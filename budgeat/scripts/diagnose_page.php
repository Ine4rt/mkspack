<?php
declare(strict_types=1);

/**
 * Diagnostic d'une page produit.
 *
 *   php scripts/diagnose_page.php "https://www.colruyt.be/fr/…"
 *   php scripts/diagnose_page.php page.html          # fichier local
 *
 * À utiliser quand une enseigne ne rend plus aucun prix : le script montre ce
 * que la page contient réellement (données structurées, balises, montants
 * candidats) et propose les sélecteurs à mettre dans data/collectors.json.
 *
 * C'est aussi le bon réflexe avant de lancer une collecte sur un site pour la
 * première fois : une page suffit à savoir si l'extraction va fonctionner.
 */

$root = __DIR__ . '/..';
require_once __DIR__ . '/lib/Scraper.php';

$target = $argv[1] ?? null;
if ($target === null) {
    fwrite(STDERR, "Usage : php scripts/diagnose_page.php \"https://…\" | fichier.html\n");
    exit(1);
}

$config = json_decode(file_get_contents("$root/data/collectors.json"), true);
$scraper = new Scraper(
    cacheDir: "$root/storage/cache",
    contact: $config['contact'] ?? 'diagnostic@example.com',
    delaySeconds: 1.0,
);

if (str_starts_with($target, 'http')) {
    echo "Lecture de $target\n";
    if (!$scraper->robotsAllows($target, (string) parse_url($target, PHP_URL_PATH))) {
        echo "\n  robots.txt interdit cette page. Rien ne sera téléchargé.\n";
        exit(1);
    }
    $html = $scraper->get($target);
    if ($html === null) {
        echo "\n  Échec : " . $scraper->lastError() . "\n";
        if ($scraper->isBlocked()) {
            echo "  Le site bloque les requêtes automatisées. Sur cette enseigne, il faudra\n";
            echo "  passer par le relevé manuel (php scripts/carnet_releves.php).\n";
        }
        exit(1);
    }
    echo $scraper->fromCache() ? "  (depuis le cache)\n" : "  " . strlen($html) . " octets reçus\n";
} else {
    $html = file_get_contents($target);
    if ($html === false) {
        fwrite(STDERR, "Fichier illisible : $target\n");
        exit(1);
    }
}

echo "\n" . str_repeat('=', 72) . "\n";
echo "DONNÉES STRUCTURÉES\n";
echo str_repeat('=', 72) . "\n";

$jsonLd = $scraper->jsonLd($html);
printf("  JSON-LD schema.org   %s\n", $jsonLd !== null
    ? sprintf('%.2f € — extraction automatique, rien à configurer', $jsonLd)
    : 'absent');

$micro = $scraper->microdata($html);
printf("  Microdata itemprop   %s\n", $micro !== null ? sprintf('%.2f €', $micro) : 'absent');

$meta = $scraper->metaTags($html);
printf("  Balises meta         %s\n", $meta !== null ? sprintf('%.2f €', $meta) : 'absent');

if ($jsonLd !== null || $micro !== null || $meta !== null) {
    echo "\n  → Cette page se lit sans configuration particulière.\n";
    echo "    Renseignez simplement les URL produits dans data/collectors.json.\n";
}

echo "\n" . str_repeat('=', 72) . "\n";
echo "MONTANTS TROUVÉS DANS LA PAGE\n";
echo str_repeat('=', 72) . "\n";

// Les nœuds dont le texte ressemble à un prix : de quoi bâtir un sélecteur.
$previous = libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
libxml_clear_errors();
libxml_use_internal_errors($previous);

$xpath = new DOMXPath($dom);
$candidates = [];

foreach ($xpath->query('//*[not(self::script or self::style)]/text()') as $textNode) {
    $text = trim($textNode->textContent);
    if ($text === '' || strlen($text) > 40) {
        continue;
    }
    if (!preg_match('/\d+[.,]\d{2}/', $text) && !str_contains($text, '€')) {
        continue;
    }
    $element = $textNode->parentNode;
    if (!$element instanceof DOMElement) {
        continue;
    }
    $class = $element->getAttribute('class');
    $key = $element->tagName . '|' . $class;
    if (!isset($candidates[$key])) {
        $candidates[$key] = ['text' => $text, 'tag' => $element->tagName, 'class' => $class, 'n' => 0];
    }
    $candidates[$key]['n']++;
}

if ($candidates === []) {
    echo "  Aucun montant visible dans le HTML reçu.\n";
    echo "  C'est le symptôme d'une page dont le prix est chargé après coup par du\n";
    echo "  JavaScript : le HTML seul ne suffit pas. Deux options —\n";
    echo "    · trouver l'appel réseau qui renvoie le prix (onglet Réseau du navigateur,\n";
    echo "      filtre XHR) et pointer directement cette URL, souvent du JSON ;\n";
    echo "    · relever ce produit à la main.\n";
} else {
    $shown = 0;
    foreach ($candidates as $candidate) {
        if ($shown++ >= 12) {
            break;
        }
        printf("  %-22s %-34s %s\n",
            $candidate['text'],
            $candidate['class'] !== '' ? substr($candidate['class'], 0, 34) : '(sans classe)',
            $candidate['tag']);
    }

    echo "\n  Sélecteurs à essayer dans data/collectors.json → rules.xpath :\n";
    $suggested = 0;
    foreach ($candidates as $candidate) {
        if ($candidate['class'] === '' || $suggested >= 4) {
            continue;
        }
        if (!preg_match('/pri(ce|x)|amount|montant|tarif/i', $candidate['class'])) {
            continue;
        }
        $first = explode(' ', trim($candidate['class']))[0];
        printf("    \"//*[contains(@class,'%s')]\"\n", $first);
        $suggested++;
    }
    if ($suggested === 0) {
        echo "    Aucune classe évocatrice ; repérez la bonne dans la liste ci-dessus et\n";
        echo "    écrivez \"//*[contains(@class,'la-classe')]\".\n";
    }
}

echo "\n";
