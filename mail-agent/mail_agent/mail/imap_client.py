"""Accès IMAP à la boîte de réception.

RÈGLE FONDAMENTALE : la consultation ne doit JAMAIS faire passer un mail en
« lu ». Deux protections indépendantes sont appliquées à toute lecture :

1. la boîte est ouverte avec EXAMINE (`select(readonly=True)`) : le serveur
   interdit alors toute modification de drapeaux pendant la session ;
2. le contenu est récupéré avec `BODY.PEEK[]` et jamais `BODY[]`/`RFC822`,
   qui positionnent implicitement le drapeau \\Seen.

La seule méthode qui modifie des drapeaux est `mark_replied`, appelée
exclusivement par `MailService` après un envoi SMTP réussi.
"""

from __future__ import annotations

import imaplib
import logging
import re
import ssl
import time
from contextlib import contextmanager
from datetime import date
from typing import Iterator, Protocol

from ..config import ImapSettings
from .models import Email, SearchQuery
from .parsing import parse_email

log = logging.getLogger(__name__)

# Seules commandes FETCH autorisées en lecture : toutes en PEEK.
FETCH_FULL = "(UID FLAGS BODY.PEEK[])"
FETCH_MESSAGE_ID = "(UID BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])"
MAX_RESULTS = 25

_MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]


class MailboxError(RuntimeError):
    pass


class MailboxBackend(Protocol):
    """Interface commune (IMAP réel ou faux serveur de test)."""

    def search(self, query: SearchQuery, limit: int = 10) -> list[Email]: ...

    def fetch(self, mailbox: str, uid: str) -> Email | None: ...

    def mark_replied(self, mailbox: str, uid: str, expected_message_id: str) -> bool: ...

    def append_to_folder(self, folder: str, raw: bytes) -> None: ...


def imap_date(d: date) -> str:
    return f"{d.day:02d}-{_MONTHS[d.month - 1]}-{d.year}"


def quote_mailbox(name: str) -> str:
    return '"' + name.replace("\\", "\\\\").replace('"', '\\"') + '"'


def _quote_value(value: str, utf8_mode: bool) -> str | bytes:
    escaped = value.replace("\\", "\\\\").replace('"', '\\"')
    if utf8_mode or value.isascii():
        return f'"{escaped}"'
    return b'"' + escaped.encode("utf-8") + b'"'


def build_search_criteria(query: SearchQuery, utf8_mode: bool) -> list[str | bytes]:
    criteria: list[str | bytes] = []
    for key, value in (
        ("FROM", query.from_),
        ("TO", query.to),
        ("SUBJECT", query.subject),
        ("TEXT", query.text),
    ):
        if value:
            criteria += [key, _quote_value(value.strip(), utf8_mode)]
    if query.since:
        criteria += ["SINCE", imap_date(query.since)]
    if query.before:
        criteria += ["BEFORE", imap_date(query.before)]
    if query.unread_only:
        criteria.append("UNSEEN")
    criteria = criteria or ["ALL"]
    needs_charset = not utf8_mode and any(isinstance(c, bytes) for c in criteria)
    return (["CHARSET", "UTF-8"] if needs_charset else []) + criteria


_UID_RE = re.compile(rb"UID (\d+)")
_FLAGS_RE = re.compile(rb"FLAGS \(([^)]*)\)")


def parse_fetch_response(data: list) -> list[tuple[str, set[str], bytes]]:
    """Retourne [(uid, flags, contenu brut)] depuis une réponse FETCH imaplib."""
    results = []
    for i, item in enumerate(data):
        if not isinstance(item, tuple):
            continue
        meta = item[0]
        # Certains serveurs placent FLAGS après le littéral : on regarde la suite.
        if i + 1 < len(data) and isinstance(data[i + 1], bytes):
            meta += data[i + 1]
        uid_m = _UID_RE.search(meta)
        if not uid_m:
            continue
        flags_m = _FLAGS_RE.search(meta)
        flags = set(flags_m.group(1).decode().split()) if flags_m else set()
        results.append((uid_m.group(1).decode(), flags, item[1]))
    return results


