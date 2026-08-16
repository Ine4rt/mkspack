<?php
declare(strict_types=1);

/**
 * Recale le catalogue sur des relevés de prix RÉELS.
 *
 *   php scripts/import_prices.php [chemin/vers/releves.csv] [--dry-run]
 *
 * Les prix livrés avec le projet sont des ordres de grandeur posés à la main.
 * Tant que vous ne les avez pas recalés, la promesse « pile dans votre budget »
 * repose sur des estimations. Ce script transforme un simple fichier de relevés
 * (une ligne = un produit vu dans un magasin) en catalogue calibré.
 *
 * Format du CSV (point-virgule, en-tête obligatoire) :
 *   ingredient_id;enseigne_id;prix_paquet;date;source
 *   poulet-filet;colruyt;7.95;2026-08-14;magasin Liège
 *
 * prix_paquet = le prix affiché pour le conditionnement décrit dans
 * ingredients.json (packLabel). Si le magasin vend un autre format, ajoutez une
 * ligne dans data/formats.csv plutôt que de convertir à la main.
 *
 * Méthode : prix de référence et indices d'enseigne sont interdépendants
 * (un prix relevé chez Delhaize est « cher » à la fois parce que le produit
 * l'est et parce que l'enseigne l'est). On les sépare par quelques passes
 * d'ajustement alterné, en utilisant des médianes pour résister aux promos.
 */

$csvPath = $argv[1] ?? __DIR__ . '/../data/releves.csv';
$dryRun = in_array('--dry-run', $argv, true);

$dataDir = __DIR__ . '/../data';
$ingredientsFile = "$dataDir/ingredients.json";
$storesFile = "$dataDir/stores.json";

$ingredientsDoc = json_decode(file_get_contents($ingredientsFile), true, 512, JSON_THROW_ON_ERROR);
$storesDoc = json_decode(file_get_contents($storesFile), true, 512, JSON_THROW_ON_ERROR);

$ingredients = [];
foreach ($ingredientsDoc['ingredients'] as $ing) {
    $ingredients[$ing['id']] = $ing;
}
$stores = [];
foreach ($storesDoc['stores'] as $store) {
    $stores[$store['id']] = $store;
}

// ------------------------------------------------------------------ lecture

if (!file_exists($csvPath)) {
    fwrite(STDERR, "Fichier de relevés introuvable : $csvPath\n");
    fwrite(STDERR, "Créez-le à partir de data/releves.csv (modèle fourni).\n");
    exit(1);
}

$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle, 0, ';');
if ($header === false) {
    fwrite(STDERR, "CSV vide.\n");
    exit(1);
}
$header = array_map(fn($h) => strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header);

$observations = [];   // [ingredient_id][store_id] = [prix unitaires observés]
$errors = [];
$line = 1;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $line++;
    if ($row === [null] || count($row) < 3) {
        continue;
    }
    $record = array_combine(array_slice($header, 0, count($row)), $row);

    $ingredientId = trim((string) ($record['ingredient_id'] ?? ''));
    $storeId = trim((string) ($record['enseigne_id'] ?? ''));
    $price = (float) str_replace(',', '.', (string) ($record['prix_paquet'] ?? ''));

    if ($ingredientId === '' || $storeId === '') {
        continue;
    }
    if (!isset($ingredients[$ingredientId])) {
        $errors[] = "ligne $line : ingrédient inconnu « $ingredientId »";
        continue;
    }
    if (!isset($stores[$storeId])) {
        $errors[] = "ligne $line : enseigne inconnue « $storeId »";
        continue;
    }
    if ($price <= 0) {
        $errors[] = "ligne $line : prix invalide";
        continue;
    }

    // Ramené au prix pour l'unité de référence (kg, litre ou pièce).
    $ing = $ingredients[$ingredientId];
    $observations[$ingredientId][$storeId][] = $price / $ing['pack'] * $ing['ref'];
}
fclose($handle);

foreach ($errors as $error) {
    fwrite(STDERR, "  ! $error\n");
}
if ($observations === []) {
    fwrite(STDERR, "Aucun relevé exploitable.\n");
    exit(1);
}

// Une seule valeur par couple produit/enseigne : la médiane des relevés.
$observed = [];
foreach ($observations as $ingredientId => $byStore) {
    foreach ($byStore as $storeId => $prices) {
        $observed[$ingredientId][$storeId] = median($prices);
    }
}

$countObs = 0;
foreach ($observed as $byStore) {
    $countObs += count($byStore);
}
$storesSeen = [];
foreach ($observed as $byStore) {
    foreach (array_keys($byStore) as $storeId) {
        $storesSeen[$storeId] = true;
    }
}
echo "Relevés retenus : $countObs couples produit/enseigne, "
   . count($observed) . " produits, "
   . count($storesSeen) . " enseignes.\n\n";

// ------------------------------------------------- ajustement alterné (5 passes)

$refPrice = [];
foreach ($ingredients as $id => $ing) {
    $refPrice[$id] = (float) $ing['price'];
}
$baseIndex = [];
foreach ($stores as $id => $store) {
    $baseIndex[$id] = (float) $store['base'];
}
$aisleIndex = [];   // [store][aisle] = coefficient

