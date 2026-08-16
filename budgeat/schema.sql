-- Schéma SQLite de Budgeat. Créé automatiquement au premier lancement.

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT    NOT NULL UNIQUE,
    pass_hash     TEXT    NOT NULL,
    plan          TEXT    NOT NULL DEFAULT 'free',   -- free | monthly | yearly | lifetime
    plan_until    TEXT,                              -- NULL pour free et lifetime
    stripe_customer TEXT,
    stripe_sub    TEXT,
    referral_code TEXT    UNIQUE,
    referred_by   INTEGER REFERENCES users(id),
    reward_given  INTEGER NOT NULL DEFAULT 0,        -- le parrain a-t-il déjà été récompensé
    created_at    TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS plans (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    token       TEXT    NOT NULL UNIQUE,
    owner_key   TEXT    NOT NULL,        -- u<id> pour un compte, v<uuid> pour un visiteur
    user_id     INTEGER REFERENCES users(id),
    prefs_json  TEXT    NOT NULL,
    plan_json   TEXT    NOT NULL,
    total       REAL    NOT NULL,
    budget      REAL    NOT NULL,
    created_at  TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_plans_owner ON plans(owner_key, created_at);

CREATE TABLE IF NOT EXISTS payments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id),
    provider    TEXT    NOT NULL,        -- stripe | demo
    reference   TEXT,                    -- session ou paiement côté fournisseur
    kind        TEXT    NOT NULL,        -- monthly | yearly | lifetime
    amount      INTEGER NOT NULL,        -- centimes
    currency    TEXT    NOT NULL DEFAULT 'eur',
    status      TEXT    NOT NULL,        -- pending | paid | refunded | failed
    created_at  TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_payments_ref ON payments(reference);

CREATE TABLE IF NOT EXISTS events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_key   TEXT    NOT NULL,
    event       TEXT    NOT NULL,
    payload     TEXT,
    created_at  TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_events_type ON events(event, created_at);

-- Clics sortants vers les partenaires (drive, enseignes), pour suivre
-- les revenus d'affiliation.
CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_key   TEXT    NOT NULL,
    partner     TEXT    NOT NULL,
    plan_token  TEXT,
    created_at  TEXT    NOT NULL
);
