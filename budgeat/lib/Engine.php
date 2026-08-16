<?php
declare(strict_types=1);

require_once __DIR__ . '/Catalog.php';

/**
 * Moteur de composition de menus sous contrainte de budget.
 *
 * Particularité par rapport aux planificateurs classiques : le coût d'une
 * recette n'est pas la somme du prix de ses ingrédients au prorata, mais son
 * COÛT MARGINAL SUR LE PANIER RÉEL. Si la semaine contient déjà un paquet de
 * 500 g de pâtes à moitié utilisé, la deuxième recette qui en demande 100 g ne
 * coûte rien de plus. C'est ce qui permet de coller au budget de caisse plutôt
 * qu'à un total théorique, et ça pousse mécaniquement vers des semaines
 * cohérentes où les ingrédients se recoupent (moins de gaspillage).
 */
final class Engine
{
    private const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    /** Prix moyen constaté d'un plat préparé équivalent, pour l'estimation d'économie. */
    private const READYMEAL_PER_PERSON = 5.20;

    public function __construct(private Catalog $catalog) {}

    /**
     * @param array $prefs store, budget, people, days, diet, allergens[],
     *                     exclude[], maxTime, pantry[], seed, keep[]
     */
    public function generate(array $prefs): array
    {
        $prefs = $this->normalize($prefs);
        $candidates = $this->candidates($prefs);

        if (count($candidates) < $prefs['days']) {
            return [
                'ok'    => false,
                'error' => 'not_enough_recipes',
                'message' => "Avec ces filtres il ne reste que " . count($candidates)
                    . " recettes possibles. Retirez un allergène ou un ingrédient exclu.",
                'available' => count($candidates),
            ];
        }

        $selected = $this->select($candidates, $prefs);
        $selected = $this->fitBudget($selected, $candidates, $prefs);
        $selected = $this->orderByDay($selected, $prefs);

        return $this->buildPlan($selected, $prefs);
    }

    /** Remplace un seul jour, en gardant le reste de la semaine intact. */
    public function swapDay(array $prefs, array $keepIds, string $replaceId): array
    {
        $prefs = $this->normalize($prefs);
        $candidates = $this->candidates($prefs);

        $kept = [];
        foreach ($keepIds as $id) {
            if ($id !== $replaceId && isset($this->catalog->recipes[$id])) {
                $kept[] = $this->catalog->recipes[$id];
            }
        }

        $pool = array_filter(
            $candidates,
            fn($r) => $r['id'] !== $replaceId && !in_array($r['id'], $keepIds, true)
        );
        if ($pool === []) {
            return ['ok' => false, 'error' => 'no_alternative', 'message' => 'Aucune alternative disponible avec ces filtres.'];
        }

        // Budget encore disponible une fois les repas conservés payés.
        $spent = $this->basketCost($this->needs($kept, $prefs), $prefs);
        $room  = max(0.0, $prefs['budget'] - $spent);

        $best = null;
        $bestScore = -INF;
        foreach ($pool as $recipe) {
            $marginal = $this->marginalCost($kept, $recipe, $prefs);
            $score = $this->score($recipe, $kept, $marginal, $prefs);
            if ($marginal > $room) {
                $score -= 3.0 * (($marginal - $room) / max($room, 1.0));
            }
            $score += $this->jitter($recipe['id'] . $prefs['seed']) * 0.35;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $recipe;
            }
        }

        $selected = $kept;
        $selected[] = $best;
        $selected = $this->orderByDay($selected, $prefs);

