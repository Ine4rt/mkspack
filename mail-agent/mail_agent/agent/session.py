"""Mémoire conversationnelle par chat (en mémoire vive, volontairement simple)."""

from __future__ import annotations

import threading
import time
from dataclasses import dataclass, field
from typing import Any

from ..confirmation import PendingConfirmation
from ..mail.models import Email

SESSION_IDLE_RESET_SECONDS = 6 * 3600


@dataclass
class EmailRef:
    ref: str
    mailbox: str
    uid: str
    message_id: str
    label: str  # « Jean Dupont — Polos » (pour le bloc d'état)
    is_unread: bool


@dataclass
class ChatSession:
    chat_id: str
    history: list[dict[str, Any]] = field(default_factory=list)
    turn: int = 0
    pending: PendingConfirmation | None = None
    refs: dict[str, EmailRef] = field(default_factory=dict)
    _by_key: dict[tuple[str, str], str] = field(default_factory=dict)
    last_activity: float = field(default_factory=time.time)
    lock: threading.Lock = field(default_factory=threading.Lock)

    def ref_for(self, email: Email) -> str:
        key = (email.mailbox, email.uid)
        ref = self._by_key.get(key)
        if ref is None:
            ref = f"m{len(self.refs) + 1}"
            self._by_key[key] = ref
        self.refs[ref] = EmailRef(
            ref=ref, mailbox=email.mailbox, uid=email.uid, message_id=email.message_id,
            label=f"{email.from_name or email.from_addr} — {email.subject[:80]}",
            is_unread=email.is_unread,
        )
        return ref

    def resolve(self, ref: str) -> EmailRef | None:
        return self.refs.get(ref.strip().lower())

    def trim_history(self, max_turns: int) -> None:
        """Ne garde que les `max_turns` derniers échanges, en coupant toujours au
        début d'un message utilisateur « texte » (jamais entre un appel d'outil
        et son résultat)."""
        starts = [
            i for i, m in enumerate(self.history)
            if m["role"] == "user" and not _is_tool_result(m)
        ]
        if len(starts) > max_turns:
            self.history = self.history[starts[-max_turns]:]


def _is_tool_result(message: dict[str, Any]) -> bool:
    content = message.get("content")
    return isinstance(content, list) and any(
        isinstance(b, dict) and b.get("type") == "tool_result" for b in content
    )


class SessionStore:
    def __init__(self) -> None:
        self._sessions: dict[str, ChatSession] = {}
        self._lock = threading.Lock()

    def get(self, chat_id: str) -> ChatSession:
        with self._lock:
            session = self._sessions.get(chat_id)
            if session is None or time.time() - session.last_activity > SESSION_IDLE_RESET_SECONDS:
                session = ChatSession(chat_id)
                self._sessions[chat_id] = session
            session.last_activity = time.time()
            return session

    def reset(self, chat_id: str) -> None:
        with self._lock:
            self._sessions.pop(chat_id, None)
