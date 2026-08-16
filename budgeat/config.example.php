<?php
/**
 * Copiez ce fichier en config.php et renseignez vos valeurs.
 * config.php n'est jamais versionné (voir .gitignore).
 */
return [
    // Nom et URL publique du service
    'app_name' => 'Budgeat',
    'base_url' => 'https://budgeat.example',   // sans slash final
    'country'  => 'BE',                        // BE ou FR : filtre les enseignes proposées

    // Emplacement de la base SQLite (doit être inscriptible par PHP,
    // et idéalement hors du dossier public).
    'db_path'  => __DIR__ . '/storage/budgeat.sqlite',

    // Adresse d'expédition des e-mails transactionnels
    'mail_from' => 'bonjour@budgeat.example',

    // ---------------------------------------------------------------- offres
    // Montants en centimes. Adaptez-les à votre marché.
    'plans' => [
        'monthly'  => ['price' => 499,  'label' => '4,99 €/mois',        'currency' => 'eur'],
        'yearly'   => ['price' => 3900, 'label' => '39 €/an',            'currency' => 'eur'],
        'lifetime' => ['price' => 6900, 'label' => '69 € une seule fois','currency' => 'eur'],
    ],

    // Quotas de la formule gratuite
    'free' => [
        'menus_per_week' => 1,   // menus générés par semaine sans abonnement
        'swaps_per_menu' => 2,   // remplacements de dîner par menu
    ],

    // -------------------------------------------------------------- paiement
    // Stripe : https://dashboard.stripe.com/apikeys
    // Laissez vide pour faire tourner le site en mode démo (paiement simulé).
    'stripe' => [
        'secret_key'      => '',   // sk_live_... ou sk_test_...
        'publishable_key' => '',   // pk_live_... ou pk_test_...
        'webhook_secret'  => '',   // whsec_...
        // IDs de prix créés dans le dashboard Stripe (facultatif :
        // sans eux, les montants ci-dessus sont utilisés en prix ad hoc).
        'price_monthly'   => '',
        'price_yearly'    => '',
    ],

    // ------------------------------------------------------------ parrainage
    'referral' => [
        'enabled'        => true,
        'reward_months'  => 1,    // mois offerts au parrain quand le filleul paie
        'welcome_months' => 1,    // mois offerts au filleul à l'inscription
    ],

    // ----------------------------------------------------------- affiliation
    // Liens de courses en ligne. {q} est remplacé par la liste encodée.
    // Renseignez vos identifiants d'affilié pour toucher une commission.
    'affiliates' => [
        // 'carrefour' => ['label' => 'Commander sur Carrefour Drive', 'url' => 'https://...?aff=VOTRE_ID'],
    ],

    // Clé de signature des cookies de session (changez-la !)
    'secret' => 'changez-moi-par-une-chaine-aleatoire-longue',
];
