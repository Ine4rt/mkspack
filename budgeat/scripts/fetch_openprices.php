<?php
declare(strict_types=1);

/**
 * Collecte automatique de prix réels depuis Open Prices (Open Food Facts).
 *
 *   php scripts/fetch_openprices.php                      # les 4 enseignes par défaut
 *   php scripts/fetch_openprices.php colruyt,aldi,delhaize,lidl
 *   php scripts/fetch_openprices.php --min=2 --mois=12    # seuils plus larges
 *   php scripts/fetch_openprices.php --fixture=chemin.json  # test hors ligne
 *
 * Pourquoi Open Prices plutôt que les sites des enseignes : les prix y sont
 * contribués par les utilisateurs sous licence ouverte (ODbL), avec la photo du
 * ticket ou de l'étiquette en preuve. On peut donc les réutiliser, les citer et
 * les republier — ce qui n'est le cas ni d'un scraping de site marchand, ni des
 * bases des comparateurs. En contrepartie la couverture est partielle : le
 * script vous dit produit par produit ce qu'il a trouvé, et laisse le reste
 * estimé plutôt que d'inventer.
 *
 * Sortie : data/releves.csv, au format attendu par import_prices.php.
 * Enchaînez avec :  php scripts/import_prices.php
 */

const API = 'https://prices.openfoodfacts.org/api/v1';
const COUNTRIES = ['Belgium', 'België', 'Belgique', 'BE'];

/** Ce qu'OpenStreetMap peut porter comme nom de marque pour chaque enseigne. */
const BRAND_ALIASES = [
    'colruyt'  => ['colruyt', 'colruyt laagste prijzen', 'colruyt meilleurs prix'],
    'aldi'     => ['aldi', 'aldi nord', 'aldi markt'],
    'delhaize' => ['delhaize', 'ad delhaize', 'delhaize le lion', 'proxy delhaize'],
    'lidl'     => ['lidl'],
    'carrefour'=> ['carrefour', 'carrefour market', 'carrefour hyper'],
    'okay'     => ['okay', 'okay compact'],
    'jumbo'    => ['jumbo'],
    'spar'     => ['spar'],
    'intermarche' => ['intermarché', 'intermarche'],
    'albert-heijn' => ['albert heijn', 'ah'],
    'cora'     => ['cora'],
    'match'    => ['match'],
    'smatch'   => ['smatch'],
    'alvo'     => ['alvo'],
    'bio-planet' => ['bio-planet', 'bio planet'],
];

$root = __DIR__ . '/..';
require_once "$root/lib/Catalog.php";

// ------------------------------------------------------------------ arguments

$stores = ['colruyt', 'aldi', 'delhaize', 'lidl'];
$minSamples = 3;
$months = 18;
$fixture = null;
$outPath = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--min=')) {
        $minSamples = max(1, (int) substr($arg, 6));
    } elseif (str_starts_with($arg, '--mois=')) {
        $months = max(1, (int) substr($arg, 7));
    } elseif (str_starts_with($arg, '--fixture=')) {
        $fixture = substr($arg, 10);
    } elseif (str_starts_with($arg, '--out=')) {
        $outPath = substr($arg, 6);
    } elseif (!str_starts_with($arg, '--')) {
        $stores = array_values(array_filter(explode(',', $arg)));
    }
}

$catalog = Catalog::load();
$mapDoc = json_decode(file_get_contents("$root/data/openprices_map.json"), true, 512, JSON_THROW_ON_ERROR);
$map = $mapDoc['map'];

foreach ($stores as $storeId) {
    if (!isset($catalog->stores[$storeId])) {
        fwrite(STDERR, "Enseigne inconnue : $storeId\n");
        exit(1);
    }
    if (!isset(BRAND_ALIASES[$storeId])) {
        fwrite(STDERR, "Aucun alias de marque connu pour $storeId — complétez BRAND_ALIASES.\n");
        exit(1);
    }
}

$since = date('Y-m-d', strtotime("-$months months"));

echo "Open Prices — collecte pour : " . implode(', ', $stores) . "\n";
echo "Période : depuis $since · minimum $minSamples relevé(s) par produit et enseigne\n";
echo $fixture ? "MODE FIXTURE : aucune requête réseau ($fixture)\n\n" : "\n";

// -------------------------------------------------------------------- collecte

$fixtureData = $fixture ? json_decode(file_get_contents($fixture), true, 512, JSON_THROW_ON_ERROR) : null;

$collected = [];   // [ingredient][store] = [prix ramenés au conditionnement]
$stats = ['requests' => 0, 'prices_seen' => 0, 'kept' => 0, 'empty_categories' => []];

foreach ($map as $ingredientId => $entry) {
    if (!isset($catalog->ingredients[$ingredientId])) {
        continue;
    }
    $ing = $catalog->ingredients[$ingredientId];
    $items = $fixtureData !== null
        ? array_values(array_filter($fixtureData['items'] ?? [], fn($i) => ($i['category_tag'] ?? null) === $entry['category']))
        : fetchCategory($entry['category'], $since, $stats);

    $found = 0;
    foreach ($items as $item) {
        $stats['prices_seen']++;

        $location = $item['location'] ?? [];
        $country = (string) ($location['osm_address_country'] ?? '');
        if ($country !== '' && !in_array($country, COUNTRIES, true)) {
            continue;
        }
        $storeId = matchBrand((string) ($location['osm_brands'] ?? ''), $stores);
        if ($storeId === null) {
            continue;
        }

        // Un prix promotionnel ne reflète pas le prix habituel du rayon.
        if (!empty($item['price_is_discounted'])) {
            continue;
        }

        $price = (float) ($item['price'] ?? 0);
        if ($price <= 0) {
            continue;
        }

        $packPrice = toPackPrice($price, (string) ($item['price_per'] ?? 'UNIT'), $ing);
        if ($packPrice === null) {
            continue;
        }

        $collected[$ingredientId][$storeId][] = $packPrice;
        $stats['kept']++;
        $found++;
    }

    if ($found === 0) {
        $stats['empty_categories'][] = $ingredientId . ' (' . $entry['category'] . ')';
    }
    printf("  %-28s %-34s %s\n", $ing['name'], $entry['category'],
        $found > 0 ? "$found relevé(s)" : '—');
}

