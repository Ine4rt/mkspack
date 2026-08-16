<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/App.php';

$app = App::get();
$input = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

$token = (string) ($input['token'] ?? '');
$replace = (string) ($input['replace'] ?? '');

$record = $app->loadPlan($token);
if ($record === null || $record['owner_key'] !== $app->ownerKey()) {
    json_out(['ok' => false, 'error' => 'not_found', 'message' => 'Menu introuvable.'], 404);
}

// Les comptes gratuits ont droit à quelques remplacements par menu.
if (!$app->isPremium()) {
    $used = (int) ($_SESSION['swaps'][$token] ?? 0);
    $max = (int) $app->config['free']['swaps_per_menu'];
    if ($used >= $max) {
        json_out([
            'ok' => false,
            'error' => 'quota',
            'paywall' => true,
            'message' => "Vous avez utilisé vos $max remplacements gratuits sur ce menu. "
                . "L'accès illimité permet de changer chaque dîner autant de fois que nécessaire.",
        ], 402);
    }
    $_SESSION['swaps'][$token] = $used + 1;
}

$keepIds = array_column($record['plan']['days'], 'id');
$plan = $app->engine()->swapDay($record['prefs'], $keepIds, $replace);

if (!($plan['ok'] ?? false)) {
    json_out($plan, 422);
}

$app->updatePlan($token, $plan);
$plan['token'] = $token;
$plan['premium'] = $app->isPremium();
$plan['swapsLeft'] = $app->isPremium()
    ? null
    : max(0, (int) $app->config['free']['swaps_per_menu'] - (int) ($_SESSION['swaps'][$token] ?? 0));

$app->track('swap', ['replaced' => $replace]);

json_out($plan);
