<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/App.php';

$app = App::get();

$input = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

$quota = $app->quota();
if (!$quota['unlimited'] && $quota['left'] <= 0) {
    json_out([
        'ok'      => false,
        'error'   => 'quota',
        'paywall' => true,
        'message' => "Vous avez utilisé votre menu gratuit de la semaine. "
            . "L'accès illimité débloque un nouveau menu quand vous voulez.",
        'quota'   => $quota,
    ], 402);
}

$prefs = [
    'store'     => (string) ($input['store'] ?? 'lidl'),
    'budget'    => (float)  ($input['budget'] ?? 60),
    'people'    => (int)    ($input['people'] ?? 2),
    'days'      => (int)    ($input['days'] ?? 7),
    'diet'      => (string) ($input['diet'] ?? 'omnivore'),
    'allergens' => (array)  ($input['allergens'] ?? []),
    'exclude'   => (array)  ($input['exclude'] ?? []),
    'maxTime'   => (int)    ($input['maxTime'] ?? 0),
    'seed'      => (string) ($input['seed'] ?? bin2hex(random_bytes(4))),
];
if (isset($input['pantry']) && is_array($input['pantry'])) {
    $prefs['pantry'] = $input['pantry'];
}

$plan = $app->engine()->generate($prefs);

if (!($plan['ok'] ?? false)) {
    json_out($plan, 422);
}

$token = $app->savePlan($prefs, $plan);
$plan['token'] = $token;
$plan['quota'] = $app->quota();
$plan['premium'] = $app->isPremium();
$plan['swapsLeft'] = $app->isPremium()
    ? null
    : (int) $app->config['free']['swaps_per_menu'];

$app->track('generate', [
    'store'  => $prefs['store'],
    'budget' => $prefs['budget'],
    'people' => $prefs['people'],
    'diet'   => $prefs['diet'],
    'total'  => $plan['totals']['total'],
]);

json_out($plan);
