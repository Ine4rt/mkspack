<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Billing.php';

$app = App::get();
$billing = new Billing($app);

// Retour du tunnel de démonstration : on valide le "paiement" puis on renvoie
// l'utilisateur vers son compte.
if (isset($_GET['confirm'])) {
    $billing->confirmDemo((string) $_GET['confirm']);
    header('Location: ' . $app->url('/compte.php?paiement=ok&demo=1'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $app->url('/tarifs.php'));
    exit;
}

if (!$app->checkCsrf($_POST['csrf'] ?? null)) {
    http_response_code(400);
    exit('Session expirée, rechargez la page.');
}

$kind = (string) ($_POST['kind'] ?? 'monthly');

if ($app->user() === null) {
    // On mémorise la formule choisie pour reprendre le tunnel après inscription.
    $_SESSION['pending_plan'] = $kind;
    header('Location: ' . $app->url('/connexion.php?suite=paiement'));
    exit;
}

$app->track('checkout_start', ['kind' => $kind]);
$result = $billing->checkout($kind);

if (!$result['ok']) {
    header('Location: ' . $app->url('/tarifs.php?erreur=' . urlencode($result['error'])));
    exit;
}

header('Location: ' . $result['url']);
