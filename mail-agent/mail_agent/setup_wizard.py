"""Assistant de configuration : ``python -m mail_agent --setup``.

Pose les questions une par une, vérifie chaque réglage en direct (bot
Telegram, clé Claude, connexion IMAP/SMTP one.com) puis écrit le fichier
.env. Aucun mail n'est lu en entier ni modifié pendant la configuration.
"""

from __future__ import annotations

import getpass
import json
import os
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

from .config import ENV_FILE, ImapSettings, SmtpSettings

ONE_COM = {"imap_host": "imap.one.com", "imap_port": "993",
           "smtp_host": "send.one.com", "smtp_port": "587", "smtp_security": "starttls"}
DEFAULT_SIGNATURE = "Bien à vous,\n\nDimitri\nIneart\nImpression & broderie textile"
DEFAULT_MODEL = "claude-opus-5"


# ------------------------------------------------------------------ saisie
def ask(question: str, default: str = "", secret: bool = False) -> str:
    hint = f" [{default}]" if default and not secret else (" [déjà enregistré]" if default else "")
    while True:
        prompt = f"{question}{hint} : "
        value = (getpass.getpass(prompt) if secret else input(prompt)).strip()
        if value:
            return value
        if default:
            return default
        print("  → Une réponse est nécessaire.")


def yes(question: str, default: bool = True) -> bool:
    answer = input(f"{question} [{'O/n' if default else 'o/N'}] : ").strip().lower()
    return default if not answer else answer in {"o", "oui", "y", "yes"}


def title(text: str) -> None:
    print(f"\n=== {text} ===")


# ------------------------------------------------------------------ fichier .env
def env_quote(value: str) -> str:
    """Met une valeur entre guillemets au format .env (python-dotenv)."""
    if "'" not in value and "\n" not in value:
        return f"'{value}'"  # aucun échappement nécessaire
    escaped = value.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")
    return f'"{escaped}"'


def write_env(values: dict[str, str], path: Path = ENV_FILE) -> None:
    lines = ["# Généré par python -m mail_agent --setup. Ne jamais partager ce fichier."]
    lines += [f"{key}={env_quote(val)}" for key, val in values.items()]
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    try:
        os.chmod(path, 0o600)
    except OSError:
        pass


def read_env(path: Path = ENV_FILE) -> dict[str, str]:
    if not path.exists():
        return {}
    from dotenv import dotenv_values

    return {k: v for k, v in dotenv_values(path, interpolate=False).items() if v is not None}


# ------------------------------------------------------------------ Telegram
def telegram_call(token: str, method: str, **params) -> dict:
    url = f"https://api.telegram.org/bot{token}/{method}"
    if params:
        url += "?" + urllib.parse.urlencode(params)
    try:
        with urllib.request.urlopen(url, timeout=int(params.get("timeout", 0)) + 15) as resp:
            data = json.load(resp)
    except urllib.error.HTTPError as exc:
        data = json.load(exc)
    if not data.get("ok"):
        raise RuntimeError(data.get("description", "réponse Telegram invalide"))
    return data["result"]


