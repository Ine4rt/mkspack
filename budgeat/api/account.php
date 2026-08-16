<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Billing.php';

$app = App::get();
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === 'logout') {
    $app->logout();
    header('Location: ' . $app->url('/'));
    exit;
}

if ($action === 'resilier') {
    $user = $app->user();
    if ($user === null) {
        header('Location: ' . $app->url('/connexion.php'));
        exit;
    }
    $result = (new Billing($app))->cancel((int) $user['id']);
    $app->track('cancel', ['ok' => $result['ok']]);
    $_SESSION['flash'] = $result['ok']
        ? "Renouvellement interrompu. Votre accès reste actif jusqu'au terme de la période déjà payée."
        : $result['error'];
    header('Location: ' . $app->url('/compte.php?resiliation=' . ($result['ok'] ? 'ok' : 'ko')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$app->checkCsrf($_POST['csrf'] ?? null)) {
    header('Location: ' . $app->url('/connexion.php'));
    exit;
}

$email = (string) ($_POST['email'] ?? '');
$password = (string) ($_POST['password'] ?? '');

$result = $action === 'register'
    ? $app->register($email, $password, $_POST['referral'] ?? ($_SESSION['referral'] ?? null))
    : $app->login($email, $password);

if (!$result['ok']) {
    $_SESSION['flash'] = $result['error'];
    header('Location: ' . $app->url('/connexion.php?mode=' . ($action === 'register' ? 'inscription' : 'connexion')));
    exit;
}

// L'utilisateur venait de choisir une formule : on reprend le tunnel là où
// il l'avait laissé plutôt que de le perdre sur la page d'accueil.
if (!empty($_SESSION['pending_plan'])) {
    $kind = $_SESSION['pending_plan'];
    unset($_SESSION['pending_plan']);
    $billing = new Billing($app);
    $checkout = $billing->checkout($kind);
    if ($checkout['ok']) {
        header('Location: ' . $checkout['url']);
        exit;
    }
}

header('Location: ' . $app->url('/compte.php'));
