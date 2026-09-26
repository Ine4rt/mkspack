"""Doublures de test : faux serveur mail, faux SMTP, faux modèle IA scripté."""

from __future__ import annotations

from email.message import EmailMessage
from email.utils import format_datetime
from datetime import datetime, timezone
from types import SimpleNamespace
from typing import Any, Callable

from mail_agent.mail.models import Email, SearchQuery
from mail_agent.mail.parsing import parse_email


def make_raw_email(*, sender: str, subject: str, body: str, message_id: str,
                   to: str = "moi@ineart.test", attachment: tuple[str, bytes, str] | None = None) -> bytes:
    msg = EmailMessage()
    msg["From"] = sender
    msg["To"] = to
    msg["Subject"] = subject
    msg["Message-ID"] = message_id
    msg["Date"] = format_datetime(datetime(2026, 9, 21, 9, 30, tzinfo=timezone.utc))
    msg.set_content(body)
    if attachment:
        name, data, ctype = attachment
        maintype, subtype = ctype.split("/")
        msg.add_attachment(data, maintype=maintype, subtype=subtype, filename=name)
    return msg.as_bytes()


class FakeMailbox:
    """Boîte IMAP en mémoire. Enregistre toute modification de drapeaux."""

    def __init__(self) -> None:
        self.messages: dict[str, dict[str, Any]] = {}
        self.flag_changes: list[tuple[str, set[str]]] = []
        self.appended: list[tuple[str, bytes]] = []
        self._next_uid = 100

    def add(self, **kwargs) -> str:
        uid = str(self._next_uid)
        self._next_uid += 1
        self.messages[uid] = {"raw": make_raw_email(**kwargs), "flags": set()}
        return uid

    def flags(self, uid: str) -> set[str]:
        return set(self.messages[uid]["flags"])

    def is_unread(self, uid: str) -> bool:
        return "\\Seen" not in self.messages[uid]["flags"]

    def _email(self, uid: str) -> Email:
        m = self.messages[uid]
        return parse_email(m["raw"], uid=uid, mailbox="INBOX", flags=set(m["flags"]))

    # --- interface MailboxBackend (lecture sans effet de bord) ---
    def search(self, query: SearchQuery, limit: int = 10) -> list[Email]:
        results = []
        for uid in sorted(self.messages, key=int, reverse=True):
            e = self._email(uid)
            if query.unread_only and not e.is_unread:
                continue
            if query.from_ and query.from_.lower() not in e.sender_display.lower():
                continue
            if query.subject and query.subject.lower() not in e.subject.lower():
                continue
            if query.text and query.text.lower() not in (e.subject + e.body_text).lower():
                continue
            results.append(e)
        return results[:limit]

    def fetch(self, mailbox: str, uid: str) -> Email | None:
        return self._email(uid) if uid in self.messages else None

    def mark_replied(self, mailbox: str, uid: str, expected_message_id: str) -> bool:
        e = self._email(uid)
        if expected_message_id and e.message_id != expected_message_id:
            return False
        self.messages[uid]["flags"] |= {"\\Seen", "\\Answered"}
        self.flag_changes.append((uid, {"\\Seen", "\\Answered"}))
        return True

    def append_to_folder(self, folder: str, raw: bytes) -> None:
        self.appended.append((folder, raw))


class FakeSender:
    def __init__(self, fail: bool = False) -> None:
        self.fail = fail
        self.sent: list[EmailMessage] = []

    def send(self, message: EmailMessage) -> None:
        if self.fail:
            raise ConnectionError("serveur SMTP injoignable")
        self.sent.append(message)


# ---------------------------------------------------------------- faux modèle IA
def text(t: str) -> SimpleNamespace:
    return SimpleNamespace(type="text", text=t)


_ids = iter(range(1, 10_000))


def tool(name: str, **inputs) -> SimpleNamespace:
    return SimpleNamespace(type="tool_use", name=name, input=inputs, id=f"toolu_{next(_ids)}")


Step = Callable[[list[dict]], list[SimpleNamespace]] | list[SimpleNamespace]


class ScriptedLLM:
    """Simule l'API Anthropic : chaque appel renvoie l'étape suivante du script.

    Une étape est une liste de blocs, ou une fonction (messages -> blocs) pour
    construire la réponse d'après le contexte reçu (ex. identifiants m1…).
    """

    def __init__(self) -> None:
        self.script: list[Step] = []
        self.calls: list[dict[str, Any]] = []
        self.messages = self  # client.messages.create
        self.beta = SimpleNamespace(messages=self)

    def queue(self, *steps: Step) -> None:
        self.script.extend(steps)

    def create(self, **kwargs):
        self.calls.append(kwargs)
        if not self.script:
            raise AssertionError("Appel IA inattendu (script épuisé)")
        step = self.script.pop(0)
        blocks = step(kwargs["messages"]) if callable(step) else step
        stop = "tool_use" if any(b.type == "tool_use" for b in blocks) else "end_turn"
        return SimpleNamespace(content=blocks, stop_reason=stop)


def last_tool_result(messages: list[dict]) -> str:
    for m in reversed(messages):
        if m["role"] == "user" and isinstance(m["content"], list):
            for b in m["content"]:
                if isinstance(b, dict) and b.get("type") == "tool_result":
                    return b["content"]
    raise AssertionError("aucun résultat d'outil")
