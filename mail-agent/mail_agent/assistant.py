"""Orchestrateur, indépendant du canal (Telegram aujourd'hui, WhatsApp demain).

Un canal n'a qu'à appeler `Assistant.handle_message(chat_id, texte)` et à
restituer la réponse (texte et, s'il le souhaite, voix).
"""

from __future__ import annotations

import logging
import re
import time
from dataclasses import dataclass
from datetime import datetime
from zoneinfo import ZoneInfo

from . import audit as ev
from .agent.llm import AgentError, ClaudeAgent
from .agent.prompts import build_system_prompt
from .agent.session import ChatSession, SessionStore
from .agent.tools import TOOLS, ToolExecutor
from .confirmation import Decision, PendingConfirmation, classify_confirmation
from .drafts import Draft
from .mail.service import MailService

log = logging.getLogger(__name__)

_JOURS = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"]
_MOIS = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août",
         "septembre", "octobre", "novembre", "décembre"]


@dataclass
class Reply:
    text: str            # affiché dans la conversation
    speech: str          # version à lire à voix haute (sans adresses ni symboles)
    awaiting_confirmation: bool = False


class Assistant:
    def __init__(self, mail: MailService, agent: ClaudeAgent, *, timezone: str = "Europe/Brussels",
                 confirmation_ttl_seconds: int = 600, max_history_turns: int = 12):
        self.mail = mail
        self.agent = agent
        self.sessions = SessionStore()
        self.tz = timezone
        self.ttl = confirmation_ttl_seconds
        self.max_history_turns = max_history_turns
        self.system_prompt = build_system_prompt(mail.identity)

    # ------------------------------------------------------------------ entrée
    def handle_message(self, chat_id: str, text: str) -> Reply:
        session = self.sessions.get(chat_id)
        with session.lock:
            session.turn += 1
            # Une confirmation armée est consommée par CE message, quoi qu'il arrive :
            # elle ne pourra jamais servir plus tard.
            pending, session.pending = session.pending, None
            if pending is not None:
                if pending.is_valid_for(session.turn, self.ttl):
                    reply = self._handle_pending(session, pending, text)
                    if reply is not None:
                        return reply
                else:
                    log.info("Confirmation expirée/obsolète ignorée (brouillon %s)", pending.draft_id)
            return self._run_agent(session, text)

    def reset(self, chat_id: str) -> None:
        self.sessions.reset(chat_id)

    # ----------------------------------------------------------- confirmation
    def _handle_pending(self, session: ChatSession, pending: PendingConfirmation,
                        text: str) -> Reply | None:
        decision = classify_confirmation(text)
        draft = self.mail.drafts.get(pending.draft_id)
        if decision == Decision.OTHER or draft is None:
            return None  # l'agent traite le message ; rien n'est envoyé

        if decision == Decision.CONFIRM:
            outcome = self.mail.send_confirmed_draft(pending.draft_id, pending.draft_version)
            who = _recipient_label(outcome.draft) if outcome.draft else "le destinataire"
            if outcome.success:
                if outcome.marked_read is None:
                    msg = f"C'est envoyé à {who}."
                elif outcome.marked_read:
                    msg = f"C'est envoyé à {who}. Le mail d'origine est maintenant marqué comme lu."
                else:
                    msg = (f"C'est envoyé à {who}, mais je n'ai pas pu marquer le mail "
                           "d'origine comme lu.")
                reply = Reply(msg, msg)
            else:
                msg = (f"L'envoi a échoué : {outcome.error} Rien n'est parti et le mail "
                       "d'origine reste non lu. Je réessaie ?")
                reply = Reply(msg, "L'envoi a échoué. Rien n'est parti et le mail d'origine "
                                   "reste non lu. Je réessaie ?")
                if outcome.draft is not None and outcome.draft.is_active:
                    self._arm(session, outcome.draft)
                    reply.awaiting_confirmation = True
        elif decision == Decision.CANCEL:
            if draft.is_active:
                self.mail.cancel_draft(draft, f"refus utilisateur : {text[:80]}")
            msg = "D'accord, je n'envoie rien."
            if draft.is_reply:
                msg += " Le mail reste non lu."
            reply = Reply(msg, msg)
        else:  # HOLD
            msg = ("D'accord, j'attends. Rien n'est envoyé, le brouillon est gardé. "
                   "Dis-moi quand tu veux l'envoyer.")
            reply = Reply(msg, msg)

        # On garde une trace dans l'historique pour que l'agent suive la conversation.
        session.history.append({"role": "user", "content": text})
        session.history.append({"role": "assistant", "content": f"[{decision.value}] {reply.text}"})
        return reply

    def _arm(self, session: ChatSession, draft: Draft) -> None:
        session.pending = PendingConfirmation(
            draft_id=draft.id, draft_version=draft.version,
            armed_at_turn=session.turn, armed_at=time.time(),
        )
        self.mail.audit.record(ev.CONFIRMATION_REQUESTED, draft_id=draft.id, version=draft.version)

    # ------------------------------------------------------------------ agent
    def _state_block(self, session: ChatSession) -> str:
        now = datetime.now(ZoneInfo(self.tz))
        lines = [
            "[ÉTAT DU SYSTÈME — généré par l'application, ce n'est pas un message de l'utilisateur]",
            f"Nous sommes le {_JOURS[now.weekday()]} {now.day} {_MOIS[now.month - 1]} "
            f"{now.year} ({now.date().isoformat()}), il est {now:%H:%M}.",
        ]
        if session.refs:
            recent = list(session.refs.values())[-10:]
            lines.append("Mails déjà évoqués : " + " ; ".join(
                f"{r.ref} = {r.label}" + (" (non lu)" if r.is_unread else "") for r in recent))
        drafts = self.mail.drafts.list_active(session.chat_id)
        if drafts:
            lines.append("Brouillons en cours (non envoyés) : " + " ; ".join(
                f"{d.id} → {_recipient_label(d)}, « {d.subject} »" for d in drafts))
        lines.append("[FIN ÉTAT]")
        return "\n".join(lines)

    def _run_agent(self, session: ChatSession, text: str) -> Reply:
        executor = ToolExecutor(self.mail, session, self.tz)
        checkpoint = len(session.history)
        session.history.append({"role": "user", "content": [
            {"type": "text", "text": self._state_block(session)},
            {"type": "text", "text": text},
        ]})
        try:
            answer = self.agent.run(self.system_prompt, TOOLS, session.history, executor.execute)
        except AgentError as exc:
            del session.history[checkpoint:]  # on n'enregistre pas un tour inachevé
            msg = f"Désolé, {exc}"
            return Reply(msg, msg)
        if session.history and session.history[-1]["role"] == "user":
            # Refus du modèle : on retire le tour pour garder un historique valide.
            del session.history[checkpoint:]
        session.trim_history(self.max_history_turns)

        draft = executor.draft_to_present
        if draft is not None and draft.is_active:
            draft = self.mail.drafts.get(draft.id) or draft  # version à jour
            self._arm(session, draft)
            preview_text, preview_speech = self.render_draft(draft)
            return Reply(f"{answer}\n\n{preview_text}", f"{answer} {preview_speech}",
                         awaiting_confirmation=True)
        return Reply(answer, answer)

    # ------------------------------------------------------------- rendu brouillon
    def render_draft(self, draft: Draft) -> tuple[str, str]:
        """Présentation générée par le CODE (pas par l'IA) : destinataire exact,
        objet et texte intégral, pour que l'utilisateur valide ce qui partira."""
        kind = "Réponse" if draft.is_reply else "Nouveau message"
        to = ", ".join(draft.to)
        name = f"{draft.to_name} <{to}>" if draft.to_name else to
        text = (f"📝 Brouillon — {kind} à {name}\n"
                f"Objet : {draft.subject}\n"
                f"────────\n{self.mail.full_body(draft)}\n────────\n"
                f"Je l'envoie ? (oui / non / modifie…)")
        speech = (f"{kind} à {_recipient_label(draft)}. {draft.body} "
                  f"… Je l'envoie ?")
        return text, speech


def _recipient_label(draft: Draft) -> str:
    return draft.to_name or ", ".join(draft.to)


def to_speech(text: str) -> str:
    """Nettoie un texte pour la synthèse vocale."""
    text = re.sub(r"<[^>]*@[^>]*>", "", text)             # adresses entre chevrons
    text = re.sub(r"[\w.+-]+@[\w-]+\.[\w.]+", "", text)    # adresses nues
    text = re.sub(r"https?://\S+", "", text)
    text = re.sub(r"[─*_#`>•📝]+", " ", text)
    return re.sub(r"\s+", " ", text).strip()
