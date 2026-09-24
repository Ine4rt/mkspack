from __future__ import annotations

import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))

from fakes import FakeMailbox, FakeSender, ScriptedLLM  # noqa: E402

from mail_agent.agent.llm import ClaudeAgent  # noqa: E402
from mail_agent.assistant import Assistant  # noqa: E402
from mail_agent.audit import AuditLog  # noqa: E402
from mail_agent.config import Identity, LlmSettings  # noqa: E402
from mail_agent.drafts import DraftStore  # noqa: E402
from mail_agent.mail.service import MailService  # noqa: E402

SIGNATURE = "Bien à vous,\n\nDimitri\nIneart\nImpression & broderie textile"


class Env:
    def __init__(self, sender_fails: bool = False):
        self.mailbox = FakeMailbox()
        self.sender = FakeSender(fail=sender_fails)
        self.audit = AuditLog(None)
        self.llm = ScriptedLLM()
        identity = Identity("moi@ineart.test", "Dimitri - Ineart", "Dimitri", SIGNATURE)
        self.mail = MailService(self.mailbox, self.sender, DraftStore(":memory:"), self.audit,
                                identity)
        agent = ClaudeAgent(LlmSettings(api_key="test", model="claude-opus-5"), client=self.llm)
        self.assistant = Assistant(self.mail, agent)

    def say(self, message: str, chat_id: str = "42"):
        return self.assistant.handle_message(chat_id, message)


@pytest.fixture
def env() -> Env:
    return Env()


@pytest.fixture
def failing_env() -> Env:
    return Env(sender_fails=True)
