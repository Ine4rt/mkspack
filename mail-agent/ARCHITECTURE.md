# Architecture et choix techniques

Ce document résume l'analyse faite avant de coder : le besoin, l'architecture
retenue pour le MVP, les choix techniques et les pièges connus.

## 1. Analyse du besoin

| Exigence | Conséquence technique |
|---|---|
| Piloté à la voix, en voiture, depuis le téléphone | Telegram (notes vocales natives, lecture enchaînée) + STT + TTS ; réponses courtes, sans markdown |
| Assistant, pas agent autonome | L'IA **prépare** ; seul l'utilisateur **déclenche** un envoi |
| Un mail reste NON LU tant qu'on n'y a pas répondu | Lecture IMAP strictement non destructive ; marquage « lu » uniquement après envoi SMTP réussi |
| Confirmations vocales simples (« oui », « envoie ») | Classification déterministe, liée à UNE action précise et à UN tour de parole |
| Comprendre « réponds-lui », « le premier » | Mémoire de conversation + identifiants courts de mails (m1, m2…) |
| Protection contre les prompt injections | Défense structurelle : l'IA n'a pas d'outil d'envoi, le contenu des mails est balisé comme non fiable |
| WhatsApp plus tard | Cœur indépendant du canal ; Telegram n'est qu'un adaptateur |
| Simple, peu coûteux, maintenable | Python, bibliothèque standard pour IMAP/SMTP/SQLite, 5 dépendances, pas de serveur web |

## 2. Architecture

```
 Téléphone (Telegram)
        │  texte / note vocale (OGG Opus)
        ▼
┌─────────────────────┐   channels/telegram_bot.py   (adaptateur ; WhatsApp = autre adaptateur)
│  Canal              │   channels/base.py           (logique commune : STT → assistant → TTS)
└─────────┬───────────┘
          │ voice/stt.py  (OpenAI / Groq / faster-whisper local)
          ▼
┌─────────────────────┐   assistant.py
│  Assistant          │   1. une confirmation est-elle armée pour CE message ?
│  (orchestrateur)    │      oui → confirmation.py décide (sans IA) : envoyer / annuler / attendre
│                     │   2. sinon → agent IA avec outils
└──┬──────────────┬───┘
   │              │
   ▼              ▼
┌─────────┐  ┌──────────────────┐   agent/llm.py      boucle d'outils Claude
│ Agent IA│─▶│ Outils (lecture, │   agent/tools.py    AUCUN outil d'envoi ni de marquage
│ Claude  │  │ brouillons)      │   agent/session.py  mémoire par conversation
└─────────┘  └────────┬─────────┘   agent/prompts.py  règles, style vocal, sécurité
                      ▼
            ┌───────────────────┐   mail/service.py   règles métier (envoi confirmé, marquage lu)
            │ Service e-mail    │   mail/imap_client.py  EXAMINE + BODY.PEEK (lecture seule)
            │                   │   mail/smtp_sender.py  envoi
            └───────────────────┘   drafts.py          brouillons SQLite + états
                      │             audit.py           journal JSONL
                      ▼
               Serveur IMAP / SMTP
```

### Outils de l'agent

| Outil (IA) | Rôle |
|---|---|
| `list_unread_emails` | mails non lus (≈ `get_unread_emails()`) |
| `list_latest_emails` | derniers mails (≈ `get_latest_emails()`) |
| `search_emails` | recherche serveur : expéditeur, destinataire, objet, texte, dates, non lus |
| `get_email` | contenu complet d'un mail |
| `create_reply_draft` / `create_new_draft` / `update_draft` / `cancel_draft` | brouillons (≈ `create_draft()`) |
| `request_send_confirmation` | représenter un brouillon avec « Je l'envoie ? » |
| `list_drafts` | brouillons en cours |

`send_email()` et `mark_email_as_read()` existent (`MailService.send_confirmed_draft`
et `MailService._mark_original_read`) mais **ne sont pas donnés à l'IA**. Ils ne
sont appelés que par l'assistant, après une confirmation explicite, et le
marquage exige un `SendReceipt` qui n'existe que si le SMTP a accepté le mail.

