<?php
declare(strict_types=1);

/**
 * Assemble la démo publique en une page autonome.
 *
 *   php scripts/build_demo.php
 *
 * Le CSS, le moteur, l'interface et les données sont inlinés dans un seul
 * fichier (demo/budgeat-demo.html) : aucune requête réseau, aucun serveur.
 * Régénérez-la après chaque modification des recettes, des prix ou du style.
 */

$root = __DIR__ . '/..';

$data = [
    'ingredients' => json_decode(file_get_contents("$root/data/ingredients.json"), true, 512, JSON_THROW_ON_ERROR),
    'recipes'     => json_decode(file_get_contents("$root/data/recipes.json"), true, 512, JSON_THROW_ON_ERROR),
    'stores'      => json_decode(file_get_contents("$root/data/stores.json"), true, 512, JSON_THROW_ON_ERROR),
];

$html = strtr(file_get_contents("$root/demo/template.html"), [
    '{{CSS}}'    => file_get_contents("$root/assets/app.css"),
    '{{ENGINE}}' => file_get_contents("$root/demo/engine.js"),
    '{{UI}}'     => file_get_contents("$root/demo/ui.js"),
    // JSON_HEX_TAG évite qu'une chaîne des données ne referme la balise script.
    '{{DATA}}'   => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG),
    '{{STORE_COUNT}}'      => (string) count($data['stores']['stores']),
    '{{RECIPE_COUNT}}'     => (string) count($data['recipes']['recipes']),
    '{{INGREDIENT_COUNT}}' => (string) count($data['ingredients']['ingredients']),
]);

$out = "$root/demo/budgeat-demo.html";
file_put_contents($out, $html);

printf("Écrit : %s (%.0f Ko)\n", realpath($out), strlen($html) / 1024);
printf("  %d enseignes · %d recettes · %d ingrédients\n",
    count($data['stores']['stores']),
    count($data['recipes']['recipes']),
    count($data['ingredients']['ingredients']));
