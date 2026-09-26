"""Point d'entrée :

* ``python -m mail_agent``          bot Telegram
* ``python -m mail_agent --setup``  assistant de configuration
* ``python -m mail_agent --console`` test en mode texte, sans Telegram
"""

from __future__ import annotations

import argparse
import logging
import logging.handlers
import sys
import time

from .agent.llm import ClaudeAgent
from .assistant import Assistant
from .audit import AuditLog
from .config import PROJECT_ROOT, ConfigError, Settings, load_settings
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


def setup_logging(level: str) -> None:
    log_dir = PROJECT_ROOT / "data"
    log_dir.mkdir(parents=True, exist_ok=True)
    handlers: list[logging.Handler] = [logging.handlers.RotatingFileHandler(
        log_dir / "mail_agent.log", maxBytes=2_000_000, backupCount=3, encoding="utf-8")]
    if sys.stderr is not None:  # absent quand lancé sans fenêtre (pythonw)
        handlers.append(logging.StreamHandler())
    logging.basicConfig(level=level.upper(), handlers=handlers,
                        format="%(asctime)s %(levelname)s %(name)s: %(message)s")
    # Évite de journaliser les URL de l'API Telegram (qui contiennent le token).
    logging.getLogger("httpx").setLevel(logging.WARNING)
    logging.getLogger("httpx2").setLevel(logging.WARNING)


_lock_handle = None


def acquire_single_instance_lock() -> bool:
    """Empêche deux bots de tourner en même temps avec le même token."""
    global _lock_handle
    path = PROJECT_ROOT / "data" / "bot.lock"
    path.parent.mkdir(parents=True, exist_ok=True)
    handle = open(path, "a+")
    try:
        if sys.platform == "win32":
            import msvcrt

            handle.seek(0)
            msvcrt.locking(handle.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl

            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        handle.close()
        return False
    _lock_handle = handle
    return True


def main() -> None:
    parser = argparse.ArgumentParser(prog="mail_agent")
    parser.add_argument("--setup", action="store_true", help="assistant de configuration")
    parser.add_argument("--console", action="store_true", help="dialogue texte dans le terminal")
    parser.add_argument("--log-level", default="INFO")
    args = parser.parse_args()

    if args.setup:
        from .setup_wizard import run_setup

        run_setup()
        return

    setup_logging(args.log_level)
    log = logging.getLogger("mail_agent")
    try:
        settings = load_settings(require_telegram=not args.console)
    except ConfigError as exc:
        log.error("Configuration invalide : %s (lancez : python -m mail_agent --setup)", exc)
        sys.exit(f"Configuration invalide : {exc}")

    if args.console:
        run_console(settings)
        return

    if not acquire_single_instance_lock():
        log.warning("Le bot tourne déjà sur cet ordinateur : ce second lancement s'arrête.")
        return

    from .channels.base import ChannelCore
    from .channels.telegram_bot import TelegramChannel
    from .voice.stt import build_stt
    from .voice.tts import build_tts

    assistant, audit = build_assistant(settings)
    core = ChannelCore(assistant, build_stt(settings.voice), build_tts(settings.voice),
                       settings.voice.reply_mode)
    from telegram.error import NetworkError

    while True:  # au démarrage de l'ordinateur, Internet peut ne pas être encore là
        try:
            TelegramChannel(settings.telegram_token, settings.allowed_user_ids, core, audit).run()
            return
        except NetworkError as exc:
            log.warning("Telegram injoignable (%s), nouvel essai dans 30 s", exc)
            time.sleep(30)


if __name__ == "__main__":
    main()
