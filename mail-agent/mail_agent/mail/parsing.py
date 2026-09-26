"""Conversion d'un message MIME brut en objet `Email` lisible."""

from __future__ import annotations

import html
import re
from email import policy
from email.message import EmailMessage
from email.parser import BytesParser
from email.utils import getaddresses, parseaddr, parsedate_to_datetime
from html.parser import HTMLParser

from .models import Attachment, Email

MAX_BODY_CHARS = 20_000


class _HtmlToText(HTMLParser):
    _BLOCK = {"p", "div", "br", "tr", "li", "h1", "h2", "h3", "h4", "table", "blockquote"}

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.parts: list[str] = []
        self._skip = 0

    def handle_starttag(self, tag, attrs):
        if tag in {"script", "style", "head"}:
            self._skip += 1
        elif tag in self._BLOCK:
            self.parts.append("\n")

    def handle_endtag(self, tag):
        if tag in {"script", "style", "head"} and self._skip:
            self._skip -= 1
        elif tag in self._BLOCK:
            self.parts.append("\n")

    def handle_data(self, data):
        if not self._skip:
            self.parts.append(data)


def html_to_text(markup: str) -> str:
    parser = _HtmlToText()
    try:
        parser.feed(markup)
        parser.close()
        text = "".join(parser.parts)
    except Exception:  # HTML très cassé : on retire grossièrement les balises
        text = html.unescape(re.sub(r"<[^>]+>", " ", markup))
    text = re.sub(r"[ \t\xa0]+", " ", text)
    return re.sub(r"\n\s*\n+", "\n\n", text).strip()


def _decode_part(part: EmailMessage) -> str:
    try:
        return part.get_content()
    except Exception:
        payload = part.get_payload(decode=True) or b""
        return payload.decode(part.get_content_charset() or "utf-8", errors="replace")


def extract_body(msg: EmailMessage) -> str:
    body = msg.get_body(preferencelist=("plain", "html"))
    if body is None:
        return ""
    text = _decode_part(body)
    if body.get_content_type() == "text/html":
        text = html_to_text(text)
    text = text.replace("\r\n", "\n").strip()
    if len(text) > MAX_BODY_CHARS:
        text = text[:MAX_BODY_CHARS] + "\n[… message tronqué …]"
    return text


def extract_attachments(msg: EmailMessage) -> list[Attachment]:
    found: list[Attachment] = []
    for part in msg.walk():
        if part.is_multipart():
            continue
        disposition = part.get_content_disposition()
        filename = part.get_filename()
        if disposition == "attachment" or (disposition == "inline" and filename) or (
            filename and not part.get_content_type().startswith("text/")
        ):
            payload = part.get_payload(decode=True) or b""
            found.append(Attachment(filename or "", part.get_content_type(), len(payload)))
    return found


def parse_email(raw: bytes, *, uid: str, mailbox: str, flags: set[str]) -> Email:
    msg: EmailMessage = BytesParser(policy=policy.default).parsebytes(raw)  # type: ignore[assignment]
    from_name, from_addr = parseaddr(str(msg.get("From", "")))
    _, reply_to = parseaddr(str(msg.get("Reply-To", "")))
    date = None
    if msg.get("Date"):
        try:
            date = parsedate_to_datetime(str(msg["Date"]))
        except (TypeError, ValueError):
            date = None
    flags_lower = {f.lower() for f in flags}
    return Email(
        uid=uid,
        mailbox=mailbox,
        message_id=str(msg.get("Message-ID", "")).strip(),
        subject=str(msg.get("Subject", "")).strip() or "(sans objet)",
        from_name=from_name,
        from_addr=from_addr,
        reply_to=reply_to,
        to=[addr for _, addr in getaddresses([str(v) for v in msg.get_all("To", [])]) if addr],
        cc=[addr for _, addr in getaddresses([str(v) for v in msg.get_all("Cc", [])]) if addr],
        date=date,
        is_unread="\\seen" not in flags_lower,
        is_answered="\\answered" in flags_lower,
        body_text=extract_body(msg),
        attachments=extract_attachments(msg),
        references=" ".join(str(msg.get("References", "")).split()),
        in_reply_to=str(msg.get("In-Reply-To", "")).strip(),
    )