/**
 * Ramène un prix Open Prices au prix du conditionnement de notre catalogue.
 * KILOGRAM : prix au kilo (ou au litre), directement convertible.
 * UNIT : prix du produit tel que vendu — on ne peut le retenir que si notre
 * fiche décrit le même type de format, sinon on préfère ne rien retenir.
 */
function toPackPrice(float $price, string $pricePer, array $ing): ?float
{
    if ($pricePer === 'KILOGRAM') {
        if ($ing['unit'] === 'piece') {
            return null;                       // prix au kilo inutilisable à la pièce
        }
        return round($price / 1000 * $ing['pack'], 2);
    }
    if ($pricePer === 'UNIT') {
        return round($price, 2);               // le paquet entier
    }
    return null;
}

function matchBrand(string $brands, array $wanted): ?string
{
    $brands = mb_strtolower(trim($brands));
    if ($brands === '') {
        return null;
    }
    foreach ($wanted as $storeId) {
        foreach (BRAND_ALIASES[$storeId] as $alias) {
            if ($brands === $alias || str_contains($brands, $alias)) {
                return $storeId;
            }
        }
    }
    return null;
}

function fetchCategory(string $category, string $since, array &$stats): array
{
    $items = [];
    for ($page = 1; $page <= 5; $page++) {
        $url = API . '/prices?' . http_build_query([
            'category_tag' => $category,
            'date__gte'    => $since,
            'order_by'     => '-date',
            'page'         => $page,
            'size'         => 100,
        ]);
        $response = httpGet($url);
        $stats['requests']++;
        if ($response === null) {
            break;
        }
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['items'])) {
            break;
        }
        $items = array_merge($items, $data['items']);
        if ($page >= (int) ($data['pages'] ?? 1)) {
            break;
        }
        usleep(300000);                        // on reste poli avec une API bénévole
    }
    return $items;
}

function httpGet(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Budgeat/1.0 (planificateur de menus ; contact : voir mentions légales)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        fwrite(STDERR, "\n  ! requête échouée ($code) " . ($error ?: '') . "\n");
        return null;
    }
    return (string) $body;
}

// ---------------------------------------------------------------------- sortie

echo "\n" . str_repeat('-', 72) . "\n";
printf("%d requêtes · %d prix examinés · %d retenus\n",
    $stats['requests'], $stats['prices_seen'], $stats['kept']);

$rows = [];
$kept = 0;
$rejected = 0;

foreach ($collected as $ingredientId => $byStore) {
    foreach ($byStore as $storeId => $prices) {
        if (count($prices) < $minSamples) {
            $rejected++;
            continue;
        }
        sort($prices);
        $n = count($prices);
        $median = $n % 2 ? $prices[intdiv($n, 2)] : ($prices[intdiv($n, 2) - 1] + $prices[intdiv($n, 2)]) / 2;
        $rows[] = sprintf('%s;%s;%s;%s;openprices (%d relevés, médiane)',
            $ingredientId, $storeId, number_format($median, 2, ',', ''), date('Y-m-d'), $n);
        $kept++;
    }
}

echo "$kept couples produit/enseigne exploitables";
echo $rejected > 0 ? ", $rejected écartés faute de relevés suffisants.\n" : ".\n";

if ($stats['empty_categories'] !== []) {
    echo "\nCatégories sans aucun résultat (" . count($stats['empty_categories']) . ") — "
       . "vérifiez le tag sur world.openfoodfacts.org et corrigez data/openprices_map.json :\n";
    foreach (array_slice($stats['empty_categories'], 0, 15) as $line) {
        echo "  · $line\n";
    }
    if (count($stats['empty_categories']) > 15) {
        echo "  … et " . (count($stats['empty_categories']) - 15) . " autres\n";
    }
}

if ($rows === []) {
    echo "\nAucune ligne écrite. Élargissez avec --min=1 --mois=36, ou complétez les\n";
    echo "prix manquants à la main (php scripts/carnet_releves.php).\n";
    exit(1);
}

$csvPath = $outPath ?? "$root/data/releves.csv";
$existing = [];
if (file_exists($csvPath)) {
    // On conserve les relevés faits à la main : ils priment sur la collecte.
    foreach (file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
        if ($i === 0) {
            continue;
        }
        $cols = str_getcsv($line, ';');
        if (count($cols) >= 3 && trim((string) $cols[2]) !== '' && !str_contains((string) ($cols[4] ?? ''), 'openprices')) {
            $existing[$cols[0] . '|' . $cols[1]] = $line;
        }
    }
}

$final = ['ingredient_id;enseigne_id;prix_paquet;date;source'];
foreach ($rows as $row) {
    $cols = explode(';', $row);
    $key = $cols[0] . '|' . $cols[1];
    $final[] = $existing[$key] ?? $row;
    unset($existing[$key]);
}
foreach ($existing as $line) {
    $final[] = $line;                          // relevés manuels sur d'autres produits
}

file_put_contents($csvPath, implode("\n", $final) . "\n");

echo "\nÉcrit : " . $csvPath . ' (' . (count($final) - 1) . " lignes)\n";
echo "Étape suivante :  php scripts/import_prices.php --dry-run\n";