def setup_telegram(env: dict[str, str]) -> None:
    title("1/5 · Bot Telegram")
    print("Sur ton téléphone : ouvre Telegram, cherche @BotFather, envoie /newbot,\n"
          "choisis un nom puis un identifiant finissant par « bot ».\n"
          "BotFather te répond avec un token (ex. 123456789:AAH…).")
    while True:
        token = ask("Colle le token du bot", env.get("TELEGRAM_BOT_TOKEN", ""), secret=True)
        try:
            bot = telegram_call(token, "getMe")
            break
        except Exception as exc:
            print(f"  ERREUR - Token refusé par Telegram ({exc}). Réessaie.")
    env["TELEGRAM_BOT_TOKEN"] = token
    print(f"  OK - Bot trouvé : @{bot['username']}")

    if env.get("TELEGRAM_ALLOWED_USER_IDS") and yes(
            f"Garder l'utilisateur autorisé actuel ({env['TELEGRAM_ALLOWED_USER_IDS']}) ?"):
        return
    print(f"\nMaintenant, sur ton téléphone : ouvre la conversation avec @{bot['username']}\n"
          "et appuie sur « Démarrer » (ou envoie « bonjour »). J'attends ton message…")
    last_seen = None
    try:  # ignorer les anciens messages
        updates = telegram_call(token, "getUpdates", timeout=0)
        if updates:
            last_seen = updates[-1]["update_id"]
    except Exception:
        pass
    deadline = time.time() + 300
    while time.time() < deadline:
        params = {"timeout": 25}
        if last_seen is not None:
            params["offset"] = last_seen + 1
        try:
            updates = telegram_call(token, "getUpdates", **params)
        except Exception as exc:
            print(f"  (erreur Telegram : {exc}, nouvel essai)")
            time.sleep(3)
            continue
        for update in updates:
            last_seen = update["update_id"]
            message = update.get("message") or {}
            user = message.get("from")
            if user and not user.get("is_bot"):
                name = user.get("first_name", "")
                if yes(f"  Message reçu de {name} (id {user['id']}). C'est bien toi ?"):
                    env["TELEGRAM_ALLOWED_USER_IDS"] = str(user["id"])
                    telegram_call(token, "getUpdates", offset=last_seen + 1, timeout=0)
                    print("  OK - Seul ce compte Telegram pourra utiliser le bot.")
                    return
    env["TELEGRAM_ALLOWED_USER_IDS"] = ask(
        "Aucun message reçu. Entre ton identifiant Telegram numérique (via @userinfobot)")


# ------------------------------------------------------------------ Claude
def setup_claude(env: dict[str, str]) -> None:
    title("2/5 · Intelligence artificielle (Claude)")
    print("Crée une clé sur https://console.anthropic.com (menu « API Keys »)\n"
          "et ajoute un peu de crédit (Billing). La clé commence par sk-ant-…")
    import anthropic

    model = env.get("LLM_MODEL", DEFAULT_MODEL)
    while True:
        key = ask("Colle la clé API Anthropic", env.get("ANTHROPIC_API_KEY", ""), secret=True)
        try:
            anthropic.Anthropic(api_key=key, max_retries=1).models.retrieve(model)
            break
        except anthropic.AuthenticationError:
            print("  ERREUR - Clé refusée. Vérifie-la et réessaie.")
        except anthropic.APIError as exc:
            print(f"  ! Impossible de vérifier la clé ({exc}). Elle est gardée telle quelle.")
            break
    env["ANTHROPIC_API_KEY"] = key
    env.setdefault("LLM_MODEL", model)
    env.setdefault("LLM_EFFORT", "medium")
    env.setdefault("LLM_FALLBACKS", "default")
    print(f"  OK - Clé valide (modèle {model}).")


