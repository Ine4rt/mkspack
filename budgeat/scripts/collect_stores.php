<?php
declare(strict_types=1);

/**
 * Collecte de prix sur les sites des enseignes.
 *
 *   php scripts/collect_stores.php colruyt              # une enseigne
 *   php scripts/collect_stores.php colruyt,delhaize     # plusieurs
 *   php scripts/collect_stores.php colruyt --limite=10  # essai court
 *   php scripts/collect_stores.php colruyt --sans-cache
 *
 * ------------------------------------------------------------------------
 * À LIRE AVANT LA PREMIÈRE UTILISATION
 *
 * Les prix sont des faits, mais les sites qui les publient sont protégés par
 * leurs conditions d'utilisation et, en Europe, par un droit propre aux bases
 * de données. Interroger un site marchand pour en extraire des prix est donc
 * un risque contractuel que vous assumez. Ce script est écrit pour le réduire
 * autant que possible, pas pour le supprimer :
 *
 *   · il lit robots.txt et refuse toute URL interdite ;
 *   · il attend entre deux requêtes (2 s par défaut) et ne parallélise rien ;
 *   · il s'annonce avec un User-Agent identifiable et une adresse de contact ;
 *   · il met en cache : relancer ne retélécharge pas ;
 *   · il s'arrête net sur un 403, un 429 ou une page anti-bot, sans jamais
 *     tenter de contourner quoi que ce soit.
 *
 * Utilisez-le à faible fréquence (un passage par trimestre suffit à garder un
 * catalogue juste) et sur peu de produits. Un scraper qui tourne en continu se
 * fait bloquer, et donne raison à l'enseigne.
 * ------------------------------------------------------------------------
 *
 * La structure des sites change sans prévenir : les règles d'extraction sont
 * donc dans data/collectors.json, pas dans ce fichier. Quand une enseigne ne
 * rend plus rien, lancez le diagnostic pour retrouver les bons sélecteurs :
 *
 *   php scripts/diagnose_page.php "https://…/un-produit"
 */

$root = __DIR__ . '/..';
require_once "$root/lib/Catalog.php";
require_once __DIR__ . '/lib/Scraper.php';

// ------------------------------------------------------------------ arguments

$stores = [];
$limit = 0;
$useCache = true;
$delay = 2.0;
$outPath = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--limite=')) {
        $limit = max(1, (int) substr($arg, 9));
    } elseif ($arg === '--sans-cache') {
        $useCache = false;
    } elseif (str_starts_with($arg, '--delai=')) {
        $delay = max(0.5, (float) substr($arg, 8));
    } elseif (str_starts_with($arg, '--out=')) {
        $outPath = substr($arg, 6);
    } elseif (!str_starts_with($arg, '--')) {
        $stores = array_values(array_filter(explode(',', $arg)));
    }
}

$config = json_decode(file_get_contents("$root/data/collectors.json"), true, 512, JSON_THROW_ON_ERROR);
$catalog = Catalog::load();

if ($stores === []) {
    echo "Enseignes configurées : " . implode(', ', array_keys($config['collectors'])) . "\n";
    echo "Usage : php scripts/collect_stores.php colruyt,delhaize [--limite=10]\n";
    exit(0);
}

foreach ($stores as $storeId) {
    if (!isset($config['collectors'][$storeId])) {
        fwrite(STDERR, "Aucune configuration pour « $storeId » dans data/collectors.json\n");
        exit(1);
    }
}

$contact = $config['contact'] ?? 'contact-non-renseigne@example.com';
if (str_contains($contact, 'example.com')) {
    fwrite(STDERR, "\n  ! Renseignez votre adresse de contact dans data/collectors.json avant de\n");
    fwrite(STDERR, "    lancer une collecte : elle part dans le User-Agent, c'est la moindre\n");
    fwrite(STDERR, "    des politesses et ça vous évite d'être pris pour un robot anonyme.\n\n");
    exit(1);
}

$scraper = new Scraper(
    cacheDir: "$root/storage/cache",
    contact: $contact,
    delaySeconds: $delay,
    useCache: $useCache
);

// -------------------------------------------------------------------- collecte

$rows = [];
$report = [];

