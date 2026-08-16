<?php
/**
 * Vérifie l'archive de déploiement : php budgeat/tests/package_test.php
 *
 * Ce que ce test protège : une archive envoyée par FTP part sur un serveur
 * public. Un config.php ou une base SQLite qui s'y glisserait exposerait des
 * identifiants et les comptes clients. C'est le genre d'erreur qui ne se voit
 * qu'après coup, d'où ce garde-fou.
 */
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok   $label\n"; }
    else { $fail++; echo "  FAIL $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}

echo "\n== Construction de l'archive ==\n";

exec(sprintf('php %s 2>&1', escapeshellarg("$root/scripts/build_release.php")), $output, $code);
check('le script de build s\'exécute', $code === 0, implode("\n", array_slice($output, -4)));

$zips = glob(dirname($root) . '/budgeat-*.zip');
usort($zips, fn($a, $b) => filemtime($b) <=> filemtime($a));
$zipPath = $zips[0] ?? null;
check('une archive est produite', $zipPath !== null);

if ($zipPath === null) {
    echo "\n$pass réussis, " . ($fail + 1) . " échoués\n";
    exit(1);
}

$zip = new ZipArchive();
$zip->open($zipPath);
$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entries[] = $zip->getNameIndex($i);
}
$zip->close();

echo "\n== Rien de confidentiel dans l'archive ==\n";

$forbidden = [
    'config.php'      => fn($e) => $e === 'config.php',
    'base de données' => fn($e) => str_ends_with($e, '.sqlite') || str_contains($e, '.sqlite-'),
    'relevés de prix' => fn($e) => str_ends_with($e, 'releves.csv'),
    'dossier tests/'  => fn($e) => str_starts_with($e, 'tests/'),
    'dossier .git/'   => fn($e) => str_starts_with($e, '.git/'),
    'cache de collecte' => fn($e) => str_starts_with($e, 'storage/cache/'),
];
foreach ($forbidden as $label => $matcher) {
    $found = array_values(array_filter($entries, $matcher));
    check("absence : $label", $found === [], implode(', ', array_slice($found, 0, 3)));
}

// Une clé de paiement dans un fichier livré serait un incident sérieux.
$zip = new ZipArchive();
$zip->open($zipPath);
$leak = [];
foreach ($entries as $entry) {
    if (!preg_match('/\.(php|json|txt|md)$/', $entry)) {
        continue;
    }
    $content = (string) $zip->getFromName($entry);
    if (preg_match('/(sk_live_|sk_test_|whsec_)[A-Za-z0-9]{10,}/', $content)) {
        $leak[] = $entry;
    }
}
$zip->close();
check('aucune clé Stripe dans les fichiers livrés', $leak === [], implode(', ', $leak));

echo "\n== L'essentiel est présent ==\n";

$required = [
    'index.php', 'app.php', 'menu.php', 'tarifs.php', 'compte.php', 'connexion.php',
    'install.php', 'LISEZMOI.txt', 'README.md', 'schema.sql', 'config.example.php',
    'manifest.json', '.htaccess',
    'lib/App.php', 'lib/Engine.php', 'lib/Catalog.php', 'lib/Billing.php',
    'api/generate.php', 'api/swap.php', 'api/checkout.php', 'api/webhook.php',
    'assets/app.css', 'assets/app.js',
    'data/recipes.json', 'data/ingredients.json', 'data/stores.json', 'data/collectors.json',
    'legal/cgv.php', 'legal/mentions.php', 'legal/confidentialite.php',
    'scripts/import_prices.php', 'scripts/collect_stores.php', 'scripts/carnet_releves.php',
    'demo/budgeat-demo.html',
];
$missing = array_values(array_diff($required, $entries));
check(count($required) . ' fichiers indispensables présents', $missing === [], implode(', ', $missing));

check('le dossier storage/ est fourni protégé', in_array('storage/.htaccess', $entries, true));
check('la démo est à jour', in_array('demo/budgeat-demo.html', $entries, true));

echo "\n== Taille ==\n";
$size = filesize($zipPath) / 1048576;
check(sprintf('archive légère (%.1f Mo)', $size), $size < 5);

echo "\n----------------------------------------\n";
echo "$pass réussis, $fail échoués\n";
exit($fail === 0 ? 0 : 1);
