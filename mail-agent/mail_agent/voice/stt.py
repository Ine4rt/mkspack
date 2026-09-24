"""Speech-to-Text : transcription des messages vocaux Telegram (OGG/Opus).

Fournisseurs :
* ``openai`` : API compatible OpenAI (OpenAI, ou Groq via STT_BASE_URL) — le
  plus simple, quelques dixièmes de centime par minute ;
* ``local``  : faster-whisper sur la machine (gratuit, open source, demande
  du CPU/RAM) — installer ``pip install faster-whisper`` ;
* ``none``   : vocal désactivé.
"""

from __future__ import annotations

import io
import logging
from typing import Protocol

from ..config import VoiceSettings

log = logging.getLogger(__name__)


class SpeechToText(Protocol):
    def transcribe(self, audio: bytes, filename: str = "voice.ogg") -> str: ...


class OpenAICompatibleSTT:
    def __init__(self, settings: VoiceSettings):
        from openai import OpenAI

        self.settings = settings
        self.client = OpenAI(api_key=settings.stt_api_key or None,
                             base_url=settings.stt_base_url or None)

    def transcribe(self, audio: bytes, filename: str = "voice.ogg") -> str:
        buf = io.BytesIO(audio)
        buf.name = filename  # l'extension indique le format à l'API
        result = self.client.audio.transcriptions.create(
            model=self.settings.stt_model,
            file=buf,
            language=self.settings.stt_language or None,
            # Aide la transcription des termes du métier.
            prompt="Conversation avec un assistant e-mail : mails, devis, brouillon, envoie.",
        )
        return (getattr(result, "text", None) or str(result)).strip()


class LocalWhisperSTT:
    def __init__(self, settings: VoiceSettings):
        from faster_whisper import WhisperModel  # dépendance optionnelle

        self.settings = settings
        self.model = WhisperModel(settings.local_whisper_model, device="cpu", compute_type="int8")

    def transcribe(self, audio: bytes, filename: str = "voice.ogg") -> str:
        segments, _ = self.model.transcribe(
            io.BytesIO(audio), language=self.settings.stt_language or None, vad_filter=True
        )
        return " ".join(s.text.strip() for s in segments).strip()


def build_stt(settings: VoiceSettings) -> SpeechToText | None:
    if settings.stt_provider == "none":
        return None
    if settings.stt_provider == "local":
        return LocalWhisperSTT(settings)
    if settings.stt_provider == "openai":
        if not settings.stt_api_key:
            log.warning("STT_PROVIDER=openai mais aucune clé (OPENAI_API_KEY/STT_API_KEY) : vocal désactivé")
            return None
        return OpenAICompatibleSTT(settings)
    raise ValueError(f"STT_PROVIDER inconnu : {settings.stt_provider}")
