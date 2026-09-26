"""Envoi SMTP. Toute anomalie lève une exception : l'appelant considère alors
l'envoi comme échoué et ne marque surtout pas le mail d'origine comme lu."""

from __future__ import annotations

import smtplib
import ssl
from contextlib import contextmanager
from email.message import EmailMessage
from typing import Iterator, Protocol

from ..config import SmtpSettings


class SendError(RuntimeError):
    pass


class MailSender(Protocol):
    def send(self, message: EmailMessage) -> None: ...


class SmtpSender:
    def __init__(self, settings: SmtpSettings):
        self.settings = settings

    @contextmanager
    def _session(self) -> Iterator[smtplib.SMTP]:
        """Connexion chiffrée et identifiée ; convertit toute erreur en SendError."""
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
                yield server
        except (smtplib.SMTPException, OSError) as exc:
            raise SendError(f"Échec SMTP : {exc}") from exc

    def send(self, message: EmailMessage) -> None:
        with self._session() as server:
            refused = server.send_message(message)
        if refused:
            # Au moins un destinataire refusé : on considère l'envoi comme échoué.
            raise SendError(f"Destinataires refusés : {', '.join(refused)}")

    def test_login(self) -> None:
        """Vérifie la connexion et l'identification SMTP, sans rien envoyer."""
        with self._session():
            pass
