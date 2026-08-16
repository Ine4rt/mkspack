<?php
declare(strict_types=1);

/**
 * Vérification de l'hébergement, à ouvrir dans le navigateur juste après
 * l'envoi des fichiers par FTP : https://votre-domaine/install.php
 *
 * Contrôle ce qui bloque réellement sur un hébergement mutualisé (version de
 * PHP, extensions, droits d'écriture), crée la base et propose un compte
 * administrateur. À SUPPRIMER une fois l'installation terminée — la page le
 * rappelle et refuse de tourner si la base contient déjà des comptes.
 */

$root = __DIR__;
$checks = [];
$blocking = 0;

/**
 * $blocking : empêche de poursuivre l'installation.
 * $severe   : s'affiche en rouge sans pour autant bloquer — pour ce qu'il faut
 *             absolument corriger, mais qui ne se règle pas depuis cette page.
 */
function check(string $label, bool $ok, string $detail, bool $blocking = true, bool $severe = false): array
{
    return ['label' => $label, 'ok' => $ok, 'detail' => $detail,
            'blocking' => $blocking, 'severe' => $severe];
}

// ----------------------------------------------------------------- contrôles

$checks[] = check(
    'Version de PHP',
    PHP_VERSION_ID >= 80100,
    PHP_VERSION_ID >= 80100
        ? 'PHP ' . PHP_VERSION
        : 'PHP ' . PHP_VERSION . ' — il faut au moins PHP 8.1. Cherchez « version PHP » dans le panneau de votre hébergeur.'
);

foreach ([
    'pdo_sqlite' => 'stocke les comptes et les menus',
    'curl'       => 'paiements Stripe et collecte de prix',
    'mbstring'   => 'gestion des accents',
    'json'       => 'lecture du catalogue',
] as $ext => $why) {
    $checks[] = check(
        "Extension $ext",
        extension_loaded($ext),
        extension_loaded($ext) ? 'présente' : "absente — nécessaire pour : $why"
    );
}

$checks[] = check(
    'Extension dom',
    extension_loaded('dom'),
    extension_loaded('dom') ? 'présente' : 'absente — seule la collecte de prix en pâtira, le site fonctionnera',
    false
);

// Écriture : le point qui coince le plus souvent en mutualisé.
$storage = "$root/storage";
if (!is_dir($storage)) {
    @mkdir($storage, 0775, true);
}
$writable = is_dir($storage) && is_writable($storage);
$checks[] = check(
    'Dossier storage/ inscriptible',
    $writable,
    $writable
        ? 'la base de données pourra être créée'
        : 'impossible d\'écrire. Dans votre client FTP, clic droit sur storage/ → Permissions → 755, ou 775 si besoin.'
);

// Le point vraiment dangereux : storage/ accessible depuis le web. On ne le
// devine pas, on le teste — en demandant au serveur un fichier témoin.
$exposed = null;
if ($writable) {
    // Nom fixe : si la vérification automatique échoue, on laisse ce fichier
    // en place pour que vous puissiez faire le test vous-même dans un onglet.
    $witness = 'test-acces-public.txt';
    @file_put_contents("$storage/$witness", 'budgeat');

    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    $witnessUrl = "$base/storage/$witness";

    if (function_exists('curl_init')) {
        $ch = curl_init($witnessUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,   // certificat parfois incomplet en local
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $status > 0) {
            $exposed = $status === 200 && is_string($body) && str_contains($body, 'budgeat');
        }
        // Requête sans réponse : le serveur ne peut pas se répondre à lui-même
        // (cas courant en local, ou avec un pare-feu sortant). On laisse
        // $exposed à null : « je ne sais pas » ne doit jamais devenir « c'est bon ».
    }
    if ($exposed !== null) {
        @unlink("$storage/$witness");
    }
}

if ($exposed === true) {
    // Grave, mais rien ne se règle depuis cette page : on alerte en rouge et on
    // laisse l'installation se terminer, sinon le site est ininstallable chez
    // les hébergeurs qui ne lisent pas .htaccess.
    $checks[] = check(
        'Dossier storage/ inaccessible depuis le web',
        false,
        'ATTENTION : le contenu de storage/ se télécharge depuis un navigateur. '
        . 'Votre base de données (adresses e-mail, mots de passe chiffrés) est exposée. '
        . 'Voir « Protéger le dossier storage/ » en bas de cette page — à corriger '
        . 'avant d\'ouvrir le site au public.',
        blocking: false,
        severe: true
    );
} elseif ($exposed === false) {
    $checks[] = check(
        'Dossier storage/ inaccessible depuis le web',
        true,
        'le serveur refuse l\'accès direct, c\'est ce qu\'on veut'
    );
} else {
    $checks[] = check(
        'Dossier storage/ inaccessible depuis le web',
        false,
        'vérification impossible depuis le serveur. Testez vous-même : ouvrez '
        . ($witnessUrl ?? 'l\'adresse de storage/') . ' dans un onglet. '
        . 'Si vous voyez le mot « budgeat » au lieu d\'une erreur, le dossier est exposé.',
        false
    );
}

$configExists = file_exists("$root/config.php");
$checks[] = check(
    'Fichier config.php',
    $configExists,
    $configExists
        ? 'présent'
        : 'absent — renommez config.example.php en config.php et complétez-le',
    false
);

foreach ($checks as $c) {
    if (!$c['ok'] && $c['blocking']) {
        $blocking++;
    }
}

// Création de la base dès que l'environnement le permet.
$dbReady = false;
$dbError = null;
$hasUsers = false;

