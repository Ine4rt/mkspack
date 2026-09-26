"""Tests de l'installation : fichier .env, détection du dossier des envoyés,
conversion audio."""

from __future__ import annotations

from dotenv import dotenv_values

from mail_agent.mail.imap_client import parse_list_response, pick_sent_folder
from mail_agent.setup_wizard import write_env


def test_env_aller_retour(tmp_path):
    values = {
        "IMAP_PASSWORD": "p@ss'w\"o$rd\\${HOME}",
        "MAIL_SIGNATURE": "Bien à vous,\n\nDimitri\nIneart\nImpression & broderie textile",
        "TELEGRAM_ALLOWED_USER_IDS": "123456",
        "SIMPLE": "mot de passe avec $HOME et ${USER}",
    }
    path = tmp_path / ".env"
    write_env(values, path)
    # Chargé comme le fait l'application : sans interpolation des « $ ».
    assert dotenv_values(path, interpolate=False) == values


def test_detection_dossier_envoyes_one_com():
    data = [
        b'(\\HasNoChildren) "." "INBOX"',
        b'(\\HasNoChildren \\Sent) "." "INBOX.Sent"',
        b'(\\HasNoChildren \\Trash) "." "INBOX.Trash"',
    ]
    assert pick_sent_folder(parse_list_response(data)) == "INBOX.Sent"


def test_detection_par_nom_et_utf7():
    data = [b'(\\HasNoChildren) "/" INBOX', b'(\\HasNoChildren) "/" "&AMk-l&AOk-ments envoy&AOk-s"']
    folders = parse_list_response(data)
    assert folders[1][1] == "Éléments envoyés"
    assert pick_sent_folder(folders) == "Éléments envoyés"
    assert pick_sent_folder([({"\\hasnochildren"}, "INBOX")]) == ""


def test_conversion_note_vocale():
    import io

    import pytest

    av = pytest.importorskip("av")
    import numpy as np

    from mail_agent.voice.tts import mp3_to_ogg_opus

    buf = io.BytesIO()
    out = av.open(buf, "w", format="mp3")
    stream = out.add_stream("libmp3lame", rate=24000, layout="mono")
    samples = (np.sin(np.arange(24000) / 10) * 8000).astype(np.int16)
    for i in range(0, len(samples), 1152):
        frame = av.AudioFrame.from_ndarray(samples[i:i + 1152].reshape(1, -1), format="s16", layout="mono")
        frame.sample_rate = 24000
        for packet in stream.encode(frame):
            out.mux(packet)
    for packet in stream.encode(None):
        out.mux(packet)
    out.close()
    ogg = mp3_to_ogg_opus(buf.getvalue())
    assert ogg[:4] == b"OggS"
    assert av.open(io.BytesIO(ogg)).streams.audio[0].codec_context.name == "opus"
