<?php
declare(strict_types=1);

/**
 * Charge le catalogue (enseignes, ingrédients, recettes) et calcule les prix
 * par enseigne. Les régimes et allergènes des recettes sont DÉDUITS des
 * ingrédients : impossible d'avoir une recette taguée "végétarienne" qui
 * contient des lardons.
 */
final class Catalog
{
    /** Assaisonnements considérés comme déjà présents dans le placard par défaut. */
    public const PANTRY_DEFAULT = [
        'sel-poivre', 'huile-olive', 'huile-tournesol', 'vinaigre', 'moutarde',
        'farine', 'sucre', 'bouillon', 'curry-poudre', 'paprika', 'cumin',
        'curcuma', 'herbes', 'piment', 'sauce-soja', 'miel',
    ];

    public const AISLE_ORDER = [
        'fruits-legumes'  => 'Fruits & légumes',
        'boucherie'       => 'Boucherie',
        'poissonnerie'    => 'Poissonnerie',
        'cremerie'        => 'Crèmerie & frais',
        'boulangerie'     => 'Boulangerie',
        'surgeles'        => 'Surgelés',
        'epicerie-salee'  => 'Épicerie salée',
        'monde'           => 'Cuisine du monde',
        'epicerie-sucree' => 'Épicerie sucrée',
        'boissons'        => 'Boissons',
    ];

    /** Ce que chaque régime interdit, par type d'ingrédient. */
    public const DIET_EXCLUDES = [
        'omnivore'    => [],
        'flexitarien' => [],
        'sans-porc'   => ['pork'],
        'pescetarien' => ['meat', 'pork'],
        'vegetarien'  => ['meat', 'pork', 'fish', 'seafood'],
        'vegan'       => ['meat', 'pork', 'fish', 'seafood', 'dairy', 'egg', 'honey'],
    ];

    public const DIET_LABELS = [
        'omnivore'    => 'Je mange de tout',
        'flexitarien' => 'Flexitarien (viande limitée)',
        'sans-porc'   => 'Sans porc',
        'pescetarien' => 'Pescétarien',
        'vegetarien'  => 'Végétarien',
        'vegan'       => 'Végan',
    ];

    public const ALLERGENS = [
        'gluten'         => 'Gluten',
        'crustaces'      => 'Crustacés',
        'oeufs'          => 'Œufs',
        'poissons'       => 'Poissons',
        'arachides'      => 'Arachides',
        'soja'           => 'Soja',
        'lait'           => 'Lait',
        'fruits-a-coque' => 'Fruits à coque',
        'celeri'         => 'Céleri',
        'moutarde'       => 'Moutarde',
        'sesame'         => 'Sésame',
        'sulfites'       => 'Sulfites',
        'mollusques'     => 'Mollusques',
    ];

    /** @var array<string,array> */
    public array $ingredients = [];
    /** @var array<string,array> */
    public array $recipes = [];
    /** @var array<string,array> */
    public array $stores = [];

    private static ?Catalog $instance = null;

    public static function load(): Catalog
    {
        if (self::$instance === null) {
            self::$instance = new self(__DIR__ . '/../data');
        }
        return self::$instance;
    }

    private function __construct(string $dir)
    {
        foreach (self::readJson("$dir/ingredients.json")['ingredients'] as $ing) {
            $ing['pantryDefault'] = in_array($ing['id'], self::PANTRY_DEFAULT, true);
            $this->ingredients[$ing['id']] = $ing;
        }
        foreach (self::readJson("$dir/stores.json")['stores'] as $store) {
            $this->stores[$store['id']] = $store;
        }
        foreach (self::readJson("$dir/recipes.json")['recipes'] as $recipe) {
            $this->recipes[$recipe['id']] = $this->annotate($recipe);
        }
    }

    private static function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Catalogue introuvable : $path");
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return $data;
    }

    /**
     * Complète une recette avec ses types, allergènes et valeurs nutritionnelles,
     * tous déduits de ses ingrédients.
     */
    private function annotate(array $recipe): array
    {
        $types = [];
        $allergens = [];
        $kcal = 0.0;
        $prot = 0.0;

        foreach ($recipe['items'] as $item) {
            $ing = $this->ingredients[$item['i']] ?? null;
            if ($ing === null) {
                throw new RuntimeException("Ingrédient inconnu « {$item['i']} » dans la recette « {$recipe['id']} »");
            }
            $types[$ing['type']] = true;
            foreach ($ing['allergens'] as $a) {
                $allergens[$a] = true;
            }
            // kcal/prot sont donnés pour 100 g (ou 100 ml). Pour les pièces, la
            // valeur du catalogue est déjà celle de la pièce entière.
            $factor = $ing['unit'] === 'piece' ? $item['q'] : $item['q'] / 100;
            $kcal += $ing['kcal'] * $factor;
            $prot += $ing['prot'] * $factor;
        }

        $recipe['types']     = array_keys($types);
        $recipe['allergens'] = array_keys($allergens);
        $recipe['kcal']      = (int) round($kcal);
        $recipe['prot']      = (int) round($prot);
        $recipe['diets']     = $this->dietsFor(array_keys($types));
        $recipe['protein']   = $this->mainProtein($recipe['items']);

        return $recipe;
    }

    /** @param string[] $types @return string[] régimes compatibles */
    private function dietsFor(array $types): array
    {
        $ok = [];
        foreach (self::DIET_EXCLUDES as $diet => $excluded) {
            if (array_intersect($types, $excluded) === []) {
                $ok[] = $diet;
            }
        }
        return $ok;
    }

    /** Protéine dominante, utilisée pour éviter 3 poulets d'affilée. */
    private function mainProtein(array $items): string
    {
        $ranked = ['meat', 'pork', 'fish', 'seafood', 'egg', 'dairy'];
        $best = null;
        $bestQty = 0.0;
        foreach ($items as $item) {
            $ing = $this->ingredients[$item['i']];
            if (!in_array($ing['type'], $ranked, true)) {
                continue;
            }
            $qty = $ing['unit'] === 'piece' ? $item['q'] * 60 : $item['q'];
            if ($qty > $bestQty) {
                $bestQty = $qty;
                $best = $item['i'];
            }
        }
        return $best ?? 'vegetal';
    }

    /** Prix d'un conditionnement complet dans l'enseigne donnée. */
    public function packPrice(string $ingredientId, string $storeId): float
    {
        $ing = $this->ingredients[$ingredientId];
        $store = $this->stores[$storeId] ?? null;
        $index = $store === null ? 1.0 : $store['base'] * ($store['aisles'][$ing['aisle']] ?? 1.0);
        $unitPrice = $ing['price'] / $ing['ref'];          // prix par g / ml / pièce
        return round($unitPrice * $ing['pack'] * $index, 2);
    }

    /** Prix au kilo / litre / pièce affiché au rayon. */
    public function refPrice(string $ingredientId, string $storeId): float
    {
        $ing = $this->ingredients[$ingredientId];
        $store = $this->stores[$storeId] ?? null;
        $index = $store === null ? 1.0 : $store['base'] * ($store['aisles'][$ing['aisle']] ?? 1.0);
        return round($ing['price'] * $index, 2);
    }

    public function storesByCountry(string $country): array
    {
        return array_values(array_filter(
            $this->stores,
            fn($s) => in_array($country, $s['country'], true)
        ));
    }

    public function aisleLabel(string $aisle): string
    {
        return self::AISLE_ORDER[$aisle] ?? ucfirst($aisle);
    }
}