for ($pass = 0; $pass < 5; $pass++) {
    // 1. Prix de référence : ce que coûterait le produit dans une enseigne d'indice 1.
    foreach ($observed as $ingredientId => $byStore) {
        $normalized = [];
        foreach ($byStore as $storeId => $price) {
            $aisle = $ingredients[$ingredientId]['aisle'];
            $index = $baseIndex[$storeId] * ($aisleIndex[$storeId][$aisle] ?? 1.0);
            $normalized[] = $price / max($index, 0.01);
        }
        $refPrice[$ingredientId] = round(median($normalized), 4);
    }

    // 2. Indice global de chaque enseigne.
    foreach ($stores as $storeId => $store) {
        $ratios = [];
        foreach ($observed as $ingredientId => $byStore) {
            if (isset($byStore[$storeId])) {
                $ratios[] = $byStore[$storeId] / max($refPrice[$ingredientId], 0.0001);
            }
        }
        if (count($ratios) >= 5) {          // en dessous, l'indice ne veut rien dire
            $baseIndex[$storeId] = round(median($ratios), 4);
        }
    }

    // 3. Écart propre à chaque rayon, à l'intérieur de l'enseigne.
    foreach ($stores as $storeId => $store) {
        $byAisle = [];
        foreach ($observed as $ingredientId => $stores2) {
            if (!isset($stores2[$storeId])) {
                continue;
            }
            $aisle = $ingredients[$ingredientId]['aisle'];
            $byAisle[$aisle][] = $stores2[$storeId]
                / max($refPrice[$ingredientId] * $baseIndex[$storeId], 0.0001);
        }
        foreach ($byAisle as $aisle => $ratios) {
            if (count($ratios) >= 4) {      // au moins 4 produits du rayon
                $aisleIndex[$storeId][$aisle] = round(median($ratios), 3);
            }
        }
    }
}

// ------------------------------------------------------------------ rapport

echo str_pad('ENSEIGNE', 24) . str_pad('INDICE', 10) . "RELEVÉS  RAYONS CALIBRÉS\n";
echo str_repeat('-', 70) . "\n";
foreach ($stores as $storeId => $store) {
    $n = 0;
    foreach ($observed as $byStore) {
        if (isset($byStore[$storeId])) {
            $n++;
        }
    }
    $status = $n >= 5 ? sprintf('%.3f', $baseIndex[$storeId]) : sprintf('%.3f (gardé)', $store['base']);
    echo str_pad($store['name'], 24)
       . str_pad($status, 16)
       . str_pad((string) $n, 9)
       . count($aisleIndex[$storeId] ?? []) . "\n";
}

$changed = 0;
$biggest = [];
foreach ($observed as $ingredientId => $_) {
    $before = (float) $ingredients[$ingredientId]['price'];
    $after = $refPrice[$ingredientId];
    if (abs($after - $before) / max($before, 0.01) > 0.02) {
        $changed++;
        $biggest[$ingredientId] = ($after - $before) / max($before, 0.01);
    }
}
arsort($biggest);
echo "\n$changed prix de référence ajustés de plus de 2 %.\n";
foreach (array_slice($biggest, 0, 8, true) as $id => $delta) {
    printf("  %-28s %+6.1f %%  (%.2f → %.2f)\n",
        $ingredients[$id]['name'], $delta * 100,
        $ingredients[$id]['price'], $refPrice[$id]);
}

// Produits jamais relevés : ils restent sur une estimation.
$never = array_diff(array_keys($ingredients), array_keys($observed));
if ($never !== []) {
    echo "\n" . count($never) . " produits sans aucun relevé (toujours estimés) :\n  "
       . implode(', ', array_slice($never, 0, 12))
       . (count($never) > 12 ? ', …' : '') . "\n";
}

if ($dryRun) {
    echo "\n--dry-run : aucun fichier modifié.\n";
    exit(0);
}

// ------------------------------------------------------------------ écriture

foreach ($ingredientsDoc['ingredients'] as $i => $ing) {
    if (isset($observed[$ing['id']])) {
        $ingredientsDoc['ingredients'][$i]['price'] = round($refPrice[$ing['id']], 2);
        $ingredientsDoc['ingredients'][$i]['calibrated'] = true;
    }
}
$ingredientsDoc['updated'] = date('Y-m-d');

foreach ($storesDoc['stores'] as $i => $store) {
    $id = $store['id'];
    $n = 0;
    foreach ($observed as $byStore) {
        if (isset($byStore[$id])) {
            $n++;
        }
    }
    if ($n >= 5) {
        $storesDoc['stores'][$i]['base'] = round($baseIndex[$id], 3);
        $storesDoc['stores'][$i]['aisles'] = $aisleIndex[$id] ?? new stdClass();
        $storesDoc['stores'][$i]['samples'] = $n;
    }
}
$storesDoc['updated'] = date('Y-m-d');
$storesDoc['calibrated'] = true;

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
file_put_contents($ingredientsFile, json_encode($ingredientsDoc, $flags) . "\n");
file_put_contents($storesFile, json_encode($storesDoc, $flags) . "\n");

echo "\nCatalogue mis à jour. Relancez les tests : php tests/engine_test.php\n";

function median(array $values): float
{
    sort($values);
    $n = count($values);
    if ($n === 0) {
        return 0.0;
    }
    $mid = intdiv($n, 2);
    return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
}
