<?php
/**
 * Tests de la chaîne de prix : php budgeat/tests/prices_test.php
 *
 * L'appel réseau à Open Prices n'est pas testable ici (et ne doit pas l'être :
 * un test ne doit pas dépendre d'une API tierce). Tout le reste l'est, à partir
 * d'une réponse d'API figée dans tests/fixtures/openprices.json : filtrage par
 * pays et par enseigne, exclusion des promotions, conversion vers le
 * conditionnement du catalogue, médiane, seuil de relevés, puis recalage.
 */
declare(strict_types=1);

$root = __DIR__ . '/..';
$tmp = sys_get_temp_dir() . '/budgeat_releves_' . getmypid() . '.csv';
$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok   $label\n"; }
    else { $fail++; echo "  FAIL $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}

echo "\n== Collecte Open Prices (fixture) ==\n";

exec(sprintf(
    'php %s/scripts/fetch_openprices.php colruyt,aldi,delhaize,lidl --fixture=%s --out=%s 2>&1',
    escapeshellarg($root), escapeshellarg("$root/tests/fixtures/openprices.json"), escapeshellarg($tmp)
), $output, $code);

check('le script s\'exécute', $code === 0, implode("\n", array_slice($output, -3)));

$rows = [];
foreach (array_slice(file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1) as $line) {
    $cols = str_getcsv($line, ';');
    $rows[$cols[0] . '|' . $cols[1]] = (float) str_replace(',', '.', $cols[2]);
}

// La fixture contient, pour le poulet chez Colruyt : 9,49 / 9,99 / 9,79 €/kg
// plus un prix promotionnel à 6,99 qui doit être écarté. Médiane 9,79 €/kg,
// et le conditionnement du catalogue fait 900 g.
check('prix au kilo converti vers le conditionnement',
    isset($rows['poulet-filet|colruyt']) && abs($rows['poulet-filet|colruyt'] - 8.81) < 0.02,
    (string) ($rows['poulet-filet|colruyt'] ?? 'absent'));

check('promotion écartée',
    ($rows['poulet-filet|colruyt'] ?? 0) > 7.0,
    'un prix promo aurait tiré la médiane vers le bas');

check('enseigne non demandée ignorée (Carrefour)', !isset($rows['poulet-filet|carrefour']));
check('prix hors Belgique ignoré', ($rows['poulet-filet|colruyt'] ?? 0) > 8.0);

check('produit vendu à la pièce : prix au kilo ignoré, prix du paquet retenu',
    isset($rows['oeuf|colruyt']) && abs($rows['oeuf|colruyt'] - 3.89) < 0.02,
    (string) ($rows['oeuf|colruyt'] ?? 'absent'));

check('sous le seuil de relevés, rien n\'est écrit (beurre Aldi : 2 relevés)',
    !isset($rows['beurre|aldi']));
check('au-dessus du seuil, la ligne est écrite (beurre Delhaize : 3 relevés)',
    isset($rows['beurre|delhaize']) && abs($rows['beurre|delhaize'] - 2.78) < 0.02,
    (string) ($rows['beurre|delhaize'] ?? 'absent'));

check('les quatre enseignes demandées sont les seules présentes',
    array_diff(
        array_unique(array_map(fn($k) => explode('|', $k)[1], array_keys($rows))),
        ['colruyt', 'aldi', 'delhaize', 'lidl']
    ) === []);

echo "\n== Relevés manuels prioritaires ==\n";

// Un prix saisi à la main ne doit jamais être écrasé par la collecte.
file_put_contents($tmp, "ingredient_id;enseigne_id;prix_paquet;date;source\n"
    . "poulet-filet;colruyt;7,50;2026-08-01;relevé magasin Liège\n"
    . "carotte;colruyt;1,49;2026-08-01;relevé magasin Liège\n");

exec(sprintf(
    'php %s/scripts/fetch_openprices.php colruyt,aldi,delhaize,lidl --fixture=%s --out=%s 2>&1',
    escapeshellarg($root), escapeshellarg("$root/tests/fixtures/openprices.json"), escapeshellarg($tmp)
), $out2, $code2);

$rows2 = [];
foreach (array_slice(file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1) as $line) {
    $cols = str_getcsv($line, ';');
    $rows2[$cols[0] . '|' . $cols[1]] = $line;
}
check('un relevé manuel n\'est pas écrasé',
    str_contains($rows2['poulet-filet|colruyt'] ?? '', '7,50'),
    $rows2['poulet-filet|colruyt'] ?? 'absent');
check('un relevé manuel sur un autre produit est conservé',
    isset($rows2['carotte|colruyt']));

echo "\n== Recalage du catalogue ==\n";

// Avec seulement quelques relevés par enseigne, le script doit refuser de
// recalculer les indices plutôt que de les asseoir sur trop peu de données.
exec(sprintf('php %s/scripts/import_prices.php %s --dry-run 2>&1',
    escapeshellarg($root), escapeshellarg($tmp)), $out3, $code3);
$report = implode("\n", $out3);

check('le recalage s\'exécute', $code3 === 0);
check('aucun fichier modifié en dry-run', str_contains($report, 'aucun fichier modifié'));
check('les enseignes trop peu couvertes gardent leur indice',
    substr_count($report, '(gardé)') >= 15, $report);

@unlink($tmp);

echo "\n----------------------------------------\n";
echo "$pass réussis, $fail échoués\n";
exit($fail === 0 ? 0 : 1);
