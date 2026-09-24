"""Structures de données du domaine e-mail."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import date, datetime


@dataclass(frozen=True)
class Attachment:
    filename: str
    content_type: str
    size: int

    def describe(self) -> str:
        kind = {
            "application/pdf": "PDF",
            "image/jpeg": "image",
            "image/png": "image",
        }.get(self.content_type, self.content_type)
        return f"{self.filename or 'sans nom'} ({kind}, {self.size // 1024 or 1} Ko)"


@dataclass
class Email:
    """Un e-mail reçu, tel que lu sur le serveur (sans modifier ses drapeaux)."""

    uid: str
    mailbox: str
    message_id: str
    subject: str
    from_name: str
    from_addr: str
    reply_to: str
    to: list[str]
    cc: list[str]
    date: datetime | None
    is_unread: bool
    body_text: str
    attachments: list[Attachment] = field(default_factory=list)
    references: str = ""
    in_reply_to: str = ""
    is_answered: bool = False

    @property
    def sender_display(self) -> str:
        return f"{self.from_name} <{self.from_addr}>" if self.from_name else self.from_addr

    def snippet(self, length: int = 300) -> str:
        text = " ".join(self.body_text.split())
        return text if len(text) <= length else text[:length].rstrip() + "…"


@dataclass(frozen=True)
class SearchQuery:
    """Critères de recherche traduits en commande IMAP SEARCH."""

    from_: str | None = None
    to: str | None = None
    subject: str | None = None
    text: str | None = None
    since: date | None = None
    before: date | None = None
    unread_only: bool = False
    mailbox: str | None = None