class ImapMailbox:
    def __init__(self, settings: ImapSettings):
        self.settings = settings
        self._utf8 = False

    # --- connexion ---------------------------------------------------------
    @contextmanager
    def _connect(self) -> Iterator[imaplib.IMAP4]:
        s = self.settings
        if s.use_ssl:
            conn: imaplib.IMAP4 = imaplib.IMAP4_SSL(
                s.host, s.port, ssl_context=ssl.create_default_context(), timeout=30
            )
        else:
            conn = imaplib.IMAP4(s.host, s.port, timeout=30)
            conn.starttls(ssl_context=ssl.create_default_context())
        try:
            conn.login(s.user, s.password)
            self._utf8 = False
            if "UTF8=ACCEPT" in getattr(conn, "capabilities", ()):
                try:
                    conn.enable("UTF8=ACCEPT")
                    self._utf8 = True
                except imaplib.IMAP4.error:
                    pass
            yield conn
        finally:
            try:
                conn.logout()
            except Exception:
                pass

    @staticmethod
    def _examine(conn: imaplib.IMAP4, mailbox: str) -> None:
        """Ouvre la boîte en LECTURE SEULE (commande IMAP EXAMINE)."""
        typ, data = conn.select(quote_mailbox(mailbox), readonly=True)
        if typ != "OK":
            raise MailboxError(f"Impossible d'ouvrir {mailbox} : {data!r}")

    def _fetch_uids(self, conn: imaplib.IMAP4, mailbox: str, uids: list[str]) -> list[Email]:
        if not uids:
            return []
        typ, data = conn.uid("FETCH", ",".join(uids), FETCH_FULL)
        if typ != "OK":
            raise MailboxError(f"FETCH a échoué : {data!r}")
        emails = [
            parse_email(raw, uid=uid, mailbox=mailbox, flags=flags)
            for uid, flags, raw in parse_fetch_response(data)
        ]
        order = {uid: i for i, uid in enumerate(uids)}
        return sorted(emails, key=lambda e: order.get(e.uid, 0))

    # --- lecture (jamais de modification de drapeaux) -----------------------
    def search(self, query: SearchQuery, limit: int = 10) -> list[Email]:
        mailbox = query.mailbox or self.settings.inbox
        limit = max(1, min(limit, MAX_RESULTS))
        with self._connect() as conn:
            self._examine(conn, mailbox)
            typ, data = conn.uid("SEARCH", *build_search_criteria(query, self._utf8))
            if typ != "OK":
                raise MailboxError(f"SEARCH a échoué : {data!r}")
            uids = sorted((data[0] or b"").split(), key=int)
            newest_first = [u.decode() for u in reversed(uids)][:limit]
            return self._fetch_uids(conn, mailbox, newest_first)

    def fetch(self, mailbox: str, uid: str) -> Email | None:
        with self._connect() as conn:
            self._examine(conn, mailbox)
            emails = self._fetch_uids(conn, mailbox, [uid])
            return emails[0] if emails else None

    # --- écriture : réservée au flux d'envoi confirmé -----------------------
    def mark_replied(self, mailbox: str, uid: str, expected_message_id: str) -> bool:
        """Marque le mail d'origine \\Seen + \\Answered.

        Ne doit être appelée que par MailService après un envoi réussi.
        Vérifie d'abord que l'UID désigne toujours le même message
        (Message-ID identique) pour ne jamais marquer le mauvais mail.
        """
        with self._connect() as conn:
            typ, data = conn.select(quote_mailbox(mailbox), readonly=False)
            if typ != "OK":
                raise MailboxError(f"Impossible d'ouvrir {mailbox} en écriture : {data!r}")
            typ, data = conn.uid("FETCH", uid, FETCH_MESSAGE_ID)
            found = parse_fetch_response(data) if typ == "OK" else []
            if not found:
                log.warning("Mail uid=%s introuvable : pas de marquage", uid)
                return False
            header = found[0][2].decode("utf-8", errors="replace")
            if expected_message_id and expected_message_id not in header:
                log.warning("Message-ID différent pour uid=%s : pas de marquage", uid)
                return False
            typ, data = conn.uid("STORE", uid, "+FLAGS", "(\\Seen \\Answered)")
            return typ == "OK"

    def append_to_folder(self, folder: str, raw: bytes) -> None:
        with self._connect() as conn:
            typ, data = conn.append(
                quote_mailbox(folder), "(\\Seen)", imaplib.Time2Internaldate(time.time()), raw
            )
            if typ != "OK":
                raise MailboxError(f"APPEND a échoué : {data!r}")
