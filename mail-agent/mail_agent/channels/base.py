"""Logique commune à tous les canaux de messagerie (Telegram, WhatsApp…).

Un canal se limite à : recevoir texte/vocal, appeler `ChannelCore`, renvoyer
le texte et éventuellement la note vocale. Ajouter WhatsApp revient à écrire
un nouvel adaptateur de ~100 lignes sans toucher au reste.
"""

from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass

from ..assistant import Assistant, Reply, to_speech
from ..voice.stt import SpeechToText
from ..voice.tts import TextToSpeech

log = logging.getLogger(__name__)


@dataclass
class OutgoingReply:
    text: str
    audio: bytes | None = None
    audio_format: str | None = None  # "ogg" (note vocale) ou "mp3"
    transcript: str | None = None    # ce qui a été compris du vocal


class ChannelCore:
    def __init__(self, assistant: Assistant, stt: SpeechToText | None,
                 tts: TextToSpeech | None, reply_mode: str = "voice_if_voice"):
        self.assistant = assistant
        self.stt = stt
        self.tts = tts
        self.reply_mode = reply_mode

    async def handle_text(self, chat_id: str, text: str, *, from_voice: bool = False) -> OutgoingReply:
        reply: Reply = await asyncio.to_thread(self.assistant.handle_message, chat_id, text)
        out = OutgoingReply(text=reply.text)
        want_voice = self.reply_mode == "always" or (self.reply_mode == "voice_if_voice" and from_voice)
        if self.tts and want_voice:
            try:
                out.audio, out.audio_format = await asyncio.to_thread(
                    self.tts.synthesize, to_speech(reply.speech)
                )
            except Exception:
                log.exception("Synthèse vocale impossible, réponse texte seule")
        return out

    async def handle_voice(self, chat_id: str, audio: bytes, filename: str = "voice.ogg") -> OutgoingReply:
        if self.stt is None:
            return OutgoingReply("Les messages vocaux ne sont pas configurés (STT_PROVIDER).")
        try:
            transcript = await asyncio.to_thread(self.stt.transcribe, audio, filename)
        except Exception:
            log.exception("Transcription impossible")
            return OutgoingReply("Je n'ai pas réussi à transcrire ton message vocal, peux-tu répéter ?")
        if not transcript:
            return OutgoingReply("Je n'ai rien entendu, peux-tu répéter ?")
        out = await self.handle_text(chat_id, transcript, from_voice=True)
        out.transcript = transcript
        return out
