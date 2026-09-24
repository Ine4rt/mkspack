"""Adaptateur Telegram (python-telegram-bot, mode long polling : aucun
serveur web ni certificat à configurer)."""

from __future__ import annotations

import logging

from telegram import Update
from telegram.constants import ChatAction
from telegram.ext import Application, CommandHandler, ContextTypes, MessageHandler, filters

from .. import audit as ev
from ..audit import AuditLog
from .base import ChannelCore, OutgoingReply

log = logging.getLogger(__name__)

TELEGRAM_MAX_TEXT = 4000

HELP = (
    "Parle-moi (message vocal) ou écris-moi naturellement, par exemple :\n"
    "• « Lis-moi mes mails non lus »\n"
    "• « Résume-moi le mail de Jean »\n"
    "• « Réponds-lui que je lui envoie le devis demain »\n"
    "• « Cherche les mails de cette semaine concernant une commande »\n\n"
    "Je ne t'envoie jamais un mail sans ta confirmation (« oui », « envoie »). "
    "Un mail reste non lu tant que tu n'y as pas répondu.\n\n"
    "/reset efface le contexte de la conversation."
)


def _split(text: str) -> list[str]:
    parts = []
    while len(text) > TELEGRAM_MAX_TEXT:
        cut = text.rfind("\n", 0, TELEGRAM_MAX_TEXT)
        cut = cut if cut > 0 else TELEGRAM_MAX_TEXT
        parts.append(text[:cut])
        text = text[cut:].lstrip("\n")
    return parts + [text]


class TelegramChannel:
    def __init__(self, token: str, allowed_user_ids: frozenset[int], core: ChannelCore,
                 audit: AuditLog):
        self.core = core
        self.audit = audit
        self.allowed = allowed_user_ids
        self.app = Application.builder().token(token).concurrent_updates(False).build()
        allowed = filters.User(user_id=list(allowed_user_ids))

        self.app.add_handler(CommandHandler(["start", "aide", "help"], self._help, filters=allowed))
        self.app.add_handler(CommandHandler("reset", self._reset, filters=allowed))
        self.app.add_handler(MessageHandler(allowed & filters.TEXT & ~filters.COMMAND, self._text))
        self.app.add_handler(MessageHandler(allowed & (filters.VOICE | filters.AUDIO), self._voice))
        # Tout le reste (utilisateurs non autorisés) : refus et trace.
        self.app.add_handler(MessageHandler(~allowed, self._unauthorized))

    # --- handlers --------------------------------------------------------------
    async def _unauthorized(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        user = update.effective_user
        self.audit.record(ev.UNAUTHORIZED_ACCESS, user_id=user.id if user else None,
                          username=user.username if user else None)
        if update.effective_message:
            await update.effective_message.reply_text("Accès non autorisé.")

    async def _help(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        await update.effective_message.reply_text(HELP)

    async def _reset(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        self.core.assistant.reset(str(update.effective_chat.id))
        await update.effective_message.reply_text("Contexte effacé. Aucun mail n'a été modifié.")

    async def _text(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        chat_id = str(update.effective_chat.id)
        await context.bot.send_chat_action(update.effective_chat.id, ChatAction.TYPING)
        reply = await self.core.handle_text(chat_id, update.effective_message.text)
        await self._send(update, context, reply)

    async def _voice(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        message = update.effective_message
        media = message.voice or message.audio
        await context.bot.send_chat_action(update.effective_chat.id, ChatAction.TYPING)
        tg_file = await media.get_file()
        audio = bytes(await tg_file.download_as_bytearray())
        filename = "voice.ogg" if message.voice else (getattr(media, "file_name", None) or "audio.mp3")
        reply = await self.core.handle_voice(str(update.effective_chat.id), audio, filename)
        await self._send(update, context, reply)

    async def _send(self, update: Update, context: ContextTypes.DEFAULT_TYPE,
                    reply: OutgoingReply) -> None:
        message = update.effective_message
        text = reply.text
        if reply.transcript:
            text = f"🎤 « {reply.transcript} »\n\n{text}"
        for part in _split(text):
            await message.reply_text(part)
        if reply.audio:
            if reply.audio_format == "ogg":
                await message.reply_voice(voice=reply.audio)
            else:
                await message.reply_audio(audio=reply.audio, filename="reponse.mp3")

    def run(self) -> None:
        log.info("Bot Telegram démarré (long polling). Utilisateurs autorisés : %s",
                 sorted(self.allowed))
        self.app.run_polling(allowed_updates=Update.ALL_TYPES, drop_pending_updates=True)
