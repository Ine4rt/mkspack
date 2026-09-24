# Assistant e-mail vocal (Telegram)

Assistant personnel qui lit, résume, recherche vos e-mails et prépare vos
réponses, piloté à la voix depuis Telegram. **Il ne décide jamais à votre
place** : aucun mail ne part sans votre « oui » explicite, et un mail reste
**non lu** tant que vous n'y avez pas réellement répondu.

```
Vous  🎤 « Lis-moi mes mails non lus. »
Agent 🔊 « Tu as deux nouveaux mails. Le premier vient de Jean Dupont, à propos
          des sweats : il demande un devis pour vingt pièces… »
Vous  🎤 « Réponds-lui que je lui prépare le devis demain. »
Agent 🔊 « Voici ce que je propose. Réponse à Jean Dupont. Bonjour Jean, merci
          pour votre message, je vous prépare le devis demain… Je l'envoie ? »
Vous  🎤 « C'est bon, envoie. »
Agent 🔊 « C'est envoyé à Jean Dupont. Le mail d'origine est maintenant marqué comme lu. »
```

L'analyse, l'architecture et les choix techniques sont détaillés dans
[ARCHITECTURE.md](ARCHITECTURE.md).

## Ce que fait le MVP

- Bot Telegram : messages texte et vocaux, réponse texte + note vocale.
- Lecture des mails non lus / derniers mails, résumé, lecture complète, pièces jointes signalées.
- Recherche en langage naturel (expéditeur, objet, mot-clé, période, non lus).
- Contexte de conversation : « réponds-lui », « le premier », « ce client ».
- Brouillons de réponse (et nouveaux messages), modification, annulation.
- Envoi **uniquement** après confirmation (« oui », « vas-y », « envoie », « c'est bon »).
- Mail d'origine marqué lu (et « répondu ») **seulement** après un envoi réussi.
- Journal d'audit (`data/audit.jsonl`).
- Signature configurable.

## Structure

```
mail-agent/
├── mail_agent/
│   ├── __main__.py          # démarrage (bot Telegram ou --console)
│   ├── config.py            # configuration par variables d'environnement
│   ├── assistant.py         # orchestrateur indépendant du canal
│   ├── confirmation.py      # « oui / non / attends » → décision, sans IA
│   ├── drafts.py            # brouillons (SQLite) et leurs états
│   ├── audit.py             # journal des actions
│   ├── agent/
│   │   ├── llm.py           # boucle d'outils avec Claude
│   │   ├── tools.py         # outils exposés à l'IA (lecture + brouillons uniquement)
│   │   ├── prompts.py       # prompt système (style vocal, sécurité)
│   │   └── session.py       # mémoire de conversation
│   ├── mail/
│   │   ├── imap_client.py   # IMAP en lecture seule (EXAMINE + BODY.PEEK)
│   │   ├── smtp_sender.py   # envoi SMTP
│   │   ├── parsing.py       # MIME → texte, pièces jointes
│   │   ├── models.py
│   │   └── service.py       # règles : envoi confirmé, marquage lu après envoi
│   ├── voice/
│   │   ├── stt.py           # transcription (OpenAI / Groq / faster-whisper)
│   │   └── tts.py           # synthèse vocale (OpenAI / edge-tts)
│   └── channels/
│       ├── base.py          # logique commune des canaux
│       └── telegram_bot.py  # adaptateur Telegram (WhatsApp : même principe)
├── tests/                   # 60 tests (pytest), sans réseau
├── .env.example
├── Dockerfile / docker-compose.yml / deploy/mail-agent.service
└── ARCHITECTURE.md
```

## Installation

### 1. Prérequis

- Python 3.10 ou plus récent (ou Docker).
- Une clé API Anthropic : <https://console.anthropic.com> → *API Keys*.
- Une clé API OpenAI pour la voix (<https://platform.openai.com/api-keys>) —
  ou Groq pour la transcription, ou les options gratuites (`faster-whisper`
  local, `edge-tts`).
- Les paramètres IMAP/SMTP de votre boîte mail.

### 2. Créer le bot Telegram

1. Dans Telegram, ouvrez **@BotFather** → `/newbot` → choisissez un nom et un
   identifiant (finissant par `bot`). Copiez le **token** → `TELEGRAM_BOT_TOKEN`.
2. Ouvrez **@userinfobot** → il vous donne votre **identifiant numérique** →
   `TELEGRAM_ALLOWED_USER_IDS`. Seuls ces identifiants pourront utiliser le bot.
3. (Optionnel) chez @BotFather : `/setprivacy` → *Enable*, `/setjoingroups` → *Disable*.

### 3. Configurer la boîte mail

| Fournisseur | IMAP | SMTP | Remarques |
|---|---|---|---|
| Gmail / Google Workspace | `imap.gmail.com:993` | `smtp.gmail.com:465` (ssl) | Activez la validation en 2 étapes puis créez un **mot de passe d'application** (<https://myaccount.google.com/apppasswords>). Laissez `IMAP_SENT_FOLDER` vide. |
| OVH (Exchange/MX Plan) | `ssl0.ovh.net:993` | `ssl0.ovh.net:465` (ssl) | `IMAP_SENT_FOLDER=Sent` (ou `INBOX.Sent`) |
| Infomaniak | `mail.infomaniak.com:993` | `mail.infomaniak.com:465` (ssl) | `IMAP_SENT_FOLDER=Sent` |
| Outlook.com / Microsoft 365 | — | — | L'authentification par mot de passe est généralement désactivée (OAuth2 requis) : non supporté dans le MVP |

Pour connaître le nom exact du dossier des envoyés, regardez-le dans votre
webmail ; le serveur l'appelle souvent `Sent`, `INBOX.Sent` ou `Éléments envoyés`.

### 4. Installer et configurer

```bash
cd mail-agent
python3 -m venv .venv
source .venv/bin/activate          # Windows : .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
# éditez .env (token, identifiant Telegram, clés API, IMAP/SMTP, signature)
```

Toutes les variables sont décrites dans [.env.example](.env.example). Aucun
secret ne doit être écrit dans le code ; `.env` est exclu de git.

### 5. Lancer en local

```bash
# Test sans Telegram, dans le terminal (texte uniquement)
python -m mail_agent --console

# Bot Telegram
python -m mail_agent
```

Écrivez ou envoyez un vocal à votre bot : « Lis-moi mes mails non lus ».
`/aide` affiche des exemples, `/reset` efface le contexte.

## Lancement en production

Le bot fonctionne en *long polling* : aucun port à ouvrir, aucun nom de domaine.
Un petit VPS (1 vCPU, 1 Go de RAM) suffit. **Une seule instance** doit tourner
avec un même token.

### Avec Docker

```bash
cp .env.example .env   # puis compléter
mkdir -p data && sudo chown 1000:1000 data
docker compose up -d --build
docker compose logs -f
```

### Avec systemd

```bash
sudo useradd --system --home /opt/mail-agent mailagent
sudo mkdir -p /opt/mail-agent && sudo cp -r . /opt/mail-agent && cd /opt/mail-agent
sudo python3 -m venv .venv && sudo .venv/bin/pip install -r requirements.txt
sudo cp .env.example .env && sudo nano .env && sudo chmod 600 .env
sudo chown -R mailagent /opt/mail-agent
sudo cp deploy/mail-agent.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now mail-agent
journalctl -u mail-agent -f
```

## Tests

```bash
pip install -r requirements-dev.txt
python -m pytest
```

Les tests n'utilisent ni réseau ni vraie boîte mail (faux serveur IMAP/SMTP et
faux modèle IA scriptés). Ils vérifient notamment :

- un mail non lu reste non lu après lecture et après création d'un brouillon ;
- la lecture IMAP utilise `EXAMINE` et `BODY.PEEK[]`, jamais `STORE` ;
- aucun mail n'est envoyé sans confirmation, l'IA n'a aucun outil d'envoi ;
- « annule », « non », « attends » empêchent l'envoi ;
- un « oui » ancien, expiré, ou venant d'une autre conversation n'envoie rien ;
- un brouillon modifié exige une nouvelle confirmation ;
- le mail est marqué lu uniquement après un envoi réussi ; un échec le laisse non lu ;
- les instructions malveillantes d'un mail ne sont pas exécutées (et même une IA
  manipulée ne peut rien envoyer) ;
