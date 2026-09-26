"""Brouillons de réponse, persistés en SQLite.

Cycle de vie (jamais de raccourci) :

    DRAFT ──(confirmation explicite)──> CONFIRMED ──> SENDING ──> SENT
      │                                                   └─────> FAILED
      └──(annulation)──> CANCELLED

Un brouillon n'est JAMAIS considéré comme une réponse envoyée : seul l'état
SENT, atteint après un envoi SMTP réussi, compte.
"""

from __future__ import annotations

import json
import sqlite3
import threading
import uuid
from dataclasses import dataclass, field
from datetime import datetime, timezone
from enum import Enum
from pathlib import Path


class DraftStatus(str, Enum):
    DRAFT = "draft"
    CONFIRMED = "confirmed"
    SENDING = "sending"
    SENT = "sent"
    FAILED = "failed"
    CANCELLED = "cancelled"


_ALLOWED_TRANSITIONS = {
    DraftStatus.DRAFT: {DraftStatus.CONFIRMED, DraftStatus.CANCELLED, DraftStatus.DRAFT},
    DraftStatus.CONFIRMED: {DraftStatus.SENDING, DraftStatus.CANCELLED},
    DraftStatus.SENDING: {DraftStatus.SENT, DraftStatus.FAILED},
    # Après un échec, on peut réessayer (nouvelle confirmation) ou abandonner.
    DraftStatus.FAILED: {DraftStatus.DRAFT, DraftStatus.CANCELLED},
    DraftStatus.SENT: set(),
    DraftStatus.CANCELLED: set(),
}


class DraftStateError(RuntimeError):
    pass


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


@dataclass
class Draft:
    id: str
    chat_id: str
    to: list[str]
    to_name: str
    subject: str
    body: str
    cc: list[str] = field(default_factory=list)
    status: DraftStatus = DraftStatus.DRAFT
    version: int = 1
    # Mail d'origine (None pour un nouveau message).
    reply_to_uid: str | None = None
    reply_to_mailbox: str | None = None
    reply_to_message_id: str = ""
    references: str = ""
    created_at: str = field(default_factory=_now)
    updated_at: str = field(default_factory=_now)
    sent_at: str | None = None
    error: str | None = None

    @property
    def is_reply(self) -> bool:
        return self.reply_to_uid is not None

    @property
    def is_active(self) -> bool:
        return self.status in (DraftStatus.DRAFT, DraftStatus.FAILED)


_COLUMNS = [
    "id", "chat_id", "to_addrs", "to_name", "subject", "body", "cc", "status", "version",
    "reply_to_uid", "reply_to_mailbox", "reply_to_message_id", "refs", "created_at",
    "updated_at", "sent_at", "error",
]


class DraftStore:
    def __init__(self, path: Path | str = ":memory:"):
        self._conn = sqlite3.connect(str(path), check_same_thread=False)
        self._lock = threading.Lock()
        with self._lock:
            self._conn.execute(
                f"CREATE TABLE IF NOT EXISTS drafts ({', '.join(c + ' TEXT' for c in _COLUMNS)},"
                " PRIMARY KEY (id))"
            )
            self._conn.commit()

    # --- sérialisation ------------------------------------------------------
    @staticmethod
    def _to_row(d: Draft) -> tuple:
        return (
            d.id, d.chat_id, json.dumps(d.to), d.to_name, d.subject, d.body, json.dumps(d.cc),
            d.status.value, str(d.version), d.reply_to_uid, d.reply_to_mailbox,
            d.reply_to_message_id, d.references, d.created_at, d.updated_at, d.sent_at, d.error,
        )

    @staticmethod
    def _from_row(row: tuple) -> Draft:
        r = dict(zip(_COLUMNS, row))
        return Draft(
            id=r["id"], chat_id=r["chat_id"], to=json.loads(r["to_addrs"]), to_name=r["to_name"],
            subject=r["subject"], body=r["body"], cc=json.loads(r["cc"]),
            status=DraftStatus(r["status"]), version=int(r["version"]),
            reply_to_uid=r["reply_to_uid"], reply_to_mailbox=r["reply_to_mailbox"],
            reply_to_message_id=r["reply_to_message_id"] or "", references=r["refs"] or "",
            created_at=r["created_at"], updated_at=r["updated_at"], sent_at=r["sent_at"],
            error=r["error"],
        )

    def _save(self, d: Draft) -> None:
        with self._lock:
            self._conn.execute(
                f"INSERT OR REPLACE INTO drafts ({', '.join(_COLUMNS)}) "
                f"VALUES ({', '.join('?' for _ in _COLUMNS)})",
                self._to_row(d),
            )
            self._conn.commit()

    # --- API ------------------------------------------------------------------
    def create(self, **kwargs) -> Draft:
        draft = Draft(id="d" + uuid.uuid4().hex[:6], **kwargs)
        self._save(draft)
        return draft

    def get(self, draft_id: str) -> Draft | None:
        with self._lock:
            row = self._conn.execute(
                f"SELECT {', '.join(_COLUMNS)} FROM drafts WHERE id = ?", (draft_id,)
            ).fetchone()
        return self._from_row(row) if row else None

    def list_active(self, chat_id: str) -> list[Draft]:
        with self._lock:
            rows = self._conn.execute(
                f"SELECT {', '.join(_COLUMNS)} FROM drafts WHERE chat_id = ? AND status IN (?, ?)"
                " ORDER BY updated_at",
                (chat_id, DraftStatus.DRAFT.value, DraftStatus.FAILED.value),
            ).fetchall()
        return [self._from_row(r) for r in rows]

    def update_content(self, draft: Draft, *, body: str | None = None,
                       subject: str | None = None) -> Draft:
        if not draft.is_active:
            raise DraftStateError(f"Le brouillon {draft.id} n'est plus modifiable ({draft.status.value}).")
        if body is not None:
            draft.body = body
        if subject is not None:
            draft.subject = subject
        draft.version += 1  # toute confirmation antérieure devient caduque
        draft.status = DraftStatus.DRAFT
        draft.updated_at = _now()
        self._save(draft)
        return draft

    def transition(self, draft: Draft, new_status: DraftStatus, *, error: str | None = None) -> Draft:
        if new_status not in _ALLOWED_TRANSITIONS[draft.status]:
            raise DraftStateError(f"Transition interdite {draft.status.value} → {new_status.value}")
        draft.status = new_status
        draft.updated_at = _now()
        if new_status == DraftStatus.SENT:
            draft.sent_at = draft.updated_at
        draft.error = error
        self._save(draft)
        return draft