foreach ($stores as $storeId) {
    $collector = $config['collectors'][$storeId];
    echo "\n" . str_repeat('=', 72) . "\n";
    echo strtoupper($catalog->stores[$storeId]['name']) . "  —  " . $collector['base'] . "\n";
    echo str_repeat('=', 72) . "\n";

    if (!$scraper->robotsAllows($collector['base'], $collector['robots_path'] ?? '/')) {
        echo "  robots.txt interdit l'accès à cette section : enseigne ignorée.\n";
        $report[$storeId] = ['status' => 'robots', 'found' => 0];
        continue;
    }

    $products = $collector['products'];
    if ($limit > 0) {
        $products = array_slice($products, 0, $limit, true);
    }

    $found = 0;
    $missed = [];

    foreach ($products as $ingredientId => $path) {
        if (!isset($catalog->ingredients[$ingredientId])) {
            continue;
        }
        $ing = $catalog->ingredients[$ingredientId];
        $url = str_starts_with($path, 'http') ? $path : rtrim($collector['base'], '/') . $path;

        $html = $scraper->get($url);
        if ($html === null) {
            printf("  %-28s %s\n", $ing['name'], $scraper->lastError());
            if ($scraper->isBlocked()) {
                echo "\n  Le site a répondu par un blocage. On s'arrête ici pour cette enseigne :\n";
                echo "  insister ne ferait qu'aggraver les choses.\n";
                $report[$storeId] = ['status' => 'blocked', 'found' => $found];
                continue 2;
            }
            $missed[] = $ingredientId;
            continue;
        }

        $price = $scraper->extractPrice($html, $collector['rules'] ?? []);
        if ($price === null) {
            printf("  %-28s prix introuvable dans la page\n", $ing['name']);
            $missed[] = $ingredientId;
            continue;
        }

        // Le prix affiché est celui du produit tel qu'il est vendu. Notre
        // catalogue raisonne aussi en conditionnement : les deux coïncident
        // tant que l'URL pointe vers le bon format.
        $rows[] = sprintf('%s;%s;%s;%s;site %s',
            $ingredientId, $storeId, number_format($price, 2, ',', ''),
            date('Y-m-d'), parse_url($url, PHP_URL_HOST));
        printf("  %-28s %6.2f €   %s\n", $ing['name'], $price,
            $scraper->fromCache() ? '(cache)' : '');
        $found++;
    }

    $report[$storeId] = ['status' => 'ok', 'found' => $found, 'missed' => $missed];
}

// ---------------------------------------------------------------------- bilan

echo "\n" . str_repeat('-', 72) . "\n";
foreach ($report as $storeId => $info) {
    $label = $catalog->stores[$storeId]['name'];
    switch ($info['status']) {
        case 'robots':
            printf("  %-20s ignorée (robots.txt)\n", $label);
            break;
        case 'blocked':
            printf("  %-20s interrompue après %d prix (blocage du site)\n", $label, $info['found']);
            break;
        default:
            printf("  %-20s %d prix collectés", $label, $info['found']);
            if (!empty($info['missed'])) {
                printf(", %d échecs", count($info['missed']));
            }
            echo "\n";
            if (!empty($info['missed'])) {
                echo "      à revoir : " . implode(', ', array_slice($info['missed'], 0, 8))
                   . (count($info['missed']) > 8 ? '…' : '') . "\n";
            }
    }
}

if ($rows === []) {
    echo "\nAucun prix collecté.\n";
    echo "Lancez le diagnostic sur une page produit pour retrouver les bons sélecteurs :\n";
    echo "  php scripts/diagnose_page.php \"https://…\"\n";
    exit(1);
}

// Fusion avec l'existant : un relevé fait à la main n'est jamais écrasé.
$csvPath = $outPath ?? "$root/data/releves.csv";
$manual = [];
if (file_exists($csvPath)) {
    foreach (array_slice(file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1) as $line) {
        $cols = str_getcsv($line, ';');
        if (count($cols) >= 5 && trim((string) $cols[2]) !== ''
            && !str_contains($cols[4], 'site ') && !str_contains($cols[4], 'openprices')) {
            $manual[$cols[0] . '|' . $cols[1]] = $line;
        }
    }
}

$final = ['ingredient_id;enseigne_id;prix_paquet;date;source'];
foreach ($rows as $row) {
    $cols = explode(';', $row);
    $key = $cols[0] . '|' . $cols[1];
    $final[] = $manual[$key] ?? $row;
    unset($manual[$key]);
}
foreach ($manual as $line) {
    $final[] = $line;
}

file_put_contents($csvPath, implode("\n", $final) . "\n");

echo "\nÉcrit : $csvPath (" . (count($final) - 1) . " lignes)\n";
echo "Étape suivante :  php scripts/import_prices.php --dry-run\n";
