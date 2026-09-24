"""Boucle d'agent avec Claude (API Anthropic, outils définis par l'application)."""

from __future__ import annotations

import logging
from typing import Any, Callable

import anthropic

from ..config import LlmSettings

log = logging.getLogger(__name__)

MAX_TOOL_ROUNDS = 8
# Modèles acceptant le paramètre serveur `fallbacks` (repli automatique en cas de refus).
_FALLBACK_MODELS = {"claude-opus-5", "claude-opus-5-5", "claude-fable-5", "claude-fable-5-1"}
FALLBACK_BETA = "server-side-fallback-2026-07-01"

ToolRunner = Callable[[str, dict[str, Any]], tuple[str, bool]]


class AgentError(RuntimeError):
    pass


class ClaudeAgent:
    def __init__(self, settings: LlmSettings, client: Any | None = None):
        self.settings = settings
        self.client = client or anthropic.Anthropic(api_key=settings.api_key, max_retries=3)

    def _request(self, system: str, tools: list[dict], messages: list[dict]):
        s = self.settings
        kwargs: dict[str, Any] = dict(
            model=s.model,
            max_tokens=16000,
            system=system,
            tools=tools,
            messages=messages,
            # Met en cache le préfixe (prompt système + outils + historique).
            cache_control={"type": "ephemeral"},
        )
        if not s.model.startswith("claude-haiku"):
            kwargs["thinking"] = {"type": "adaptive"}
            kwargs["output_config"] = {"effort": s.effort}
        if s.fallbacks == "default" and s.model in _FALLBACK_MODELS:
            return self.client.beta.messages.create(
                betas=[FALLBACK_BETA], fallbacks="default", **kwargs
            )
        return self.client.messages.create(**kwargs)

    def run(self, system: str, tools: list[dict], messages: list[dict],
            run_tool: ToolRunner) -> str:
        """Fait tourner la boucle outil jusqu'à la réponse finale.

        `messages` est complété sur place (réponses du modèle + résultats
        d'outils) pour conserver le contexte d'un tour à l'autre.
        """
        for _ in range(MAX_TOOL_ROUNDS):
            try:
                response = self._request(system, tools, messages)
            except anthropic.APIConnectionError as exc:
                raise AgentError("Connexion à l'IA impossible.") from exc
            except anthropic.RateLimitError as exc:
                raise AgentError("L'IA est saturée, réessaie dans un instant.") from exc
            except anthropic.APIStatusError as exc:
                raise AgentError(f"Erreur de l'IA ({exc.status_code}).") from exc

            if response.stop_reason == "refusal":
                # Rien n'est ajouté à l'historique : le tour est abandonné proprement.
                return "Je ne peux pas traiter cette demande."

            messages.append({"role": "assistant", "content": response.content})
            tool_uses = [b for b in response.content if b.type == "tool_use"]
            if response.stop_reason != "tool_use" or not tool_uses:
                text = " ".join(b.text for b in response.content if b.type == "text").strip()
                return text or "C'est fait."

            results = []
            for block in tool_uses:
                output, is_error = run_tool(block.name, dict(block.input or {}))
                results.append({
                    "type": "tool_result", "tool_use_id": block.id,
                    "content": output, "is_error": is_error,
                })
            messages.append({"role": "user", "content": results})

        text = "Je n'ai pas réussi à terminer cette demande, peux-tu la reformuler ?"
        messages.append({"role": "assistant", "content": text})
        return text
