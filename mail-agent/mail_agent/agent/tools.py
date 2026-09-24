"""Outils exposés au modèle IA.

Volontairement ABSENTS de cette liste : l'envoi d'un e-mail et le marquage
« lu ». Ces opérations existent dans `MailService` mais ne sont accessibles
qu'au `ConfirmationGate` de l'assistant, après confirmation explicite de
l'utilisateur. Ainsi, même un modèle manipulé par un e-mail piégé (prompt
injection) ne peut rien envoyer.
"""

from __future__ import annotations

import logging
import re
from datetime import date
from typing import Any
from zoneinfo import ZoneInfo

from ..drafts import Draft, DraftStateError
from ..mail.models import Email, SearchQuery
from ..mail.service import MailService
from .session import ChatSession

log = logging.getLogger(__name__)

_EMAIL_RE = re.compile(r"^[^@\s<>]+@[^@\s<>]+\.[^@\s<>]+$")

TOOLS: list[dict[str, Any]] = [
    {
        "name": "list_unread_emails",
        "description": "Liste les mails NON LUS de la boîte de réception, du plus récent au "
                       "plus ancien, avec un extrait. Ne modifie pas leur statut non lu.",
        "input_schema": {
            "type": "object",
            "properties": {"limit": {"type": "integer", "description": "1 à 20, défaut 5"}},
        },
    },
    {
        "name": "list_latest_emails",
        "description": "Liste les derniers mails reçus (lus ou non), du plus récent au plus "
                       "ancien, avec un extrait. Ne modifie pas leur statut.",
        "input_schema": {
            "type": "object",
            "properties": {"limit": {"type": "integer", "description": "1 à 20, défaut 5"}},
        },
    },
    {
        "name": "search_emails",
        "description": "Recherche des mails (recherche serveur IMAP, sous-chaîne, insensible "
                       "à la casse). Combine les critères. Utilise des termes courts : un "
                       "nom, un mot-clé. Ne modifie pas le statut des mails.",
        "input_schema": {
            "type": "object",
            "properties": {
                "sender": {"type": "string", "description": "Nom ou adresse de l'expéditeur"},
                "recipient": {"type": "string", "description": "Nom ou adresse du destinataire"},
                "subject": {"type": "string", "description": "Mot-clé dans l'objet"},
                "text": {"type": "string", "description": "Mot-clé n'importe où dans le mail"},
                "since": {"type": "string", "description": "Date AAAA-MM-JJ incluse"},
                "before": {"type": "string", "description": "Date AAAA-MM-JJ exclue"},
                "unread_only": {"type": "boolean"},
                "limit": {"type": "integer", "description": "1 à 20, défaut 10"},
            },
        },
    },
    {
        "name": "get_email",
        "description": "Lit le contenu complet d'un mail déjà listé (identifiant m1, m2…). "
                       "Ne le marque pas comme lu.",
        "input_schema": {
            "type": "object",
            "properties": {"email_id": {"type": "string"}},
            "required": ["email_id"],
        },
    },
    {
        "name": "create_reply_draft",
        "description": "Prépare un BROUILLON de réponse à un mail (destinataire = expéditeur "
                       "du mail, objet « Re: … », fil de discussion conservé). N'envoie rien : "
                       "l'application le présente ensuite à l'utilisateur pour confirmation.",
        "input_schema": {
            "type": "object",
            "properties": {
                "email_id": {"type": "string", "description": "Mail auquel on répond (m1…)"},
                "body": {"type": "string", "description": "Texte du mail, sans la signature"},
            },
            "required": ["email_id", "body"],
        },
    },
    {
        "name": "create_new_draft",
        "description": "Prépare un BROUILLON de nouveau mail (pas une réponse), uniquement "
                       "vers une adresse explicitement donnée par l'utilisateur. N'envoie rien.",
        "input_schema": {
            "type": "object",
            "properties": {
                "to": {"type": "string", "description": "Adresse e-mail du destinataire"},
                "to_name": {"type": "string"},
                "subject": {"type": "string"},
                "body": {"type": "string", "description": "Texte du mail, sans la signature"},
            },
            "required": ["to", "subject", "body"],
        },
    },
    {
        "name": "update_draft",
        "description": "Modifie un brouillon existant (texte et/ou objet). Toute confirmation "
                       "précédente devient caduque ; le brouillon modifié est représenté.",
        "input_schema": {
            "type": "object",
            "properties": {
                "draft_id": {"type": "string"},
                "body": {"type": "string", "description": "Nouveau texte complet, sans signature"},
                "subject": {"type": "string"},
            },
            "required": ["draft_id"],
        },
    },
    {
        "name": "cancel_draft",
        "description": "Abandonne un brouillon. Rien n'est envoyé, le mail d'origine reste non lu.",
        "input_schema": {
            "type": "object",
            "properties": {"draft_id": {"type": "string"}},
            "required": ["draft_id"],
        },
    },
    {
        "name": "request_send_confirmation",
        "description": "Représente un brouillon existant à l'utilisateur avec la question "
                       "« Je l'envoie ? ». À utiliser quand il demande d'envoyer un brouillon "
                       "qui n'est pas celui qui vient d'être présenté.",
        "input_schema": {
            "type": "object",
            "properties": {"draft_id": {"type": "string"}},
            "required": ["draft_id"],
        },
    },
    {
        "name": "list_drafts",
        "description": "Liste les brouillons en cours (non envoyés) de cette conversation.",
        "input_schema": {"type": "object", "properties": {}},
    },
]


