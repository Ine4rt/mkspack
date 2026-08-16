<?php
/**
 * Tests du moteur : php budgeat/tests/engine_test.php
 * Pas de framework, uniquement des assertions lisibles en sortie.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/Engine.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ok   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
}

$catalog = Catalog::load();
$engine  = new Engine($catalog);

echo "\n== Catalogue ==\n";
check('ingrédients chargés', count($catalog->ingredients) > 80, (string) count($catalog->ingredients));
check('recettes chargées', count($catalog->recipes) >= 50, (string) count($catalog->recipes));
check('enseignes chargées', count($catalog->stores) === 20, (string) count($catalog->stores));

$vegan = array_filter($catalog->recipes, fn($r) => in_array('vegan', $r['diets'], true));
$vege  = array_filter($catalog->recipes, fn($r) => in_array('vegetarien', $r['diets'], true));
$pesc  = array_filter($catalog->recipes, fn($r) => in_array('pescetarien', $r['diets'], true));
check('assez de recettes végétariennes', count($vege) >= 12, (string) count($vege));
check('assez de recettes véganes', count($vegan) >= 7, (string) count($vegan));
check('assez de recettes pescétariennes', count($pesc) >= 20, (string) count($pesc));

// Une recette avec lardons ne doit jamais être végétarienne.
check(
    'cohérence régime/ingrédients',
    !in_array('vegetarien', $catalog->recipes['carbonara']['diets'], true)
    && in_array('vegan', $catalog->recipes['chili-sin-carne']['diets'], true),
    json_encode($catalog->recipes['chili-sin-carne']['diets'])
);
check(
    'allergènes déduits',
    in_array('lait', $catalog->recipes['gratin-chou-fleur']['allergens'], true)
    && in_array('gluten', $catalog->recipes['pates-bolo']['allergens'], true)
);

echo "\n== Indices prix par enseigne ==\n";
$lidl = $catalog->packPrice('poulet-filet', 'lidl');
$mono = $catalog->packPrice('poulet-filet', 'monoprix');
check('Lidl moins cher que Monoprix', $lidl < $mono, "lidl=$lidl monoprix=$mono");

echo "\n== Génération : budget respecté ==\n";
$scenarios = [
    ['label' => '2 pers / 60 € / Lidl',        'store' => 'lidl',      'budget' => 60,  'people' => 2, 'diet' => 'omnivore'],
    ['label' => '4 pers / 90 € / Colruyt',     'store' => 'colruyt',   'budget' => 90,  'people' => 4, 'diet' => 'omnivore'],
    ['label' => '1 pers / 35 € / Aldi',        'store' => 'aldi',      'budget' => 35,  'people' => 1, 'diet' => 'omnivore'],
    ['label' => '4 pers / 70 € végé / Leclerc','store' => 'leclerc',   'budget' => 70,  'people' => 4, 'diet' => 'vegetarien'],
    ['label' => '2 pers / 45 € végan / Lidl',  'store' => 'lidl',      'budget' => 45,  'people' => 2, 'diet' => 'vegan'],
    ['label' => '3 pers / 75 € pesc / Delhaize','store' => 'delhaize', 'budget' => 75,  'people' => 3, 'diet' => 'pescetarien'],
    ['label' => '5 pers / 120 € / Carrefour',  'store' => 'carrefour', 'budget' => 120, 'people' => 5, 'diet' => 'sans-porc'],
    ['label' => '2 pers / 40 € flexi / Netto', 'store' => 'netto',     'budget' => 40,  'people' => 2, 'diet' => 'flexitarien'],
];

foreach ($scenarios as $n => $s) {
    $plan = $engine->generate($s + ['days' => 7, 'seed' => (string) $n]);
    if (!$plan['ok']) {
        check($s['label'], false, $plan['message']);
        continue;
    }
    $t = $plan['totals'];
    $ok = $t['total'] <= $t['budget'];
    check(
        $s['label'] . " -> {$t['total']} € / {$t['budget']} € ({$t['usedPct']} %), {$t['perMeal']} €/assiette, gaspi {$t['wastePct']} %",
        $ok
    );
    check('  7 dîners distincts', count($plan['days']) === 7 && count(array_unique(array_column($plan['days'], 'id'))) === 7);
    check('  budget bien utilisé (>60 %)', $t['usedPct'] >= 60, $t['usedPct'] . ' %');
}

echo "\n== Filtres durs ==\n";
$plan = $engine->generate(['store' => 'lidl', 'budget' => 80, 'people' => 2, 'diet' => 'vegan', 'seed' => 'x']);
$hasAnimal = false;
foreach ($plan['days'] as $d) {
    foreach ($catalog->recipes[$d['id']]['types'] as $t) {
        if (in_array($t, ['meat', 'pork', 'fish', 'seafood', 'dairy', 'egg', 'honey'], true)) {
            $hasAnimal = true;
        }
    }
}
check('menu végan sans produit animal', !$hasAnimal);

$plan = $engine->generate(['store' => 'lidl', 'budget' => 90, 'people' => 2, 'allergens' => ['gluten', 'lait'], 'seed' => 'y']);
$bad = [];
foreach ($plan['days'] as $d) {
    $inter = array_intersect($catalog->recipes[$d['id']]['allergens'], ['gluten', 'lait']);
    if ($inter !== []) {
        $bad[] = $d['id'];
    }
}
check('sans gluten ni lait respecté', $bad === [], implode(',', $bad));

$plan = $engine->generate(['store' => 'lidl', 'budget' => 80, 'people' => 2, 'maxTime' => 30, 'seed' => 'z']);
check('temps max 30 min respecté', max(array_column($plan['days'], 'time')) <= 30);

$plan = $engine->generate(['store' => 'lidl', 'budget' => 80, 'people' => 2, 'exclude' => ['champignon', 'oignon'], 'seed' => 'w']);
$bad = [];
foreach ($plan['days'] as $d) {
    if (array_intersect(array_column($catalog->recipes[$d['id']]['items'], 'i'), ['champignon', 'oignon']) !== []) {
        $bad[] = $d['id'];
    }
}
check('ingrédients exclus absents', $bad === [], implode(',', $bad));

echo "\n== Budget trop serré ==\n";
$plan = $engine->generate(['store' => 'monoprix', 'budget' => 15, 'people' => 4, 'seed' => 'tight']);
check('réponse cohérente en budget impossible', $plan['ok'] === true && $plan['totals']['total'] > 0);
echo "     (budget 15 € pour 4 pers -> plancher atteint : {$plan['totals']['total']} €)\n";

echo "\n== Variété entre deux semaines ==\n";
$a = $engine->generate(['store' => 'lidl', 'budget' => 65, 'people' => 2, 'seed' => 'sem1']);
$b = $engine->generate(['store' => 'lidl', 'budget' => 65, 'people' => 2, 'seed' => 'sem2']);
$common = count(array_intersect(array_column($a['days'], 'id'), array_column($b['days'], 'id')));
check('deux graines donnent des semaines différentes', $common <= 5, "$common recettes en commun");

echo "\n== Swap d'un dîner ==\n";
$ids = array_column($a['days'], 'id');
$swapped = $engine->swapDay(['store' => 'lidl', 'budget' => 65, 'people' => 2, 'seed' => 'sem1'], $ids, $ids[2]);
check('swap réussi', $swapped['ok'] === true);
check('recette remplacée absente', !in_array($ids[2], array_column($swapped['days'], 'id'), true));
check('swap tient le budget', $swapped['totals']['total'] <= 65, (string) $swapped['totals']['total']);

echo "\n== Liste de courses ==\n";
$plan = $engine->generate(['store' => 'colruyt', 'budget' => 85, 'people' => 4, 'seed' => 'courses']);
$sum = 0.0;
foreach ($plan['shopping']['aisles'] as $aisle) {
    $sum += $aisle['total'];
}
check('total liste = total annoncé', abs($sum - $plan['totals']['total']) < 0.02, "$sum vs {$plan['totals']['total']}");
check('rayons non vides', count($plan['shopping']['aisles']) >= 4, (string) count($plan['shopping']['aisles']));
check('placard séparé', count($plan['shopping']['pantry']) > 0);

$firstAisle = $plan['shopping']['aisles'][0]['id'];
check('parcours magasin cohérent (fruits & légumes en premier)', $firstAisle === 'fruits-legumes', $firstAisle);

echo "\n== Performance ==\n";
$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    $engine->generate(['store' => 'lidl', 'budget' => 70, 'people' => 3, 'seed' => "perf$i"]);
}
$ms = (microtime(true) - $start) * 100;
check('génération < 250 ms', $ms < 250, round($ms) . ' ms/génération');

echo "\n----------------------------------------\n";
echo "$pass réussis, $fail échoués\n";
exit($fail === 0 ? 0 : 1);
