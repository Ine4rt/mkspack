"""Point d'entrée : ``python -m mail_agent`` (bot Telegram)
ou ``python -m mail_agent --console`` (test en mode texte, sans Telegram)."""

from __future__ import annotations

import argparse
import logging
import sys

from .agent.llm import ClaudeAgent
from .assistant import Assistant
from .audit import AuditLog
from .config import ConfigError, Settings, load_settings
from .drafts import DraftStore
from .mail.imap_client import ImapMailbox
from .mail.service import MailService
from .mail.smtp_sender import SmtpSender


def build_assistant(settings: Settings) -> tuple[Assistant, AuditLog]:
    audit = AuditLog(settings.audit_log_path)
    mail = MailService(
        backend=ImapMailbox(settings.imap),
        sender=SmtpSender(settings.smtp),
        drafts=DraftStore(settings.db_path),
        audit=audit,
        identity=settings.identity,
        inbox=settings.imap.inbox,
        sent_folder=settings.imap.sent_folder,
    )
    assistant = Assistant(
        mail, ClaudeAgent(settings.llm), timezone=settings.timezone,
        confirmation_ttl_seconds=settings.confirmation_ttl_seconds,
        max_history_turns=settings.llm.max_history_turns,
    )
    return assistant, audit


def run_console(settings: Settings) -> None:
    assistant, _ = build_assistant(settings)
    print("Mode console — tapez votre demande (Ctrl+D pour quitter).")
    while True:
        try:
            text = input("\nVous > ").strip()
        except EOFError:
            break
        if text:
            print("\nAssistant >", assistant.handle_message("console", text).text)


def main() -> None:
    parser = argparse.ArgumentParser(prog="mail_agent")
    parser.add_argument("--console", action="store_true", help="dialogue texte dans le terminal")
    parser.add_argument("--log-level", default="INFO")
    args = parser.parse_args()
    logging.basicConfig(level=args.log_level.upper(),
                        format="%(asctime)s %(levelname)s %(name)s: %(message)s")
    # Évite de journaliser les URL de l'API Telegram (qui contiennent le token).
    logging.getLogger("httpx").setLevel(logging.WARNING)
    logging.getLogger("httpx2").setLevel(logging.WARNING)

    try:
        settings = load_settings(require_telegram=not args.console)
    except ConfigError as exc:
        sys.exit(f"Configuration invalide : {exc}")

    if args.console:
        run_console(settings)
        return

    from .channels.base import ChannelCore
    from .channels.telegram_bot import TelegramChannel
    from .voice.stt import build_stt
    from .voice.tts import build_tts

    assistant, audit = build_assistant(settings)
    core = ChannelCore(assistant, build_stt(settings.voice), build_tts(settings.voice),
                       settings.voice.reply_mode)
    TelegramChannel(settings.telegram_token, settings.allowed_user_ids, core, audit).run()


if __name__ == "__main__":
    main()