- le contexte est conservé (« réponds-lui » après « lis-moi le mail de Jean »).

### Procédure de test manuelle (recommandée avant usage réel)

1. Depuis une autre adresse, envoyez-vous un mail de test. Vérifiez dans votre
   webmail qu'il est **non lu**.
2. Au bot : « Lis-moi mes mails non lus » → il le lit. Vérifiez dans le webmail :
   toujours **non lu**.
3. « Réponds-lui que c'est noté » → brouillon affiché + « Je l'envoie ? ».
   Répondez « non » → rien n'est envoyé, toujours **non lu**.
4. Recommencez, répondez « oui » → la réponse arrive sur l'autre adresse, et le
   mail d'origine passe **lu** (et « répondu »).
5. Consultez `data/audit.jsonl` pour voir la trace complète.

## Journal d'audit

Chaque ligne de `data/audit.jsonl` est un événement JSON :
`email_listed`, `email_searched`, `email_viewed`, `draft_created`,
`draft_updated`, `draft_cancelled`, `confirmation_requested`, `send_confirmed`,
`email_sent`, `send_failed`, `sent_copy_failed`, `marked_read`,
`mark_read_skipped`, `send_rejected`, `unauthorized_access`.

```bash
jq -c 'select(.event=="email_sent" or .event=="marked_read")' data/audit.jsonl
```

## Coûts et réglages

- `LLM_MODEL=claude-opus-5` (défaut) donne les meilleures réponses ; passez à
  `claude-sonnet-5` ou `claude-haiku-4-5` pour réduire la facture, et
  `LLM_EFFORT=low` pour des réponses plus rapides.
- Voix : `gpt-4o-mini-transcribe` + `gpt-4o-mini-tts` coûtent quelques centimes
  par jour. Pour 0 € : `STT_PROVIDER=local` (`pip install faster-whisper`) et
  `TTS_PROVIDER=edge` (nécessite `ffmpeg`).

## Limites connues du MVP

- Pas d'OAuth2 (Gmail fonctionne avec un mot de passe d'application ; Microsoft non).
- Pièces jointes détectées et décrites, mais pas encore analysées.
- Brouillons stockés localement (pas encore dans le dossier « Brouillons » du serveur).
- Mémoire de conversation en RAM (perdue au redémarrage ; les brouillons restent).
- Une réponse acceptée par le serveur SMTP peut encore rebondir ensuite.