### États d'un mail et d'un brouillon

```
Mail reçu (NON LU) ──lecture/résumé/brouillon──▶ toujours NON LU
Brouillon : DRAFT ──« oui »──▶ CONFIRMED ──▶ SENDING ──▶ SENT ──▶ mail d'origine \Seen + \Answered
                 │                              └──▶ FAILED ──▶ mail d'origine reste NON LU
                 └──« non / annule »──▶ CANCELLED ──▶ mail d'origine reste NON LU
```

### La confirmation

1. Quand l'IA crée ou modifie un brouillon, **le code** (pas l'IA) le présente :
   destinataire exact, objet, texte intégral avec signature, puis « Je l'envoie ? ».
2. Une confirmation est « armée » pour ce brouillon **et cette version**.
3. Le message **suivant** de l'utilisateur est classé sans IA :
   `oui / vas-y / envoie / c'est bon` → envoi ; `non / annule / laisse tomber` →
   annulation ; `attends / pas encore` → rien ; tout le reste (dont « modifie… »)
   → l'agent reprend la main, **rien n'est envoyé**.
4. La confirmation est consommée par ce message quoi qu'il arrive, expire
   après 10 minutes et n'est valable que dans la même conversation. Un « oui »
   ancien ou répondant à autre chose ne peut donc jamais déclencher d'envoi.
5. Toute modification du brouillon incrémente sa version : il faut reconfirmer.

## 3. Choix techniques

| Composant | Choix | Pourquoi / alternatives |
|---|---|---|
| Langage | Python ≥ 3.10 | Bibliothèque standard complète pour IMAP, SMTP, MIME, SQLite |
| Messagerie | `python-telegram-bot` 22 en **long polling** | Aucun serveur web, domaine ni certificat. Webhook possible plus tard |
| IA | Claude via SDK `anthropic` 1.x, outils définis par l'application, boucle manuelle | Contrôle total : on choisit exactement les outils exposés. `claude-opus-5` par défaut ; `claude-sonnet-5` / `claude-haiku-4-5` configurables pour réduire les coûts |
| Mémoire | En RAM par conversation (12 derniers échanges) | Suffisant pour le MVP ; les brouillons, eux, sont persistés |
| Brouillons | SQLite local | Zéro service externe. Copie dans le dossier « Brouillons » IMAP : étape 3 |
| IMAP / SMTP | `imaplib` / `smtplib` (standard) | Contrôle explicite de `EXAMINE` et `BODY.PEEK`, ce que certaines surcouches masquent |
| STT | `gpt-4o-mini-transcribe` (API OpenAI) par défaut | Accepte l'OGG Telegram tel quel, très bon en français, ≈ 0,003 $/min. Alternatives : Groq Whisper (même code, `STT_BASE_URL`), ou `faster-whisper` local gratuit |
| TTS | `gpt-4o-mini-tts` (sortie Opus native = vraie note vocale Telegram) | Alternative gratuite : `edge-tts` (voix belges/françaises de qualité, service non officiel, nécessite ffmpeg) |
| Journal | JSONL append-only (`data/audit.jsonl`) | Lisible avec `jq`, sans base de données |
| Déploiement | Docker Compose ou systemd, un seul processus | Un petit VPS (1 vCPU, 1 Go) suffit |

Coût indicatif pour ~30 échanges vocaux par jour : quelques centimes de STT/TTS
par jour ; le poste principal est le modèle Claude (réduire avec `LLM_MODEL`
et `LLM_EFFORT` si besoin, le cache de prompt est activé).

## 4. Problèmes identifiés et parades

**IMAP**
- `FETCH BODY[]` / `RFC822` marquent le mail comme lu → on utilise uniquement
  `BODY.PEEK[]`, et la boîte est ouverte en `EXAMINE` (lecture seule) : double sécurité.
