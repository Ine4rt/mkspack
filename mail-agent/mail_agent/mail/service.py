"""Service e-mail : lecture, brouillons, envoi confirmé et marquage « lu ».

C'est ici que sont appliquées les règles métier critiques :

* la lecture ne modifie jamais l'état lu/non lu (délégué au backend IMAP) ;
* un envoi ne part que depuis `send_confirmed_draft`, que seul le
  `ConfirmationGate` appelle, après une confirmation explicite de
  l'utilisateur portant sur la version exacte du brouillon ;
* le mail d'origine n'est marqué lu (\\Seen + \\Answered) qu'avec un
  `SendReceipt`, qui n'existe que si le SMTP a accepté le message.
"""

from __future__ import annotations

import logging
import re
from dataclasses import dataclass
from email.message import EmailMessage
from email.utils import formataddr, formatdate, make_msgid

from .. import audit as ev
from ..audit import AuditLog
from ..config import Identity
from ..drafts import Draft, DraftStateError, DraftStatus, DraftStore
from .imap_client import MailboxBackend
from .models import Email, SearchQuery
from .smtp_sender import MailSender

log = logging.getLogger(__name__)

_REPLY_PREFIX = re.compile(r"^\s*(re|ré|réf|ref|aw|sv|antw)\s*:", re.IGNORECASE)
_RECEIPT_TOKEN = object()


@dataclass(frozen=True)
class SendReceipt:
    """Preuve d'un envoi réussi. Ne peut être créée que par MailService."""

    draft_id: str
    message_id: str
    _token: object

    def __post_init__(self):
        if self._token is not _RECEIPT_TOKEN:
            raise PermissionError("Un SendReceipt ne peut être créé que par MailService.")


@dataclass
class SendOutcome:
    success: bool
    draft: Draft | None
    error: str | None = None
    marked_read: bool | None = None  # None : pas de mail d'origine (nouveau message)


def reply_subject(subject: str) -> str:
    return subject if _REPLY_PREFIX.match(subject) else f"Re: {subject}"


