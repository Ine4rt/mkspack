<?php
declare(strict_types=1);

/**
 * Prépare la liste des pages produits à renseigner pour la collecte.
 *
 *   php scripts/mapper_produits.php 30 colruyt,delhaize,lidl,aldi
 *
 * La collecte automatique a besoin de savoir, pour chaque produit, quelle page
 * du site consulter. C'est le seul travail manuel, et il n'est à faire qu'une
 * fois : ensuite la collecte se relance en une commande, chaque trimestre.
 *
 * Le script écrit data/a_mapper.txt : la liste des produits classés par poids
 * dans les paniers, avec pour chacun le conditionnement attendu et une ligne
 * JSON à compléter. Vous cherchez le produit sur le site de l'enseigne, vous
 * copiez l'URL, vous la collez. Puis :
 *
 *   php scripts/collect_stores.php colruyt --limite=3     # vérification
 *   php scripts/collect_stores.php colruyt,delhaize,lidl,aldi
 */

$root = __DIR__ . '/..';
require_once "$root/lib/Engine.php";

$topN = (int) ($argv[1] ?? 30);
$catalog = Catalog::load();
$engine = new Engine($catalog);
$config = json_decode(file_get_contents("$root/data/collectors.json"), true, 512, JSON_THROW_ON_ERROR);

$stores = isset($argv[2])
    ? array_values(array_filter(explode(',', $argv[2]), fn($s) => isset($config['collectors'][$s])))
    : array_keys($config['collectors']);

// Même classement que le carnet de relevés : on ne mappe que ce qui pèse.
echo "Calcul des produits prioritaires…\n";
$weight = [];
foreach ([2, 4] as $people) {
    foreach ([50, 70, 95] as $budget) {
        foreach (['omnivore', 'omnivore', 'vegetarien', 'sans-porc', 'pescetarien'] as $i => $diet) {
            $plan = $engine->generate([
                'store' => 'colruyt', 'budget' => $budget * $people / 2.5,
                'people' => $people, 'diet' => $diet, 'seed' => "$people-$budget-$i",
            ]);
            if (!$plan['ok']) {
                continue;
            }
            foreach ($plan['shopping']['aisles'] as $aisle) {
                foreach ($aisle['items'] as $item) {
                    $weight[$item['id']] = ($weight[$item['id']] ?? 0) + $item['price'];
                }
            }
        }
    }
}
arsort($weight);
$selected = array_slice(array_keys($weight), 0, $topN);

$out = [];
$out[] = "PAGES PRODUITS À RENSEIGNER";
$out[] = str_repeat('=', 76);
$out[] = "";
$out[] = "Pour chaque produit : cherchez-le sur le site de l'enseigne, ouvrez sa fiche,";
$out[] = "copiez l'URL. Prenez bien le FORMAT INDIQUÉ — c'est ce format que notre";
$out[] = "catalogue chiffre, et comparer un pot de 200 g à un pot de 500 g fausserait";
$out[] = "tout le budget.";
$out[] = "";
$out[] = "Quand une enseigne ne vend pas le produit dans ce format, laissez la ligne";
$out[] = "vide : le produit restera estimé, ce n'est pas grave.";
$out[] = "";
$out[] = "Une fois les URL collées dans data/collectors.json (section products de";
$out[] = "chaque enseigne), lancez :   php scripts/collect_stores.php colruyt --limite=3";
$out[] = "";

$total = array_sum($weight);
$covered = 0.0;
foreach ($selected as $id) {
    $covered += $weight[$id];
}
$out[] = sprintf("Ces %d produits représentent %.0f %% de la valeur des paniers.",
    count($selected), $covered / max($total, 0.01) * 100);
$out[] = "";

foreach ($stores as $storeId) {
    $collector = $config['collectors'][$storeId];
    $out[] = "";
    $out[] = str_repeat('-', 76);
    $out[] = strtoupper($catalog->stores[$storeId]['name']) . "   " . $collector['base'];
    $out[] = str_repeat('-', 76);
    $out[] = '  "products": {';
    foreach ($selected as $rank => $id) {
        $ing = $catalog->ingredients[$id];
        $done = $collector['products'][$id] ?? '';
        $out[] = sprintf('    "%s": "%s",%s// %s — %s',
            $id,
            $done,
            str_repeat(' ', max(1, 34 - strlen($id) - strlen($done))),
            $ing['name'],
            $ing['packLabel']);
    }
    $out[] = '  }';
}

$out[] = "";
$out[] = str_repeat('=', 76);
$out[] = "Rappel : la collecte lit robots.txt, attend deux secondes entre deux pages et";
$out[] = "s'arrête si le site la refuse. Ne réduisez pas ces délais : un passage par";
$out[] = "trimestre suffit à garder les prix justes.";

file_put_contents("$root/data/a_mapper.txt", implode("\n", $out) . "\n");

printf("\nÉcrit : data/a_mapper.txt — %d produits × %d enseignes = %d URL à coller.\n",
    count($selected), count($stores), count($selected) * count($stores));
echo "Comptez deux à trois heures pour tout faire, une seule fois.\n\n";
echo "Priorité (les 10 premiers pèsent le plus lourd) :\n";
foreach (array_slice($selected, 0, 10) as $rank => $id) {
    printf("  %2d. %-28s %-26s %4.1f %%\n", $rank + 1,
        $catalog->ingredients[$id]['name'],
        $catalog->ingredients[$id]['packLabel'],
        $weight[$id] / max($total, 0.01) * 100);
}