class ToolError(Exception):
    pass


def _neutralize(text: str) -> str:
    """Empêche un contenu de mail de refermer/ouvrir nos balises de données."""
    return re.sub(r"<\s*(/?)\s*email", r"‹\1email", text, flags=re.IGNORECASE)


def _parse_date(value: Any) -> date | None:
    if not value:
        return None
    try:
        return date.fromisoformat(str(value)[:10])
    except ValueError as exc:
        raise ToolError(f"Date invalide : {value!r} (format AAAA-MM-JJ attendu)") from exc


def _limit(value: Any, default: int) -> int:
    try:
        return max(1, min(int(value), 20))
    except (TypeError, ValueError):
        return default


class ToolExecutor:
    """Exécute les outils pour UN tour de conversation."""

    def __init__(self, mail: MailService, session: ChatSession, tz: str = "Europe/Brussels"):
        self.mail = mail
        self.session = session
        self.tz = ZoneInfo(tz)
        # Brouillon à présenter à l'utilisateur à la fin du tour (le dernier gagne).
        self.draft_to_present: Draft | None = None

    # --------------------------------------------------------------- formatage
    def _fmt_date(self, email: Email) -> str:
        if not email.date:
            return "inconnue"
        d = email.date.astimezone(self.tz) if email.date.tzinfo else email.date
        return d.strftime("%a %d/%m/%Y %H:%M")

    def format_email(self, email: Email, *, full: bool) -> str:
        ref = self.session.ref_for(email)
        lines = [
            f'<email id="{ref}" contenu_non_fiable="oui">',
            f"De : {_neutralize(email.sender_display)}",
            f"À : {', '.join(email.to) or '-'}" + (f" ; Cc : {', '.join(email.cc)}" if email.cc else ""),
            f"Date : {self._fmt_date(email)}",
            f"Objet : {_neutralize(email.subject)}",
            f"Statut : {'NON LU' if email.is_unread else 'lu'}"
            + (" (déjà répondu)" if email.is_answered else ""),
        ]
        if email.attachments:
            lines.append("Pièces jointes : " + "; ".join(_neutralize(a.describe()) for a in email.attachments))
        body = email.body_text if full else email.snippet(600)
        lines += [("Contenu :" if full else "Extrait :"), _neutralize(body) or "(vide)", "</email>"]
        return "\n".join(lines)

    def _format_list(self, emails: list[Email], empty: str) -> str:
        if not emails:
            return empty
        return f"{len(emails)} mail(s) :\n\n" + "\n\n".join(
            self.format_email(e, full=False) for e in emails
        )

    def _draft_summary(self, d: Draft) -> str:
        kind = "réponse" if d.is_reply else "nouveau message"
        return (f"Brouillon {d.id} ({kind}, v{d.version}, statut {d.status.value}) → "
                f"{', '.join(d.to)} ; objet « {d.subject} »")

    def _get_draft(self, draft_id: str) -> Draft:
        draft = self.mail.drafts.get(str(draft_id).strip())
        if draft is None or draft.chat_id != self.session.chat_id:
            raise ToolError(f"Brouillon introuvable : {draft_id}")
        if not draft.is_active:
            raise ToolError(f"Le brouillon {draft.id} est {draft.status.value} : plus modifiable.")
        return draft

    def _get_email(self, email_id: str) -> Email:
        ref = self.session.resolve(str(email_id))
        if ref is None:
            raise ToolError(f"Identifiant de mail inconnu : {email_id}. Liste ou cherche d'abord.")
        email = self.mail.get(ref.mailbox, ref.uid)
        if email is None:
            raise ToolError("Ce mail n'existe plus sur le serveur (supprimé ou déplacé).")
        return email

    # --------------------------------------------------------------- exécution
    def execute(self, name: str, args: dict[str, Any]) -> tuple[str, bool]:
        """Retourne (résultat texte, is_error)."""
        handler = getattr(self, f"_tool_{name}", None)
        if handler is None:
            return f"Outil inconnu : {name}", True
        try:
            return handler(args or {}), False
        except (ToolError, DraftStateError) as exc:
            return str(exc), True
        except Exception as exc:  # erreur IMAP, réseau…
            log.exception("Erreur outil %s", name)
            return f"Erreur technique ({type(exc).__name__}) : {exc}", True

    def _tool_list_unread_emails(self, args):
        emails = self.mail.list_unread(_limit(args.get("limit"), 5))
        return self._format_list(emails, "Aucun mail non lu.")

    def _tool_list_latest_emails(self, args):
        emails = self.mail.list_latest(_limit(args.get("limit"), 5))
        return self._format_list(emails, "La boîte de réception est vide.")

    def _tool_search_emails(self, args):
        query = SearchQuery(
            from_=args.get("sender") or None, to=args.get("recipient") or None,
            subject=args.get("subject") or None, text=args.get("text") or None,
            since=_parse_date(args.get("since")), before=_parse_date(args.get("before")),
            unread_only=bool(args.get("unread_only", False)), mailbox=self.mail.inbox,
        )
        emails = self.mail.search(query, _limit(args.get("limit"), 10))
        return self._format_list(emails, "Aucun mail ne correspond à cette recherche.")

    def _tool_get_email(self, args):
        return self.format_email(self._get_email(args.get("email_id", "")), full=True)

    def _tool_create_reply_draft(self, args):
        body = str(args.get("body", "")).strip()
        if not body:
            raise ToolError("Le texte du brouillon est vide.")
        original = self._get_email(args.get("email_id", ""))
        draft = self.mail.create_reply_draft(self.session.chat_id, original, body)
        self.draft_to_present = draft
        return self._draft_summary(draft) + "\nIl sera présenté automatiquement à l'utilisateur."

    def _tool_create_new_draft(self, args):
        to = str(args.get("to", "")).strip()
        if not _EMAIL_RE.match(to):
            raise ToolError(f"Adresse e-mail invalide : {to!r}")
        body = str(args.get("body", "")).strip()
        if not body:
            raise ToolError("Le texte du brouillon est vide.")
        draft = self.mail.create_new_draft(
            self.session.chat_id, [to], str(args.get("subject", "")).strip() or "(sans objet)",
            body, to_name=str(args.get("to_name", "")).strip(),
        )
        self.draft_to_present = draft
        return self._draft_summary(draft) + "\nIl sera présenté automatiquement à l'utilisateur."

    def _tool_update_draft(self, args):
        draft = self._get_draft(args.get("draft_id", ""))
        body = args.get("body")
        subject = args.get("subject")
        if not body and not subject:
            raise ToolError("Rien à modifier : fournis body et/ou subject.")
        draft = self.mail.update_draft(draft, body=str(body).strip() if body else None,
                                       subject=str(subject).strip() if subject else None)
        self.draft_to_present = draft
        return self._draft_summary(draft) + "\nIl sera présenté automatiquement à l'utilisateur."

    def _tool_cancel_draft(self, args):
        draft = self.mail.cancel_draft(self._get_draft(args.get("draft_id", "")), "demande utilisateur")
        if self.draft_to_present and self.draft_to_present.id == draft.id:
            self.draft_to_present = None
        return f"Brouillon {draft.id} abandonné. Rien n'a été envoyé ; le mail d'origine reste non lu."

    def _tool_request_send_confirmation(self, args):
        draft = self._get_draft(args.get("draft_id", ""))
        self.draft_to_present = draft
        return self._draft_summary(draft) + "\nIl va être présenté avec la question « Je l'envoie ? »."

    def _tool_list_drafts(self, args):
        drafts = self.mail.drafts.list_active(self.session.chat_id)
        if not drafts:
            return "Aucun brouillon en cours."
        return "\n".join(self._draft_summary(d) for d in drafts)