if ($blocking === 0) {
    try {
        require_once "$root/lib/App.php";
        $app = App::get();
        $dbReady = true;
        $hasUsers = (int) $app->db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

// ------------------------------------------------- création du compte admin

$created = null;
$error = null;

if ($dbReady && !$hasUsers && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = $app->register((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        $stmt = $app->db->prepare('UPDATE users SET plan = "lifetime" WHERE email = ?');
        $stmt->execute([strtolower(trim((string) $_POST['email']))]);
        $created = (string) $_POST['email'];
        $hasUsers = true;
    } else {
        $error = $result['error'];
    }
}

$ok = $blocking === 0 && $dbReady;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation</title>
<link rel="stylesheet" href="assets/app.css">
<style>
  .check { display: flex; gap: .8rem; padding: .8rem 0; border-bottom: 1px solid var(--line); align-items: flex-start; }
  .check-mark { font-size: 1.1rem; line-height: 1.4; flex-shrink: 0; width: 1.5rem; }
  .check-ok { color: var(--brand); }
  .check-ko { color: var(--danger); }
  .check-warn { color: var(--accent); }
  .check-body strong { display: block; }
  .check-body span { font-size: .9rem; color: var(--ink-soft); }
</style>
</head>
<body>
<main class="narrow" style="padding:3rem 0">
  <h1>Installation de Budgeat</h1>

  <div class="card card-pad" style="margin-bottom:1.5rem">
    <?php foreach ($checks as $c): ?>
      <div class="check">
        <?php $red = !$c['ok'] && ($c['blocking'] || $c['severe']); ?>
        <span class="check-mark <?= $c['ok'] ? 'check-ok' : ($red ? 'check-ko' : 'check-warn') ?>">
          <?= $c['ok'] ? '✓' : ($red ? '✕' : '!') ?>
        </span>
        <span class="check-body">
          <strong><?= htmlspecialchars($c['label']) ?></strong>
          <span><?= htmlspecialchars($c['detail']) ?></span>
        </span>
      </div>
    <?php endforeach; ?>

    <?php if ($dbError !== null): ?>
      <div class="check">
        <span class="check-mark check-ko">✕</span>
        <span class="check-body">
          <strong>Base de données</strong>
          <span><?= htmlspecialchars($dbError) ?></span>
        </span>
      </div>
    <?php elseif ($dbReady): ?>
      <div class="check">
        <span class="check-mark check-ok">✓</span>
        <span class="check-body">
          <strong>Base de données</strong>
          <span>créée et accessible dans storage/</span>
        </span>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$ok): ?>
    <div class="flash err">
      <strong>Corrigez les points en rouge, puis rechargez cette page.</strong>
      Les points en orange n'empêchent pas le site de fonctionner.
    </div>

  <?php elseif ($created !== null): ?>
    <div class="flash ok">
      <strong>Compte créé pour <?= htmlspecialchars($created) ?></strong>, avec l'accès complet.
      Vous êtes déjà connecté.
    </div>
    <div class="flash err">
      <strong>Dernière étape : supprimez install.php de votre serveur.</strong>
      Tant qu'il est là, n'importe qui peut consulter la configuration de votre hébergement.
    </div>
    <p><a class="btn btn-primary" href="app.php">Composer une première semaine</a>
       <a class="btn btn-ghost" href="index.php">Voir le site</a></p>

  <?php elseif ($hasUsers): ?>
    <div class="flash">
      L'installation est déjà faite : la base contient des comptes.
      <strong>Supprimez install.php</strong>, il n'a plus d'utilité.
    </div>
    <p><a class="btn btn-primary" href="index.php">Aller sur le site</a></p>

  <?php else: ?>
    <h2>Créer votre compte</h2>
    <p class="muted">Ce premier compte reçoit l'accès complet, sans passer par le paiement.</p>
    <?php if ($error !== null): ?>
      <div class="flash err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="card card-pad">
      <label class="field">
        <span>Votre adresse e-mail</span>
        <input type="email" name="email" required autofocus>
      </label>
      <label class="field">
        <span>Mot de passe</span>
        <input type="password" name="password" required minlength="8">
        <span class="hint">8 caractères minimum.</span>
      </label>
      <button class="btn btn-primary btn-block">Créer le compte</button>
    </form>
  <?php endif; ?>

  <?php if ($exposed === true): ?>
    <h2 style="margin-top:2.5rem">Protéger le dossier storage/</h2>
    <p class="muted">
      Votre serveur laisse télécharger les fichiers de <code>storage/</code>. C'est là que
      vivent la base de données et son cache. Trois solutions, de la meilleure à la plus
      rapide :
    </p>
    <ol class="muted">
      <li><strong>Déplacer le dossier hors du répertoire public.</strong> Créez
        <code>storage/</code> à côté de <code>www</code> (et non dedans), puis indiquez
        son chemin dans <code>config.php</code> :
        <code>'db_path' =&gt; __DIR__ . '/../storage/budgeat.sqlite'</code>.
        C'est la seule solution qui ferme complètement la porte.</li>
      <li><strong>Demander à votre hébergeur</strong> d'interdire l'accès à ce dossier.
        Sur nginx, c'est une règle <code>location</code> ; le support le fait en deux minutes.</li>
      <li><strong>À défaut</strong>, la base porte déjà un nom aléatoire, ce qui la met à
        l'abri d'un scan automatique — mais pas de quelqu'un qui cherche vraiment.</li>
    </ol>
  <?php endif; ?>

  <h2 style="margin-top:2.5rem">Ensuite</h2>
  <ol class="muted">
    <li>Complétez <code>legal/mentions.php</code> avec votre identité : c'est obligatoire pour vendre.</li>
    <li>Renseignez vos clés Stripe dans <code>config.php</code> quand vous voudrez encaisser.
        Sans elles, le site tourne en mode démonstration.</li>
    <li>Recalez les prix : voir <code>LISEZMOI.txt</code>, section « Prix réels ».</li>
  </ol>
</main>
</body>
</html>
