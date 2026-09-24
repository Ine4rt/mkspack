"""Envoi SMTP. Toute anomalie lève une exception : l'appelant considère alors
l'envoi comme échoué et ne marque surtout pas le mail d'origine comme lu."""

from __future__ import annotations

import smtplib
import ssl
from email.message import EmailMessage
from typing import Protocol

from ..config import SmtpSettings


class SendError(RuntimeError):
    pass


class MailSender(Protocol):
    def send(self, message: EmailMessage) -> None: ...


class SmtpSender:
    def __init__(self, settings: SmtpSettings):
        self.settings = settings

    def send(self, message: EmailMessage) -> None:
        s = self.settings
        context = ssl.create_default_context()
        try:
            if s.security == "ssl":
                server: smtplib.SMTP = smtplib.SMTP_SSL(s.host, s.port, context=context, timeout=30)
            else:
                server = smtplib.SMTP(s.host, s.port, timeout=30)
            with server:
                if s.security == "starttls":
                    server.starttls(context=context)
                if s.user:
                    server.login(s.user, s.password)
                refused = server.send_message(message)
        except (smtplib.SMTPException, OSError) as exc:
            raise SendError(f"Échec SMTP : {exc}") from exc
        if refused:
            # Au moins un destinataire refusé : on considère l'envoi comme échoué.
            raise SendError(f"Destinataires refusés : {', '.join(refused)}")