class MailService:
    def __init__(self, backend: MailboxBackend, sender: MailSender, drafts: DraftStore,
                 audit: AuditLog, identity: Identity, inbox: str = "INBOX",
                 sent_folder: str = ""):
        self.backend = backend
        self.sender = sender
        self.drafts = drafts
        self.audit = audit
        self.identity = identity
        self.inbox = inbox
        self.sent_folder = sent_folder

    # ------------------------------------------------------------------ lecture
    def list_unread(self, limit: int = 5) -> list[Email]:
        emails = self.backend.search(SearchQuery(unread_only=True, mailbox=self.inbox), limit)
        self.audit.record(ev.EMAIL_LISTED, kind="unread", uids=[e.uid for e in emails])
        return emails

    def list_latest(self, limit: int = 5) -> list[Email]:
        emails = self.backend.search(SearchQuery(mailbox=self.inbox), limit)
        self.audit.record(ev.EMAIL_LISTED, kind="latest", uids=[e.uid for e in emails])
        return emails

    def search(self, query: SearchQuery, limit: int = 10) -> list[Email]:
        emails = self.backend.search(query, limit)
        self.audit.record(ev.EMAIL_SEARCHED, query=repr(query), uids=[e.uid for e in emails])
        return emails

    def get(self, mailbox: str, uid: str) -> Email | None:
        email = self.backend.fetch(mailbox, uid)
        if email:
            self.audit.record(ev.EMAIL_VIEWED, uid=uid, mailbox=mailbox,
                              sender=email.from_addr, subject=email.subject)
        return email

    # --------------------------------------------------------------- brouillons
    def create_reply_draft(self, chat_id: str, original: Email, body: str) -> Draft:
        to_addr = original.reply_to or original.from_addr
        references = " ".join(x for x in (original.references, original.message_id) if x)
        draft = self.drafts.create(
            chat_id=chat_id, to=[to_addr], to_name=original.from_name,
            subject=reply_subject(original.subject), body=body.strip(),
            reply_to_uid=original.uid, reply_to_mailbox=original.mailbox,
            reply_to_message_id=original.message_id, references=references,
        )
        self.audit.record(ev.DRAFT_CREATED, draft_id=draft.id, reply_to_uid=original.uid,
                          to=draft.to, subject=draft.subject, body=draft.body)
        return draft

    def create_new_draft(self, chat_id: str, to: list[str], subject: str, body: str,
                         to_name: str = "") -> Draft:
        draft = self.drafts.create(chat_id=chat_id, to=to, to_name=to_name,
                                   subject=subject.strip(), body=body.strip())
        self.audit.record(ev.DRAFT_CREATED, draft_id=draft.id, reply_to_uid=None,
                          to=draft.to, subject=draft.subject, body=draft.body)
        return draft

    def update_draft(self, draft: Draft, *, body: str | None = None,
                     subject: str | None = None) -> Draft:
        draft = self.drafts.update_content(draft, body=body, subject=subject)
        self.audit.record(ev.DRAFT_UPDATED, draft_id=draft.id, version=draft.version,
                          subject=draft.subject, body=draft.body)
        return draft

    def cancel_draft(self, draft: Draft, reason: str = "") -> Draft:
        draft = self.drafts.transition(draft, DraftStatus.CANCELLED)
        self.audit.record(ev.DRAFT_CANCELLED, draft_id=draft.id, reason=reason)
        return draft

    def full_body(self, draft: Draft) -> str:
        sig = self.identity.signature
        return f"{draft.body}\n\n{sig}" if sig else draft.body

    def build_message(self, draft: Draft) -> EmailMessage:
        msg = EmailMessage()
        msg["From"] = formataddr((self.identity.from_name, self.identity.from_address))
        msg["To"] = ", ".join(draft.to)
        if draft.cc:
            msg["Cc"] = ", ".join(draft.cc)
        msg["Subject"] = draft.subject
        msg["Date"] = formatdate(localtime=True)
        domain = self.identity.from_address.rpartition("@")[2] or None
        msg["Message-ID"] = make_msgid(domain=domain)
        if draft.reply_to_message_id:
            msg["In-Reply-To"] = draft.reply_to_message_id
            msg["References"] = draft.references or draft.reply_to_message_id
        msg.set_content(self.full_body(draft))
        return msg

    # -------------------------------------------------------------------- envoi
    def send_confirmed_draft(self, draft_id: str, confirmed_version: int) -> SendOutcome:
        """Envoie un brouillon que l'utilisateur vient de confirmer explicitement.

        `confirmed_version` doit être la version exacte présentée à
        l'utilisateur : si le brouillon a changé depuis, on refuse.
        """
        draft = self.drafts.get(draft_id)
        if draft is None or not draft.is_active:
            self.audit.record(ev.SEND_REJECTED, draft_id=draft_id, reason="brouillon inactif")
            return SendOutcome(False, draft, "Ce brouillon n'est plus disponible.")
        if draft.version != confirmed_version:
            self.audit.record(ev.SEND_REJECTED, draft_id=draft_id, reason="version modifiée")
            return SendOutcome(False, draft, "Le brouillon a changé depuis la confirmation.")

        if draft.status == DraftStatus.FAILED:
            self.drafts.transition(draft, DraftStatus.DRAFT)
        self.drafts.transition(draft, DraftStatus.CONFIRMED)
        self.audit.record(ev.SEND_CONFIRMED, draft_id=draft.id, version=draft.version)
        self.drafts.transition(draft, DraftStatus.SENDING)

        message = self.build_message(draft)
        try:
            self.sender.send(message)
        except Exception as exc:  # tout échec => le mail d'origine reste NON LU
            self.drafts.transition(draft, DraftStatus.FAILED, error=str(exc))
            self.audit.record(ev.SEND_FAILED, draft_id=draft.id, error=str(exc))
            log.exception("Échec d'envoi du brouillon %s", draft.id)
            return SendOutcome(False, draft, str(exc))

        self.drafts.transition(draft, DraftStatus.SENT)
        receipt = SendReceipt(draft.id, str(message["Message-ID"]), _RECEIPT_TOKEN)
        self.audit.record(ev.EMAIL_SENT, draft_id=draft.id, to=draft.to,
                          subject=draft.subject, message_id=receipt.message_id)

        if self.sent_folder:
            try:
                self.backend.append_to_folder(self.sent_folder, message.as_bytes())
            except Exception as exc:  # non bloquant : le mail est bien parti
                self.audit.record(ev.SENT_COPY_FAILED, draft_id=draft.id, error=str(exc))

        marked = self._mark_original_read(receipt) if draft.is_reply else None
        return SendOutcome(True, draft, marked_read=marked)

    def _mark_original_read(self, receipt: SendReceipt) -> bool:
        if not isinstance(receipt, SendReceipt):
            raise PermissionError("Marquage « lu » refusé : aucune preuve d'envoi.")
        draft = self.drafts.get(receipt.draft_id)
        if draft is None or draft.status != DraftStatus.SENT or not draft.is_reply:
            raise DraftStateError("Marquage « lu » refusé : la réponse n'a pas été envoyée.")
        try:
            ok = self.backend.mark_replied(
                draft.reply_to_mailbox or self.inbox, draft.reply_to_uid or "",
                draft.reply_to_message_id,
            )
        except Exception as exc:
            ok = False
            log.exception("Échec du marquage lu (uid=%s)", draft.reply_to_uid)
            self.audit.record(ev.MARK_READ_SKIPPED, draft_id=draft.id,
                              uid=draft.reply_to_uid, reason=str(exc))
            return ok
        self.audit.record(ev.MARKED_READ if ok else ev.MARK_READ_SKIPPED,
                          draft_id=draft.id, uid=draft.reply_to_uid)
        return ok
