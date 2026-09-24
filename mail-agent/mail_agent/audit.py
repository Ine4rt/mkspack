"""Journal d'audit des actions (fichier JSON Lines, une ligne par événement).

Permet de savoir a posteriori quel mail a été consulté, quel brouillon a été
généré, quand l'envoi a été confirmé, s'il a réussi, et quand un mail a été
marqué comme lu.
"""

from __future__ import annotations

import json
import logging
import threading
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

log = logging.getLogger("mail_agent.audit")

# Événements connus (documentation ; tout autre nom reste accepté).
EMAIL_LISTED = "email_listed"
EMAIL_VIEWED = "email_viewed"
EMAIL_SEARCHED = "email_searched"
DRAFT_CREATED = "draft_created"
DRAFT_UPDATED = "draft_updated"
DRAFT_CANCELLED = "draft_cancelled"
CONFIRMATION_REQUESTED = "confirmation_requested"
SEND_CONFIRMED = "send_confirmed"
SEND_REJECTED = "send_rejected"
EMAIL_SENT = "email_sent"
SEND_FAILED = "send_failed"
SENT_COPY_FAILED = "sent_copy_failed"
MARKED_READ = "marked_read"
MARK_READ_SKIPPED = "mark_read_skipped"
UNAUTHORIZED_ACCESS = "unauthorized_access"


class AuditLog:
    def __init__(self, path: Path | None):
        self._path = path
        self._lock = threading.Lock()
        self.events: list[dict[str, Any]] = []  # copie en mémoire (utile aux tests)

    def record(self, event: str, **data: Any) -> None:
        entry = {"ts": datetime.now(timezone.utc).isoformat(), "event": event, **data}
        with self._lock:
            self.events.append(entry)
            if len(self.events) > 1000:
                del self.events[:500]
            if self._path is not None:
                with self._path.open("a", encoding="utf-8") as fh:
                    fh.write(json.dumps(entry, ensure_ascii=False, default=str) + "\n")
        log.info("%s %s", event, {k: v for k, v in data.items() if k != "body"})

    def of_type(self, event: str) -> list[dict[str, Any]]:
        return [e for e in self.events if e["event"] == event]
