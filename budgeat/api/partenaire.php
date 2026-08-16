<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/App.php';

/**
 * Redirection tracée vers un partenaire de courses en ligne (drive).
 * C'est la deuxième source de revenus du service : chaque panier transmis
 * à une enseigne partenaire génère une commission d'apport d'affaire.
 * Les partenaires se déclarent dans config.php > affiliates.
 */
$app = App::get();

$partner = (string) ($_GET['p'] ?? '');
$token   = (string) ($_GET['menu'] ?? '');
$config  = $app->config['affiliates'][$partner] ?? null;

if ($config === null) {
    http_response_code(404);
    exit('Partenaire inconnu.');
}

$stmt = $app->db->prepare(
    'INSERT INTO affiliate_clicks (owner_key, partner, plan_token, created_at)
     VALUES (?, ?, ?, datetime("now"))'
);
$stmt->execute([$app->ownerKey(), $partner, $token ?: null]);

// La liste de courses est transmise au drive quand il accepte une recherche
// multiple ; sinon le lien renvoie simplement vers l'enseigne.
$query = '';
if ($token !== '' && str_contains($config['url'], '{q}')) {
    $record = $app->loadPlan($token);
    if ($record !== null) {
        $names = [];
        foreach ($record['plan']['shopping']['aisles'] as $aisle) {
            foreach ($aisle['items'] as $item) {
                $names[] = $item['name'];
            }
        }
        $query = implode(',', array_slice($names, 0, 40));
    }
}

header('Location: ' . str_replace('{q}', urlencode($query), $config['url']));
