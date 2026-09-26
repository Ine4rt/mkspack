"""Système de confirmation des envois.

Principe : le modèle IA ne dispose d'AUCUN outil d'envoi. Quand un brouillon
est présenté, le code « arme » une confirmation liée à ce brouillon ET à sa
version. Le message suivant de l'utilisateur est alors analysé ici, de façon
déterministe (sans IA) :

* CONFIRM  (« oui », « vas-y », « c'est bon, envoie »…) -> envoi ;
* CANCEL   (« non », « annule », « laisse tomber »…)     -> brouillon annulé ;
* HOLD     (« attends », « pas encore »…)                -> rien n'est envoyé ;
* OTHER    (tout le reste, dont « modifie… »)            -> traité par l'agent,
  aucun envoi.

Une confirmation n'est valable que pour le message qui suit IMMÉDIATEMENT la
question « Je l'envoie ? », et pendant une durée limitée : un « oui » ancien
ou répondant à autre chose n'autorise jamais un envoi.
"""

from __future__ import annotations

import re
import time
import unicodedata
from dataclasses import dataclass
from enum import Enum


class Decision(str, Enum):
    CONFIRM = "confirm"
    CANCEL = "cancel"
    HOLD = "hold"
    OTHER = "other"


def normalize(text: str) -> list[str]:
    text = unicodedata.normalize("NFKD", text.lower())
    text = "".join(c for c in text if not unicodedata.combining(c))
    text = text.replace("’", "'")
    return re.findall(r"[a-z0-9]+", text)


# Mots qui, à eux seuls, expriment un accord.
_STRONG_YES = {
    "oui", "ouais", "ouai", "yes", "ok", "okay", "okey", "daccord", "accord", "vas", "go",
    "envoie", "envoies", "envoyer", "envoyez", "envoi", "bon", "parfait", "nickel", "valide",
    "valider", "confirme", "confirmer", "impeccable", "absolument", "carrement", "evidemment",
    "exactement", "yep", "ouep", "top", "super", "allez", "allons",
}
# Mots neutres tolérés autour d'un accord (« tu peux l'envoyer », « c'est bon »…).
_FILLER = {
    "y", "le", "la", "l", "lui", "leur", "tu", "peux", "pouvez", "vous", "c", "cest", "est",
    "je", "j", "d", "ca", "ce", "cela", "mail", "email", "mel", "message", "reponse",
    "maintenant", "alors", "stp", "svp", "sil", "te", "plait", "eh", "bien", "sur",
    "merci", "tout", "de", "suite", "ah", "euh", "hmm", "oh", "donc", "ben", "bah", "si",
    "moi", "pour", "tres", "ainsi", "comme", "a", "vais", "veux", "peut", "on", "brouillon",
    "cet", "cette", "fait", "faire", "it", "send", "please",
}
# Présence d'une négation : jamais une confirmation.
_NEGATION = {"non", "no", "pas", "ne", "n", "jamais", "nan", "nope", "surtout", "aucun"}
# Intention de modifier : l'agent doit retravailler le brouillon, pas l'envoyer.
_MODIFY = {
    "modifie", "modifier", "modifies", "change", "changer", "changes", "corrige", "corriger",
    "plutot", "mais", "sauf", "ajoute", "ajouter", "enleve", "enlever", "retire", "retirer",
    "remplace", "remplacer", "reformule", "reformuler", "rajoute", "precise", "dis", "mets",
    "met", "ecris", "supprime", "raccourcis", "rallonge", "aussi", "egalement",
}
_CANCEL = {"annule", "annuler", "annules", "annulez", "annulation", "stop", "oublie", "oublier",
           "tomber", "abandonne", "abandonner", "jette"}
_HOLD = {"attends", "attend", "attendez", "patiente", "minute", "seconde", "instant", "encore",
         "maintenant", "tard", "reflechir", "hesite"}


def classify_confirmation(text: str) -> Decision:
    words = normalize(text)
    if not words:
        return Decision.OTHER
    wset = set(words)

    # 1. Demande de modification => l'agent s'en charge (même si « oui » ou « non »).
    if wset & _MODIFY:
        return Decision.OTHER
    # 2. Attente explicite.
    if wset & {"attends", "attend", "attendez", "patiente"} or (
        wset & _NEGATION and wset & _HOLD
    ):
        return Decision.HOLD
    # 3. Refus / annulation.
    if wset & _CANCEL or wset & _NEGATION:
        return Decision.CANCEL
    # 4. Accord : uniquement des mots d'accord + mots neutres, et au moins un accord fort.
    if len(words) <= 12 and wset & _STRONG_YES and wset <= (_STRONG_YES | _FILLER):
        return Decision.CONFIRM
    return Decision.OTHER


@dataclass
class PendingConfirmation:
    draft_id: str
    draft_version: int
    armed_at_turn: int
    armed_at: float

    def is_valid_for(self, turn: int, ttl_seconds: int, now: float | None = None) -> bool:
        now = time.time() if now is None else now
        return turn == self.armed_at_turn + 1 and (now - self.armed_at) <= ttl_seconds