# ------------------------------------------------------------------ boîte mail
def setup_mailbox(env: dict[str, str]) -> None:
    title("3/5 · Boîte mail one.com")
    from .mail.imap_client import ImapMailbox
    from .mail.smtp_sender import SmtpSender

    address = ask("Adresse e-mail", env.get("IMAP_USER", "info@ineart.be"))
    imap_host = env.get("IMAP_HOST", ONE_COM["imap_host"])
    smtp_host = env.get("SMTP_HOST", ONE_COM["smtp_host"])
    smtp_port = env.get("SMTP_PORT", ONE_COM["smtp_port"])
    smtp_security = env.get("SMTP_SECURITY", ONE_COM["smtp_security"])
    default_pw = env.get("IMAP_PASSWORD", "") if env.get("IMAP_USER") == address else ""
    while True:
        password = ask("Mot de passe de cette boîte mail", default_pw, secret=True)
        print("  Test de la réception (IMAP)…")
        mailbox = ImapMailbox(ImapSettings(imap_host, int(env.get("IMAP_PORT", "993")),
                                           address, password))
        try:
            unread = mailbox.count_unread()
            sent_folder = mailbox.find_sent_folder()
        except Exception as exc:
            print(f"  ERREUR - Connexion refusée : {exc}")
            if yes("  Réessayer avec un autre mot de passe ?"):
                default_pw = ""
                continue
            raise SystemExit("Configuration interrompue.")
        print(f"  OK - Connecté. {unread} mail(s) non lu(s) — ils resteront non lus.")
        print("  Test de l'envoi (SMTP, aucun mail envoyé)…")
        try:
            SmtpSender(SmtpSettings(smtp_host, int(smtp_port), address, password,
                                    smtp_security)).test_login()
        except Exception as exc:
            print(f"  ERREUR - Envoi impossible : {exc}")
            if yes("  Réessayer ?"):
                continue
            raise SystemExit("Configuration interrompue.")
        print("  OK - Envoi possible.")
        break
    if sent_folder:
        print(f"  OK - Dossier des envoyés détecté : « {sent_folder} »")
    else:
        print("  ! Dossier des envoyés introuvable : les réponses ne seront pas copiées dedans.")
    env.update({
        "IMAP_HOST": imap_host, "IMAP_PORT": env.get("IMAP_PORT", "993"), "IMAP_SSL": "true",
        "IMAP_USER": address, "IMAP_PASSWORD": password, "IMAP_INBOX": "INBOX",
        "IMAP_SENT_FOLDER": sent_folder, "SMTP_HOST": smtp_host, "SMTP_PORT": smtp_port,
        "SMTP_SECURITY": smtp_security, "MAIL_FROM_ADDRESS": address,
    })


# ------------------------------------------------------------------ identité
def setup_identity(env: dict[str, str]) -> None:
    title("4/5 · Ton nom et ta signature")
    env["ASSISTANT_USER_NAME"] = ask("Ton prénom (l'assistant te tutoie)",
                                     env.get("ASSISTANT_USER_NAME", "Dimitri"))
    env["MAIL_FROM_NAME"] = ask("Nom d'expéditeur affiché", env.get("MAIL_FROM_NAME", "Ineart"))
    signature = env.get("MAIL_SIGNATURE", DEFAULT_SIGNATURE).replace("\\n", "\n")
    print("Signature actuelle :\n  " + signature.replace("\n", "\n  "))
    if not yes("La garder ?"):
        print("Tape la nouvelle signature, ligne par ligne. Termine par une ligne contenant juste un point « . »")
        lines = []
        while (line := input("  ")) != ".":
            lines.append(line)
        signature = "\n".join(lines).strip()
    env["MAIL_SIGNATURE"] = signature


# ------------------------------------------------------------------ voix
def setup_voice(env: dict[str, str]) -> None:
    title("5/5 · Voix (gratuite)")
    env.update({"STT_PROVIDER": "local", "STT_LANGUAGE": "fr", "TTS_PROVIDER": "edge",
                "VOICE_REPLY_MODE": env.get("VOICE_REPLY_MODE", "voice_if_voice")})
    env.setdefault("LOCAL_WHISPER_MODEL", "small")
    env.setdefault("EDGE_TTS_VOICE", "fr-BE-CharlineNeural")
    print("Reconnaissance vocale sur cet ordinateur (Whisper) et voix belge gratuite.")
    print("Téléchargement du modèle de reconnaissance vocale (≈ 500 Mo, une seule fois)…")
    try:
        from faster_whisper import WhisperModel

        WhisperModel(env["LOCAL_WHISPER_MODEL"], device="cpu", compute_type="int8")
        print("  OK - Modèle prêt.")
    except Exception as exc:
        print(f"  ! Téléchargement impossible pour l'instant ({exc}). Il sera retenté au démarrage.")


def run_setup() -> None:
    print("Configuration de l'assistant e-mail vocal. Ctrl+C pour quitter à tout moment.")
    env = read_env()
    for step in (setup_telegram, setup_claude, setup_mailbox, setup_identity, setup_voice):
        step(env)
    env.setdefault("TIMEZONE", "Europe/Brussels")
    env.setdefault("CONFIRMATION_TTL_SECONDS", "600")
    write_env(env)
    print(f"\nOK - Configuration enregistrée dans {ENV_FILE}")
