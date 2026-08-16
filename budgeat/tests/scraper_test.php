<?php
/**
 * Tests de la collecte sur les sites d'enseignes :
 *   php budgeat/tests/scraper_test.php
 *
 * Aucune requête réseau : les pages sont figées dans tests/fixtures/pages.
 * Ce qui est vérifié, c'est ce qui casse en pratique — l'extraction du bon
 * montant au milieu d'une page qui en contient plusieurs, le refus d'inventer
 * un prix quand il n'y en a pas, la lecture de robots.txt et la détection des
 * pages anti-robot.
 */
declare(strict_types=1);

require_once __DIR__ . '/../scripts/lib/Scraper.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok   $label\n"; }
    else { $fail++; echo "  FAIL $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}

$dir = __DIR__ . '/fixtures/pages';
$scraper = new Scraper(cacheDir: sys_get_temp_dir() . '/budgeat_scraper_test', contact: 'test@budgeat.be');
$rules = ['xpath' => ["//*[contains(@class,'price__amount')]"]];

function page(string $name): string
{
    return (string) file_get_contents(__DIR__ . '/fixtures/pages/' . $name . '.html');
}

echo "\n== Extraction du prix ==\n";

$cases = [
    'jsonld'    => [8.49, 'données structurées schema.org'],
    'graph'     => [2.99, 'JSON-LD imbriqué dans @graph'],
    'microdata' => [1.15, 'microdata itemprop'],
    'meta'      => [3.79, 'balise meta Open Graph'],
    'plain'     => [2.35, 'sélecteur XPath configuré'],
];
foreach ($cases as $name => [$expected, $label]) {
    $got = $scraper->extractPrice(page($name), $rules);
    check($label, $got !== null && abs($got - $expected) < 0.001,
        $got === null ? 'aucun prix' : (string) $got);
}

echo "\n== Pièges ==\n";

// La page affiche une note client (4,80) et un poids (0,90 kg) avant le prix.
check('une note client n\'est pas prise pour un prix',
    abs(($scraper->extractPrice(page('piege'), $rules) ?? 0) - 9.95) < 0.001);

// Mieux vaut aucun prix qu'un prix inventé : la page ne contient que du JS.
check('page sans prix : rien n\'est retourné',
    $scraper->extractPrice(page('spa'), $rules) === null);

check('page anti-robot reconnue comme telle',
    $scraper->extractPrice(page('challenge'), $rules) === null);

echo "\n== Lecture des montants ==\n";

$conversions = [
    '4,99 €'      => 4.99,
    '€ 4.99'      => 4.99,
    '  12,50  €'  => 12.50,
    "8,49\u{00A0}€" => 8.49,
    '1.05'        => 1.05,
];
foreach ($conversions as $raw => $expected) {
    $got = $scraper->toFloat($raw);
    check("« $raw » → $expected", $got !== null && abs($got - $expected) < 0.001,
        $got === null ? 'refusé' : (string) $got);
}

$refused = ['0.00', 'Réf. 123456', '9999', 'en stock', '4,80 sur 5 étoiles'];
foreach ($refused as $raw) {
    $got = $scraper->toFloat($raw);
    // « 4,80 sur 5 » reste un montant plausible hors contexte : seul l'ordre de
    // priorité des extracteurs protège de ce cas, pas la conversion.
    $expectRefusal = !str_contains($raw, '4,80');
    if ($expectRefusal) {
        check("« $raw » refusé", $got === null, (string) $got);
    }
}

echo "\n== robots.txt ==\n";

$robots = <<<TXT
User-agent: *
Disallow: /panier
Disallow: /compte/
Allow: /fr/

User-agent: BadBot
Disallow: /
TXT;

$method = new ReflectionMethod(Scraper::class, 'parseRobots');
$method->setAccessible(true);
$rules2 = $method->invoke($scraper, $robots);

check('les Disallow du bloc « * » sont lus', in_array('/panier', $rules2, true) && in_array('/compte/', $rules2, true));
check('les règles visant un autre robot sont ignorées', !in_array('/', $rules2, true), implode(',', $rules2));

// Un robots.txt qui interdit tout doit bloquer la collecte.
$rules3 = $method->invoke($scraper, "User-agent: *\nDisallow: /");
check('un site fermé aux robots est détecté', in_array('/', $rules3, true));

echo "\n== Configuration ==\n";

$config = json_decode(file_get_contents(__DIR__ . '/../data/collectors.json'), true);
check('collectors.json est valide', is_array($config) && isset($config['collectors']));
check('les 4 enseignes demandées sont configurées',
    array_diff(['colruyt', 'delhaize', 'lidl', 'aldi'], array_keys($config['collectors'])) === []);
check('l\'adresse de contact est encore un modèle à remplir',
    str_contains($config['contact'], 'example.com'),
    'si elle est déjà remplie, ce test peut être retiré');

echo "\n----------------------------------------\n";
echo "$pass réussis, $fail échoués\n";
exit($fail === 0 ? 0 : 1);