- Les clients mail (téléphone, webmail) peuvent eux-mêmes marquer des mails lus : hors de notre contrôle.
- Un UID peut être réattribué (UIDVALIDITY) → avant de marquer lu, on vérifie que le Message-ID correspond.
- Recherche avec accents : `UTF8=ACCEPT` si le serveur le supporte, sinon `CHARSET UTF-8`. Quelques vieux serveurs ne gèrent pas les accents.
- La recherche IMAP est une recherche de sous-chaîne, pas sémantique : l'IA choisit des mots-clés courts.
- Gmail : nécessite un **mot de passe d'application** (2FA). Outlook.com/Microsoft 365 : l'authentification par mot de passe (Basic Auth) est désactivée sur la plupart des comptes → OAuth2 nécessaire (non inclus dans le MVP).

**SMTP**
- Le mail envoyé n'est pas toujours copié dans « Envoyés » → `IMAP_SENT_FOLDER` (vide pour Gmail qui le fait seul).
- Un envoi accepté par le serveur peut encore rebondir plus tard (adresse inexistante) : l'agent ne peut garantir que l'acceptation SMTP.
- SPF/DKIM : utiliser le SMTP de votre fournisseur pour éviter les spams.

**Telegram**
- Les bots ne voient que les messages qu'on leur envoie ; le bot est public par nature → **liste blanche obligatoire** d'identifiants Telegram.
- Les messages transitent par les serveurs Telegram (chiffrement client-serveur, pas de bout en bout pour les bots).
- Limite de 4096 caractères par message → découpage automatique.
- En voiture : Telegram lit les notes vocales à la suite ; utiliser un kit mains libres / CarPlay-Android Auto pour enregistrer sans manipuler le téléphone.

**Speech-to-Text**
- Bruit de la voiture, noms propres et adresses mal transcrits → la transcription est affichée (« 🎤 … ») ; les adresses e-mail dictées sont peu fiables, d'où la préférence pour « réponds à ce mail » plutôt que la saisie d'adresses.
- Un « oui » mal transcrit ne peut rien envoyer d'imprévu : il ne vaut que pour le brouillon qui vient d'être lu.

**Text-to-Speech**
- Lire un long mail est pénible → l'IA résume ; le texte complet reste affiché.
- Longueur plafonnée (~3500 caractères) ; les adresses e-mail et symboles sont retirés de la version parlée.

**IA**
- Hallucinations possibles dans un résumé ou un brouillon → le brouillon intégral est toujours présenté avant envoi.
- Prompt injection → voir ci-dessous.

## 5. Sécurité

- Secrets uniquement dans l'environnement (`.env` non versionné) ; aucun secret dans le code ni dans les logs (les logs HTTP qui contiendraient le token Telegram sont coupés).
- Liste blanche Telegram ; tentatives non autorisées journalisées.
- **Défense structurelle contre les prompt injections** : même si un mail piégé
  manipulait l'IA, celle-ci ne dispose d'aucun outil pour envoyer un mail ou
  modifier un statut. Elle peut au pire préparer un brouillon, qui est présenté
  par le code avec son vrai destinataire et exige un « oui » de l'utilisateur.
- Le contenu des mails est encadré par des balises `<email … contenu_non_fiable="oui">`,
  neutralisées si le mail tente de les refermer ; le prompt système impose de le
  traiter comme une donnée.
- Les réponses partent uniquement vers l'expéditeur (ou Reply-To) du mail d'origine.
- Journal d'audit de chaque consultation, brouillon, confirmation, envoi, échec et marquage.

## 6. Feuille de route

- **Étape 1 & 2 (ce MVP)** : Telegram texte + vocal, lecture/résumé/recherche,
  brouillons, confirmation, envoi, marquage lu après envoi, journal, tests.
- **Étape 3** : analyse des pièces jointes (PDF via blocs `document` de Claude,
  images via vision), brouillons synchronisés dans le dossier IMAP « Brouillons »,
  priorisation, mémoire persistante, OAuth2 (Gmail/Microsoft), WhatsApp
  (WhatsApp Cloud API → nouvel adaptateur dans `channels/` réutilisant `ChannelCore`).
