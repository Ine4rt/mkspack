"""Text-to-Speech : génère une note vocale OGG/Opus (format « voice » Telegram).

Fournisseurs :
* ``openai`` : API OpenAI (sortie Opus native, aucune dépendance système) ;
* ``edge``   : voix neuronales Microsoft Edge via ``edge-tts`` (gratuit, mais
  service non officiel ; conversion MP3 → OGG par ``ffmpeg``) ;
* ``none``   : réponses texte uniquement.
"""

from __future__ import annotations

import asyncio
import logging
import shutil
import subprocess
from typing import Protocol

from ..config import VoiceSettings

log = logging.getLogger(__name__)

MAX_TTS_CHARS = 3500


class TextToSpeech(Protocol):
    def synthesize(self, text: str) -> tuple[bytes, str]:
        """Retourne (audio, format) où format vaut "ogg" (note vocale) ou "mp3"."""
        ...


def _clip(text: str) -> str:
    if len(text) <= MAX_TTS_CHARS:
        return text
    return text[:MAX_TTS_CHARS].rsplit(" ", 1)[0] + "… La suite est dans le message écrit."


class OpenAITTS:
    def __init__(self, settings: VoiceSettings):
        from openai import OpenAI

        self.settings = settings
        self.client = OpenAI(api_key=settings.tts_api_key or None,
                             base_url=settings.tts_base_url or None)

    def synthesize(self, text: str) -> tuple[bytes, str]:
        response = self.client.audio.speech.create(
            model=self.settings.tts_model,
            voice=self.settings.tts_voice,
            input=_clip(text),
            instructions="Parle en français, d'un ton naturel, clair et posé, à un rythme soutenu.",
            response_format="opus",
        )
        return response.read(), "ogg"


class EdgeTTS:
    def __init__(self, settings: VoiceSettings):
        import edge_tts  # noqa: F401  (vérifie la présence de la dépendance)

        self.settings = settings
        self.ffmpeg = shutil.which("ffmpeg")
        if not self.ffmpeg:
            log.warning("ffmpeg absent : les réponses vocales seront envoyées en MP3 (fichier audio)")

    async def _mp3(self, text: str) -> bytes:
        import edge_tts

        chunks = []
        async for chunk in edge_tts.Communicate(_clip(text), self.settings.edge_tts_voice).stream():
            if chunk["type"] == "audio":
                chunks.append(chunk["data"])
        return b"".join(chunks)

    def synthesize(self, text: str) -> tuple[bytes, str]:
        mp3 = asyncio.run(self._mp3(text))  # appelé depuis un thread de travail
        if not self.ffmpeg:
            return mp3, "mp3"
        proc = subprocess.run(
            [self.ffmpeg, "-loglevel", "error", "-i", "pipe:0", "-c:a", "libopus",
             "-b:a", "32k", "-f", "ogg", "pipe:1"],
            input=mp3, capture_output=True, check=True, timeout=60,
        )
        return proc.stdout, "ogg"


def build_tts(settings: VoiceSettings) -> TextToSpeech | None:
    if settings.tts_provider == "none" or settings.reply_mode == "never":
        return None
    if settings.tts_provider == "edge":
        return EdgeTTS(settings)
    if settings.tts_provider == "openai":
        if not settings.tts_api_key:
            log.warning("TTS_PROVIDER=openai mais aucune clé : réponses vocales désactivées")
            return None
        return OpenAITTS(settings)
    raise ValueError(f"TTS_PROVIDER inconnu : {settings.tts_provider}")
