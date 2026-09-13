<?php
// ---------------------------------------------------------------------------
// Connexion + création automatique de la table du suivi santé.
// Inclus par sante.php et sante_admin.php.
// ---------------------------------------------------------------------------

// Mot de passe de la page admin (à changer).
define('SANTE_ADMIN_PASSWORD', 'change-moi');

date_default_timezone_set('Europe/Brussels');

include __DIR__ . '/db.php'; // fournit $conn (mysqli)

$conn->set_charset('utf8mb4');

// Crée la table au premier lancement, rien à faire à la main.
$conn->query("CREATE TABLE IF NOT EXISTS sante_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_text VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
