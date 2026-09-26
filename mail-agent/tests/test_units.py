"""Tests unitaires : classification des confirmations, IMAP en lecture seule,
analyse MIME, cycle de vie des brouillons."""

from __future__ import annotations

from contextlib import contextmanager
from datetime import date
from unittest.mock import MagicMock

import pytest
from fakes import make_raw_email

from mail_agent.config import ImapSettings
from mail_agent.confirmation import Decision, PendingConfirmation, classify_confirmation
from mail_agent.drafts import DraftStateError, DraftStatus, DraftStore
from mail_agent.mail.imap_client import ImapMailbox, build_search_criteria, parse_fetch_response
from mail_agent.mail.models import SearchQuery
from mail_agent.mail.parsing import html_to_text, parse_email


# ------------------------------------------------------------- confirmations
@pytest.mark.parametrize("phrase", [
    "oui", "Oui.", "Ouais", "vas-y", "Vas y !", "envoie", "Envoie-le.", "tu peux l'envoyer",
    "c'est bon", "C'est bon, envoie.", "ok", "d'accord", "oui vas-y envoie", "parfait, envoie",
    "Oui, c'est bon pour moi.", "je confirme",
])
def test_confirmations(phrase):
    assert classify_confirmation(phrase) == Decision.CONFIRM


@pytest.mark.parametrize("phrase", [
    "non", "Non, laisse tomber.", "annule", "Ne l'envoie pas", "n'envoie pas", "stop",
    "oublie", "surtout pas",
])
def test_refus(phrase):
    assert classify_confirmation(phrase) == Decision.CANCEL


@pytest.mark.parametrize("phrase", ["attends", "Attends une seconde", "pas encore", "pas maintenant"])
def test_attente(phrase):
    assert classify_confirmation(phrase) == Decision.HOLD


@pytest.mark.parametrize("phrase", [
    "Non, modifie la réponse et dis plutôt que je passe jeudi",
    "oui mais change la date",
    "envoie-le aussi à Pierre",
    "Je veux changer quelque chose",
    "lis-moi mes mails",
    "oui, et ajoute que le prix est ferme",
    "",
])
def test_autres_demandes(phrase):
    assert classify_confirmation(phrase) == Decision.OTHER


def test_confirmation_valable_un_seul_tour():
    p = PendingConfirmation("d1", 1, armed_at_turn=3, armed_at=1000.0)
    assert p.is_valid_for(4, ttl_seconds=600, now=1100.0)
    assert not p.is_valid_for(5, ttl_seconds=600, now=1100.0)
    assert not p.is_valid_for(4, ttl_seconds=600, now=2000.0)


# ------------------------------------------------------------- IMAP lecture seule
def _mock_imap(monkeypatch, fetch_data):
    conn = MagicMock()
    conn.select.return_value = ("OK", [b"1"])
    conn.uid.side_effect = lambda cmd, *a: (
        ("OK", [b"101"]) if cmd == "SEARCH" else ("OK", fetch_data) if cmd == "FETCH" else ("OK", [b""])
    )
    mailbox = ImapMailbox(ImapSettings("imap.test", 993, "u", "p"))

    @contextmanager
    def fake_connect():
        yield conn

    monkeypatch.setattr(mailbox, "_connect", fake_connect)
    return mailbox, conn


def test_imap_lecture_en_examine_et_peek(monkeypatch):
    raw = make_raw_email(sender="a@b.test", subject="S", body="B", message_id="<m@b>")
    data = [(b"1 (UID 101 FLAGS () BODY[] {%d}" % len(raw), raw), b")"]
    mailbox, conn = _mock_imap(monkeypatch, data)

    emails = mailbox.search(SearchQuery(unread_only=True), limit=5)
    assert emails[0].is_unread and emails[0].subject == "S"
    mailbox.fetch("INBOX", "101")

    for call in conn.select.call_args_list:
        assert call.kwargs.get("readonly") is True  # EXAMINE
    commands = [c.args for c in conn.uid.call_args_list]
    assert all(args[0] != "STORE" for args in commands)
    for args in commands:
        if args[0] == "FETCH":
            assert "BODY.PEEK[]" in args[2]


def test_imap_marquage_seulement_via_mark_replied(monkeypatch):
    header = b"Message-ID: <m@b>\r\n\r\n"
    data = [(b"1 (UID 101 BODY[HEADER.FIELDS (MESSAGE-ID)] {%d}" % len(header), header), b")"]
    mailbox, conn = _mock_imap(monkeypatch, data)
    assert mailbox.mark_replied("INBOX", "101", "<m@b>") is True
    conn.uid.assert_any_call("STORE", "101", "+FLAGS", "(\\Seen \\Answered)")
    # Message-ID différent (UID réattribué) : aucun marquage.
    conn.uid.reset_mock()
    assert mailbox.mark_replied("INBOX", "101", "<autre@b>") is False
    assert all(c.args[0] != "STORE" for c in conn.uid.call_args_list)


def test_criteres_de_recherche():
    q = SearchQuery(from_="Jean", subject="devis", since=date(2026, 9, 21), unread_only=True)
    assert build_search_criteria(q, utf8_mode=False) == [
        "FROM", '"Jean"', "SUBJECT", '"devis"', "SINCE", "21-Sep-2026", "UNSEEN"]
    accent = build_search_criteria(SearchQuery(subject="brodé"), utf8_mode=False)
    assert accent[:2] == ["CHARSET", "UTF-8"] and accent[-1] == '"brodé"'.encode()
    assert build_search_criteria(SearchQuery(), utf8_mode=True) == ["ALL"]


def test_parse_fetch_flags_apres_litteral():
    data = [(b"1 (UID 7 BODY[] {3}", b"abc"), b" FLAGS (\\Seen))"]
    assert parse_fetch_response(data) == [("7", {"\\Seen"}, b"abc")]


# ------------------------------------------------------------- analyse MIME
def test_pieces_jointes_detectees():
    raw = make_raw_email(sender="Client <c@x.test>", subject="Commande", body="Voir PDF",
                         message_id="<a@x>", attachment=("devis.pdf", b"%PDF-1.4" * 200, "application/pdf"))
    email = parse_email(raw, uid="1", mailbox="INBOX", flags={"\\Seen"})
    assert not email.is_unread
    assert [a.filename for a in email.attachments] == ["devis.pdf"]
    assert "PDF" in email.attachments[0].describe()


def test_html_vers_texte():
    assert html_to_text("<p>Bonjour</p><style>x{}</style><p>Merci&nbsp;!</p>") == "Bonjour\n\nMerci !"


# ------------------------------------------------------------- brouillons
def test_cycle_de_vie_brouillon():
    store = DraftStore(":memory:")
    d = store.create(chat_id="1", to=["a@b.c"], to_name="", subject="S", body="B")
    with pytest.raises(DraftStateError):
        store.transition(d, DraftStatus.SENT)  # impossible de sauter la confirmation
    store.update_content(d, body="B2")
    assert store.get(d.id).version == 2
    store.transition(d, DraftStatus.CANCELLED)
    with pytest.raises(DraftStateError):
        store.update_content(d, body="B3")