        return $this->buildPlan($selected, $prefs);
    }

    // ---------------------------------------------------------------- filtres

    private function normalize(array $p): array
    {
        $days = max(1, min(7, (int) ($p['days'] ?? 7)));
        return [
            'store'     => $p['store'] ?? 'lidl',
            'budget'    => max(10.0, (float) ($p['budget'] ?? 60)),
            'people'    => max(1, min(12, (int) ($p['people'] ?? 2))),
            'days'      => $days,
            'diet'      => isset(Catalog::DIET_EXCLUDES[$p['diet'] ?? '']) ? $p['diet'] : 'omnivore',
            'allergens' => array_values(array_intersect((array) ($p['allergens'] ?? []), array_keys(Catalog::ALLERGENS))),
            'exclude'   => array_values(array_filter((array) ($p['exclude'] ?? []), fn($i) => isset($this->catalog->ingredients[$i]))),
            'maxTime'   => (int) ($p['maxTime'] ?? 0),
            'pantry'    => (array) ($p['pantry'] ?? Catalog::PANTRY_DEFAULT),
            'seed'      => (string) ($p['seed'] ?? '1'),
        ];
    }

    /** @return array<int,array> recettes qui passent tous les filtres durs */
    private function candidates(array $prefs): array
    {
        $out = [];
        foreach ($this->catalog->recipes as $recipe) {
            if (!in_array($prefs['diet'], $recipe['diets'], true)) {
                continue;
            }
            if (array_intersect($recipe['allergens'], $prefs['allergens']) !== []) {
                continue;
            }
            if ($prefs['maxTime'] > 0 && $recipe['time'] > $prefs['maxTime']) {
                continue;
            }
            if ($prefs['exclude'] !== []) {
                $ids = array_column($recipe['items'], 'i');
                if (array_intersect($ids, $prefs['exclude']) !== []) {
                    continue;
                }
            }
            $out[] = $recipe;
        }
        return $out;
    }

    // ------------------------------------------------------------- sélection

    private function select(array $candidates, array $prefs): array
    {
        $selected = [];
        $pool = $candidates;

        for ($i = 0; $i < $prefs['days']; $i++) {
            $scored = [];
            foreach ($pool as $idx => $recipe) {
                if ($this->alreadyPicked($selected, $recipe['id'])) {
                    continue;
                }
                if (!$this->compositionAllows($selected, $recipe, $prefs)) {
                    continue;
                }
                $marginal = $this->marginalCost($selected, $recipe, $prefs);
                $scored[] = [
                    'recipe' => $recipe,
                    'score'  => $this->score($recipe, $selected, $marginal, $prefs)
                                + $this->jitter($recipe['id'] . $prefs['seed'] . $i) * 0.30,
                ];
            }

            if ($scored === []) {
                // Contraintes de composition trop serrées : on les relâche.
                foreach ($pool as $recipe) {
                    if ($this->alreadyPicked($selected, $recipe['id'])) {
                        continue;
                    }
                    $marginal = $this->marginalCost($selected, $recipe, $prefs);
                    $scored[] = ['recipe' => $recipe, 'score' => $this->score($recipe, $selected, $marginal, $prefs)];
                }
            }
            if ($scored === []) {
                break;
            }

            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $selected[] = $scored[0]['recipe'];
        }

        return $selected;
    }

    private function alreadyPicked(array $selected, string $id): bool
    {
        foreach ($selected as $r) {
            if ($r['id'] === $id) {
                return true;
            }
        }
        return false;
    }

    /** Contraintes souples de composition de la semaine. */
    private function compositionAllows(array $selected, array $recipe, array $prefs): bool
    {
        $proteinCount = 0;
        $cuisineCount = 0;
        $meatCount = 0;
        foreach ($selected as $r) {
            if ($r['protein'] === $recipe['protein'] && $r['protein'] !== 'vegetal') {
                $proteinCount++;
            }
            if ($r['cuisine'] === $recipe['cuisine']) {
                $cuisineCount++;
            }
            if (array_intersect($r['types'], ['meat', 'pork']) !== []) {
                $meatCount++;
            }
        }
        if ($proteinCount >= 2) {
            return false;                       // pas plus de 2 fois la même protéine
        }
        if ($cuisineCount >= 3) {
            return false;                       // pas plus de 3 plats de la même cuisine
        }
        if ($prefs['diet'] === 'flexitarien'
            && array_intersect($recipe['types'], ['meat', 'pork']) !== []
            && $meatCount >= 3) {
            return false;                       // flexitarien : 3 repas carnés max
        }
        return true;
    }

    /**
     * Score d'une recette dans le contexte de la semaine en cours.
     * Positif = bon choix. Le coût marginal est ramené au budget cible par repas.
     */
    private function score(array $recipe, array $selected, float $marginal, array $prefs): float
    {
        $target = $prefs['budget'] / max(1, $prefs['days']);
        $ratio  = $target > 0 ? $marginal / $target : 1.0;

        // On veut coller au budget : sous-consommer est un défaut mineur,
        // dépasser est un défaut majeur.
        $score = $ratio <= 1.0 ? (1.0 - $ratio) * 1.2 : -($ratio - 1.0) * 3.0;

        // Variété : une cuisine ou une protéine déjà vue coûte des points.
        foreach ($selected as $r) {
            if ($r['cuisine'] === $recipe['cuisine']) {
                $score -= 0.35;
            }
            if ($r['protein'] === $recipe['protein'] && $r['protein'] !== 'vegetal') {
                $score -= 0.55;
            }
        }

        // Équilibre nutritionnel : on encourage un apport protéique correct
        // et on sanctionne les assiettes très caloriques.
        if ($recipe['prot'] >= 25) {
            $score += 0.25;
        }
        if ($recipe['kcal'] > 950) {
            $score -= 0.20;
        }
        if (in_array('sain', $recipe['tags'], true)) {
            $score += 0.10;
        }

        // Un poisson dans la semaine, si le régime le permet.
        if (array_intersect($recipe['types'], ['fish', 'seafood']) !== []
            && !$this->hasType($selected, ['fish', 'seafood'])) {
            $score += 0.45;
        }

        // Temps de préparation : bonus aux recettes courtes, la semaine est chargée.
        if ($recipe['time'] <= 25) {
            $score += 0.20;
        } elseif ($recipe['time'] >= 60) {
            $score -= 0.15;
        }

        return $score;
    }

    private function hasType(array $recipes, array $types): bool
    {
        foreach ($recipes as $r) {
            if (array_intersect($r['types'], $types) !== []) {
                return true;
            }
        }
        return false;
    }

    private function jitter(string $key): float
    {
        return (crc32($key) % 1000) / 1000;      // 0..1 déterministe
    }

    // ---------------------------------------------------------------- budget

    /**
     * Ajuste la semaine pour tenir dans le budget : on remplace le repas au
     * coût marginal le plus élevé par la meilleure alternative moins chère,
     * puis on remonte en gamme s'il reste beaucoup de marge.
     */
    private function fitBudget(array $selected, array $candidates, array $prefs): array
    {
        $budget = $prefs['budget'];

        for ($pass = 0; $pass < 12; $pass++) {
            $total = $this->basketCost($this->needs($selected, $prefs), $prefs);
            if ($total <= $budget) {
                break;
            }
            $worstIdx = $this->costliestIndex($selected, $prefs);
            $replacement = $this->cheapestAlternative($selected, $candidates, $worstIdx, $prefs);
            if ($replacement === null) {
                break;
            }
            $selected[$worstIdx] = $replacement;
        }

        // Il reste plus de 12 % de marge : on tente un repas plus généreux.
        for ($pass = 0; $pass < 6; $pass++) {
            $total = $this->basketCost($this->needs($selected, $prefs), $prefs);
            if ($total >= $budget * 0.88) {
                break;
            }
            $upgraded = $this->upgrade($selected, $candidates, $prefs, $budget - $total);
            if ($upgraded === null) {
                break;
            }
            $selected = $upgraded;
        }

        return $selected;
    }

    private function costliestIndex(array $selected, array $prefs): int
    {
        $worst = 0;
        $worstCost = -1.0;
        foreach ($selected as $i => $recipe) {
            $others = $selected;
            unset($others[$i]);
            $cost = $this->marginalCost(array_values($others), $recipe, $prefs);
            if ($cost > $worstCost) {
                $worstCost = $cost;
                $worst = $i;
            }
        }
        return $worst;
    }

    private function cheapestAlternative(array $selected, array $candidates, int $idx, array $prefs): ?array
    {
        $others = $selected;
        unset($others[$idx]);
        $others = array_values($others);
        $current = $this->marginalCost($others, $selected[$idx], $prefs);

        $best = null;
        $bestCost = $current;
        foreach ($candidates as $recipe) {
            if ($this->alreadyPicked($selected, $recipe['id'])) {
                continue;
            }
            $cost = $this->marginalCost($others, $recipe, $prefs);
            if ($cost < $bestCost - 0.20) {
                $bestCost = $cost;
                $best = $recipe;
            }
        }
        return $best;
    }

    /** Remonte en gamme un repas tant que la marge budgétaire le permet. */
    private function upgrade(array $selected, array $candidates, array $prefs, float $room): ?array
    {
        $bestPlan = null;
        $bestGain = 0.0;

        foreach ($selected as $i => $current) {
            $others = array_values(array_diff_key($selected, [$i => null]));
            $currentCost = $this->marginalCost($others, $current, $prefs);

            foreach ($candidates as $recipe) {
                if ($this->alreadyPicked($selected, $recipe['id'])) {
                    continue;
                }
                if (!$this->compositionAllows($others, $recipe, $prefs)) {
                    continue;
                }
                $cost = $this->marginalCost($others, $recipe, $prefs);
                $delta = $cost - $currentCost;
                if ($delta <= 0.30 || $delta > $room) {
                    continue;
                }
                // On privilégie un gain nutritionnel ou de variété, pas la dépense pure.
                $gain = ($recipe['prot'] - $current['prot']) * 0.02 + $delta * 0.10;
                if ($gain > $bestGain) {
                    $bestGain = $gain;
                    $plan = $selected;
                    $plan[$i] = $recipe;
                    $bestPlan = $plan;
                }
            }
        }
        return $bestPlan;
    }

    /**
     * Place les plats longs le week-end et les plus rapides en début de
     * semaine, là où personne n'a envie de passer une heure aux fourneaux.
     */
    private function orderByDay(array $selected, array $prefs): array
    {
        $count = count($selected);
        usort($selected, fn($a, $b) => $b['time'] <=> $a['time']);   // du plus long au plus court

        $slots   = range(0, $count - 1);
        $weekend = array_values(array_filter($slots, fn($d) => $d >= 5));
        $week    = array_values(array_filter($slots, fn($d) => $d < 5));

        $ordered = [];
        // Les recettes les plus longues partent sur samedi/dimanche...
        foreach ($weekend as $day) {
            $ordered[$day] = array_shift($selected);
        }
        // ...et le reste s'étale du plus rapide (lundi) au plus long (vendredi).
        foreach ($week as $i => $day) {
            $ordered[$day] = $selected[count($selected) - 1 - $i];
        }

        ksort($ordered);
        return array_values($ordered);
    }

    // ----------------------------------------------------------------- panier

    /** @return array<string,float> quantité totale requise par ingrédient */
    private function needs(array $recipes, array $prefs): array
    {
        $needs = [];
        foreach ($recipes as $recipe) {
            foreach ($recipe['items'] as $item) {
                $needs[$item['i']] = ($needs[$item['i']] ?? 0) + $item['q'] * $prefs['people'];
            }
        }
        return $needs;
    }

    /** Coût réel du panier : on paie des conditionnements entiers. */
    private function basketCost(array $needs, array $prefs): float
    {
        $total = 0.0;
        foreach ($needs as $id => $qty) {
            if (in_array($id, $prefs['pantry'], true)) {
                continue;
            }
            $ing = $this->catalog->ingredients[$id];
            $packs = (int) ceil($qty / $ing['pack'] - 1e-9);
            $total += $packs * $this->catalog->packPrice($id, $prefs['store']);
        }
        return round($total, 2);
    }

    private function marginalCost(array $selected, array $recipe, array $prefs): float
    {
        $before = $this->basketCost($this->needs($selected, $prefs), $prefs);
        $after  = $this->basketCost($this->needs([...$selected, $recipe], $prefs), $prefs);
        return round($after - $before, 2);
    }

    // ------------------------------------------------------------------ plan

    private function buildPlan(array $selected, array $prefs): array
    {
        $needs = $this->needs($selected, $prefs);
        $store = $this->catalog->stores[$prefs['store']] ?? null;

        $aisles = [];
        $pantryLines = [];
        $total = 0.0;
        $boughtValue = 0.0;
        $perishableLoss = 0.0;   // restes qui finiront à la poubelle
        $carryOver = 0.0;        // restes qui resserviront la semaine suivante

        foreach ($needs as $id => $qty) {
            $ing = $this->catalog->ingredients[$id];
            $packPrice = $this->catalog->packPrice($id, $prefs['store']);
            $packs = (int) ceil($qty / $ing['pack'] - 1e-9);
            $lineTotal = round($packs * $packPrice, 2);
            $bought = $packs * $ing['pack'];

            $leftover = max(0, $bought - $qty);
            // Un reste n'est un gaspillage que s'il ne tient pas jusqu'à la
            // semaine suivante. Le riz et les épices, eux, repartent au placard.
            $perishable = $ing['shelf'] <= 10;
            $leftoverValue = $leftover / $ing['pack'] * $packPrice;

            $line = [
                'id'         => $id,
                'name'       => $ing['name'],
                'aisle'      => $ing['aisle'],
                'unit'       => $ing['unit'],
                'need'       => round($qty, 1),
                'needLabel'  => $this->qtyLabel($qty, $ing['unit']),
                'packs'      => $packs,
                'packLabel'  => $ing['packLabel'],
                'refPrice'   => $this->catalog->refPrice($id, $prefs['store']),
                'refUnit'    => $ing['unit'] === 'piece' ? 'pièce' : ($ing['unit'] === 'ml' ? 'L' : 'kg'),
                'price'      => $lineTotal,
                'usedPct'    => (int) round(min(100, $qty / max($bought, 0.001) * 100)),
                'leftover'   => round($leftover, 1),
                'leftoverLabel' => $leftover > 0 ? $this->qtyLabel($leftover, $ing['unit']) : null,
                'perishable' => $perishable,
            ];

            if (in_array($id, $prefs['pantry'], true)) {
                $line['price'] = 0.0;
                $pantryLines[] = $line;
                continue;
            }

            $aisles[$ing['aisle']][] = $line;
            $total += $lineTotal;
            $boughtValue += $lineTotal;
            if ($perishable) {
                $perishableLoss += $leftoverValue;
            } else {
                $carryOver += $leftoverValue;
            }
        }

        // Ordonne les rayons dans l'ordre d'un vrai parcours de magasin.
        $sortedAisles = [];
        foreach (array_keys(Catalog::AISLE_ORDER) as $aisle) {
            if (!isset($aisles[$aisle])) {
                continue;
            }
            usort($aisles[$aisle], fn($a, $b) => strcmp($a['name'], $b['name']));
            $sortedAisles[] = [
                'id'    => $aisle,
                'label' => $this->catalog->aisleLabel($aisle),
                'items' => $aisles[$aisle],
                'total' => round(array_sum(array_column($aisles[$aisle], 'price')), 2),
            ];
        }

        $days = [];
        $kcal = 0;
        $prot = 0;
        $time = 0;
        foreach ($selected as $i => $recipe) {
            $others = array_values(array_diff_key($selected, [$i => null]));
            $marginal = $this->marginalCost($others, $recipe, $prefs);
            $days[] = [
                'day'      => self::DAYS[$i] ?? 'Jour ' . ($i + 1),
                'id'       => $recipe['id'],
                'name'     => $recipe['name'],
                'emoji'    => $recipe['emoji'],
                'time'     => $recipe['time'],
                'cuisine'  => $recipe['cuisine'],
                'tags'     => $recipe['tags'],
                'kcal'     => $recipe['kcal'],
                'prot'     => $recipe['prot'],
                'steps'    => $recipe['steps'],
                'items'    => $this->recipeLines($recipe, $prefs),
                'cost'     => $marginal,
                'costPerPerson' => round($marginal / $prefs['people'], 2),
            ];
            $kcal += $recipe['kcal'];
            $prot += $recipe['prot'];
            $time += $recipe['time'];
        }

        $count = max(1, count($selected));
        $meals = $count * $prefs['people'];
        $total = round($total, 2);
        $wastePct = $boughtValue > 0 ? (int) round($perishableLoss / $boughtValue * 100) : 0;

        // Sous un certain seuil, aucune semaine complète n'est atteignable :
        // mieux vaut le dire franchement que livrer un plan hors budget.
        $notice = null;
        if ($total > $prefs['budget'] + 0.01) {
            $suggested = (int) (ceil($total / 5) * 5);
            $notice = [
                'type'    => 'budget_too_low',
                'message' => "Avec {$prefs['people']} personne(s) sur {$count} dîners, le panier le moins cher "
                    . "que nous ayons trouvé chez {$store['name']} revient à {$total} €. "
                    . "Comptez plutôt {$suggested} € — ou réduisez le nombre de dîners.",
                'minBudget' => $suggested,
            ];
        }

        return [
            'ok'     => true,
            'notice' => $notice,
            'store'  => $store ? ['id' => $store['id'], 'name' => $store['name'], 'color' => $store['color']] : null,
            'prefs'  => $prefs,
            'days'   => $days,
            'shopping' => [
                'aisles' => $sortedAisles,
                'pantry' => $pantryLines,
                'lines'  => array_sum(array_map(fn($a) => count($a['items']), $sortedAisles)),
            ],
            'totals' => [
                'budget'        => round($prefs['budget'], 2),
                'total'         => $total,
                'remaining'     => round($prefs['budget'] - $total, 2),
                'usedPct'       => (int) round($total / max($prefs['budget'], 0.01) * 100),
                'perMeal'       => round($total / $meals, 2),
                'perDay'        => round($total / $count, 2),
                'people'        => $prefs['people'],
                'days'          => $count,
                'savedVsReady'  => round(max(0, self::READYMEAL_PER_PERSON * $meals - $total), 2),
                'wastePct'      => $wastePct,
                'wasteValue'    => round($perishableLoss, 2),
                'carryOver'     => round($carryOver, 2),
                'feasible'      => $total <= $prefs['budget'] + 0.01,
                'kcalPerMeal'   => (int) round($kcal / $count),
                'protPerMeal'   => (int) round($prot / $count),
                'cookMinutes'   => $time,
            ],
        ];
    }

    /** Quantités affichées dans la recette, pour le nombre de convives choisi. */
    private function recipeLines(array $recipe, array $prefs): array
    {
        $lines = [];
        foreach ($recipe['items'] as $item) {
            $ing = $this->catalog->ingredients[$item['i']];
            $qty = $item['q'] * $prefs['people'];
            $lines[] = [
                'id'    => $item['i'],
                'name'  => $ing['name'],
                'qty'   => $this->qtyLabel($qty, $ing['unit']),
                'pantry'=> in_array($item['i'], $prefs['pantry'], true),
            ];
        }
        return $lines;
    }

    private function qtyLabel(float $qty, string $unit): string
    {
        if ($unit === 'piece') {
            $rounded = round($qty * 2) / 2;
            $label = rtrim(rtrim(number_format($rounded, 1, ',', ' '), '0'), ',');
            return $label . ' ' . ($rounded > 1 ? 'pièces' : 'pièce');
        }
        if ($qty >= 1000) {
            return rtrim(rtrim(number_format($qty / 1000, 2, ',', ' '), '0'), ',') . ' ' . ($unit === 'ml' ? 'L' : 'kg');
        }
        return round($qty) . ' ' . $unit;
    }
}
