<?php
declare(strict_types=1);

/**
 * Prépare le carnet de relevés à emporter en magasin.
 *
 *   php scripts/carnet_releves.php [nb_produits] [enseigne,enseigne,...]
 *   php scripts/carnet_releves.php 30 colruyt,lidl,delhaize
 *
 * Relever 96 produits dans 21 enseignes, personne ne le fera. Ce script
 * simule des centaines de semaines et classe les produits par POIDS RÉEL dans
 * les paniers : les 30 premiers représentent l'essentiel de la facture.
 * Relevez ceux-là, le reste peut rester estimé sans fausser le budget.
 *
 * Produit deux fichiers :
 *   data/releves.csv        à compléter (colonne prix_paquet vide)
 *   data/carnet.txt         version imprimable, triée par rayon
 */

require_once __DIR__ . '/../lib/Engine.php';

$topN = (int) ($argv[1] ?? 30);
$catalog = Catalog::load();
$engine = new Engine($catalog);

$storeIds = isset($argv[2])
    ? array_values(array_filter(explode(',', $argv[2]), fn($s) => isset($catalog->stores[$s])))
    : array_keys($catalog->stores);

echo "Simulation des paniers…\n";

$weight = [];       // ingrédient => euros cumulés sur l'ensemble des simulations
$appears = [];      // ingrédient => nombre de semaines où il apparaît
$weeks = 0;

$diets = ['omnivore', 'omnivore', 'omnivore', 'flexitarien', 'sans-porc', 'vegetarien', 'pescetarien'];
foreach ([2, 3, 4, 1, 5] as $people) {
    foreach ([45, 60, 75, 90, 110] as $budget) {
        foreach ($diets as $d => $diet) {
            $plan = $engine->generate([
                'store'  => 'colruyt',
                'budget' => $budget * $people / 2.5,
                'people' => $people,
                'diet'   => $diet,
                'seed'   => "$people-$budget-$d",
            ]);
            if (!$plan['ok']) {
                continue;
            }
            $weeks++;
            foreach ($plan['shopping']['aisles'] as $aisle) {
                foreach ($aisle['items'] as $item) {
                    $weight[$item['id']] = ($weight[$item['id']] ?? 0) + $item['price'];
                    $appears[$item['id']] = ($appears[$item['id']] ?? 0) + 1;
                }
            }
        }
    }
}

arsort($weight);
$selected = array_slice(array_keys($weight), 0, $topN);
$totalWeight = array_sum($weight);
$covered = 0.0;
foreach ($selected as $id) {
    $covered += $weight[$id];
}

printf("%d semaines simulées. Les %d produits retenus couvrent %.0f %% de la valeur des paniers.\n\n",
    $weeks, count($selected), $covered / max($totalWeight, 0.01) * 100);

// --------------------------------------------------------------- fichier CSV

$csv = ["ingredient_id;enseigne_id;prix_paquet;date;source"];
foreach ($selected as $id) {
    foreach ($storeIds as $storeId) {
        $csv[] = "$id;$storeId;;" . date('Y-m-d') . ";";
    }
}
file_put_contents(__DIR__ . '/../data/releves.csv', implode("\n", $csv) . "\n");

// ---------------------------------------------------------- carnet imprimable

$byAisle = [];
foreach ($selected as $id) {
    $ing = $catalog->ingredients[$id];
    $byAisle[$ing['aisle']][] = $id;
}

$out = [];
$out[] = "CARNET DE RELEVÉS — " . date('d/m/Y');
$out[] = str_repeat('=', 72);
$out[] = "";
$out[] = "Notez le prix affiché pour le conditionnement indiqué. Si le magasin ne";
$out[] = "vend que d'autres formats, notez le prix au kilo (colonne de droite) et";
$out[] = "précisez-le dans la colonne source.";
$out[] = "Évitez les produits en promotion : on cherche le prix habituel.";
$out[] = "";

foreach (array_keys(Catalog::AISLE_ORDER) as $aisle) {
    if (!isset($byAisle[$aisle])) {
        continue;
    }
    $out[] = "";
    $out[] = strtoupper($catalog->aisleLabel($aisle));
    $out[] = str_repeat('-', 72);
    foreach ($byAisle[$aisle] as $id) {
        $ing = $catalog->ingredients[$id];
        $share = $weight[$id] / max($totalWeight, 0.01) * 100;
        $out[] = sprintf("  %-30s %-26s ............ €   [%.1f %% des paniers]",
            $ing['name'], $ing['packLabel'], $share);
    }
}

$out[] = "";
$out[] = str_repeat('=', 72);
$out[] = "Enseignes à couvrir : " . implode(', ', array_map(
    fn($s) => $catalog->stores[$s]['name'], $storeIds));
$out[] = "";
$out[] = "Une fois les prix notés, remplissez data/releves.csv puis lancez :";
$out[] = "    php scripts/import_prices.php";

file_put_contents(__DIR__ . '/../data/carnet.txt', implode("\n", $out) . "\n");

echo "Écrits :\n";
echo "  data/releves.csv  (" . (count($csv) - 1) . " lignes à compléter)\n";
echo "  data/carnet.txt   (version imprimable)\n\n";
echo "Priorité de relevé :\n";
foreach (array_slice($selected, 0, 12) as $rank => $id) {
    printf("  %2d. %-28s %5.1f %% de la valeur des paniers\n",
        $rank + 1, $catalog->ingredients[$id]['name'],
        $weight[$id] / max($totalWeight, 0.01) * 100);
}
