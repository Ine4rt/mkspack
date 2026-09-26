"""Configuration chargée exclusivement depuis les variables d'environnement.

Aucun secret n'est codé en dur : tout vient de l'environnement (ou d'un
fichier .env chargé au démarrage, jamais versionné).
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


# Dossier du projet (celui qui contient .env), quel que soit le dossier courant :
# le bot peut ainsi être lancé au démarrage de l'ordinateur.
PROJECT_ROOT = Path(__file__).resolve().parent.parent
ENV_FILE = PROJECT_ROOT / ".env"


class ConfigError(RuntimeError):
    pass


def _get(name: str, default: str | None = None, *, required: bool = False) -> str:
    value = os.environ.get(name)
    if value is None or value.strip() == "":
        if required:
            raise ConfigError(f"Variable d'environnement manquante : {name}")
        return default if default is not None else ""
    return value.strip()


def _get_bool(name: str, default: bool) -> bool:
    raw = _get(name, "")
    if raw == "":
        return default
    return raw.lower() in {"1", "true", "yes", "oui", "on"}


def _get_int(name: str, default: int) -> int:
    raw = _get(name, "")
    return int(raw) if raw else default


@dataclass(frozen=True)
class ImapSettings:
    host: str
    port: int
    user: str
    password: str
    use_ssl: bool = True
    inbox: str = "INBOX"
    # Dossier où copier les e-mails envoyés (vide = ne pas copier ; Gmail
    # le fait automatiquement, la plupart des autres serveurs non).
    sent_folder: str = ""


@dataclass(frozen=True)
class SmtpSettings:
    host: str
    port: int
    user: str
    password: str
    security: str = "ssl"  # "ssl" (port 465) | "starttls" (port 587) | "none"


@dataclass(frozen=True)
class Identity:
    from_address: str
    from_name: str
    user_first_name: str
    signature: str


@dataclass(frozen=True)
class LlmSettings:
    api_key: str
    model: str = "claude-opus-5"
    effort: str = "medium"
    # "default" = fallback serveur recommandé par Anthropic en cas de refus ;
    # "off" = désactivé.
    fallbacks: str = "default"
    max_history_turns: int = 12


@dataclass(frozen=True)
class VoiceSettings:
    stt_provider: str = "openai"  # openai | local | none
    stt_api_key: str = ""
    stt_base_url: str = ""
    stt_model: str = "gpt-4o-mini-transcribe"
    stt_language: str = "fr"
    local_whisper_model: str = "small"
    tts_provider: str = "openai"  # openai | edge | none
    tts_api_key: str = ""
    tts_base_url: str = ""
    tts_model: str = "gpt-4o-mini-tts"
    tts_voice: str = "alloy"
    edge_tts_voice: str = "fr-BE-CharlineNeural"
    # voice_if_voice : réponse vocale seulement si on m'a parlé ;
    # always : toujours ; never : jamais.
    reply_mode: str = "voice_if_voice"


@dataclass(frozen=True)
class Settings:
    telegram_token: str
    allowed_user_ids: frozenset[int]
    imap: ImapSettings
    smtp: SmtpSettings
    identity: Identity
    llm: LlmSettings
    voice: VoiceSettings
    data_dir: Path
    confirmation_ttl_seconds: int = 600
    timezone: str = "Europe/Brussels"

    @property
    def audit_log_path(self) -> Path:
        return self.data_dir / "audit.jsonl"

    @property
    def db_path(self) -> Path:
        return self.data_dir / "mail_agent.sqlite3"


def _load_signature() -> str:
    path = _get("MAIL_SIGNATURE_FILE")
    if path:
        return Path(path).read_text(encoding="utf-8").rstrip()
    # Dans .env, les retours à la ligne s'écrivent "\n".
    return _get("MAIL_SIGNATURE", "").replace("\\n", "\n").rstrip()


def load_settings(*, require_telegram: bool = True) -> Settings:
    """Construit la configuration à partir de l'environnement."""
    try:
        from dotenv import load_dotenv

        load_dotenv(ENV_FILE if ENV_FILE.exists() else None)
    except ImportError:  # python-dotenv est optionnel
        pass

    imap_user = _get("IMAP_USER", required=True)
    imap_password = _get("IMAP_PASSWORD", required=True)
    imap = ImapSettings(
        host=_get("IMAP_HOST", required=True),
        port=_get_int("IMAP_PORT", 993),
        user=imap_user,
        password=imap_password,
        use_ssl=_get_bool("IMAP_SSL", True),
        inbox=_get("IMAP_INBOX", "INBOX"),
        sent_folder=_get("IMAP_SENT_FOLDER", ""),
    )
    smtp = SmtpSettings(
        host=_get("SMTP_HOST", required=True),
        port=_get_int("SMTP_PORT", 465),
        user=_get("SMTP_USER", imap_user),
        password=_get("SMTP_PASSWORD", imap_password),
        security=_get("SMTP_SECURITY", "ssl").lower(),
    )
    identity = Identity(
        from_address=_get("MAIL_FROM_ADDRESS", imap_user),
        from_name=_get("MAIL_FROM_NAME", ""),
        user_first_name=_get("ASSISTANT_USER_NAME", ""),
        signature=_load_signature(),
    )
    llm = LlmSettings(
        api_key=_get("ANTHROPIC_API_KEY", required=True),
        model=_get("LLM_MODEL", "claude-opus-5"),
        effort=_get("LLM_EFFORT", "medium"),
        fallbacks=_get("LLM_FALLBACKS", "default"),
        max_history_turns=_get_int("LLM_MAX_HISTORY_TURNS", 12),
    )
    openai_key = _get("OPENAI_API_KEY", "")
    voice = VoiceSettings(
        stt_provider=_get("STT_PROVIDER", "openai").lower(),
        stt_api_key=_get("STT_API_KEY", openai_key),
        stt_base_url=_get("STT_BASE_URL", ""),
        stt_model=_get("STT_MODEL", "gpt-4o-mini-transcribe"),
        stt_language=_get("STT_LANGUAGE", "fr"),
        local_whisper_model=_get("LOCAL_WHISPER_MODEL", "small"),
        tts_provider=_get("TTS_PROVIDER", "openai").lower(),
        tts_api_key=_get("TTS_API_KEY", openai_key),
        tts_base_url=_get("TTS_BASE_URL", ""),
        tts_model=_get("TTS_MODEL", "gpt-4o-mini-tts"),
        tts_voice=_get("TTS_VOICE", "alloy"),
        edge_tts_voice=_get("EDGE_TTS_VOICE", "fr-BE-CharlineNeural"),
        reply_mode=_get("VOICE_REPLY_MODE", "voice_if_voice").lower(),
    )

    allowed_raw = _get("TELEGRAM_ALLOWED_USER_IDS", "")
    allowed = frozenset(int(x) for x in allowed_raw.replace(";", ",").split(",") if x.strip())
    token = _get("TELEGRAM_BOT_TOKEN", required=require_telegram)
    if require_telegram and not allowed:
        # Sans liste blanche, n'importe qui trouvant le bot pourrait lire vos mails.
        raise ConfigError(
            "TELEGRAM_ALLOWED_USER_IDS est obligatoire (votre identifiant Telegram numérique)."
        )

    data_dir = Path(_get("DATA_DIR", "data")).expanduser()
    if not data_dir.is_absolute():
        data_dir = PROJECT_ROOT / data_dir
    data_dir.mkdir(parents=True, exist_ok=True)

    return Settings(
        telegram_token=token,
        allowed_user_ids=allowed,
        imap=imap,
        smtp=smtp,
        identity=identity,
        llm=llm,
        voice=voice,
        data_dir=data_dir,
        confirmation_ttl_seconds=_get_int("CONFIRMATION_TTL_SECONDS", 600),
        timezone=_get("TIMEZONE", "Europe/Brussels"),
    )
