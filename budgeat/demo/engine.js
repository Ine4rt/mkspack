/**
 * Port JavaScript du moteur (lib/Catalog.php + lib/Engine.php), pour la démo
 * publique qui tourne entièrement dans le navigateur, sans serveur.
 *
 * La logique suit celle du PHP pas à pas — même sélection gloutonne, même coût
 * marginal sur le panier réel, même réparation budgétaire — pour que la démo
 * montre bien ce que fait le produit. Le PHP reste la référence : c'est lui qui
 * est couvert par tests/engine_test.php.
 */
(function (global) {
  'use strict';

  var DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
  var READYMEAL_PER_PERSON = 5.20;

  var PANTRY_DEFAULT = ['sel-poivre', 'huile-olive', 'huile-tournesol', 'vinaigre', 'moutarde',
    'farine', 'sucre', 'bouillon', 'curry-poudre', 'paprika', 'cumin', 'curcuma',
    'herbes', 'piment', 'sauce-soja', 'miel'];

  var AISLE_ORDER = {
    'fruits-legumes': 'Fruits & légumes',
    'boucherie': 'Boucherie',
    'poissonnerie': 'Poissonnerie',
    'cremerie': 'Crèmerie & frais',
    'boulangerie': 'Boulangerie',
    'surgeles': 'Surgelés',
    'epicerie-salee': 'Épicerie salée',
    'monde': 'Cuisine du monde',
    'epicerie-sucree': 'Épicerie sucrée',
    'boissons': 'Boissons'
  };

  var DIET_EXCLUDES = {
    omnivore: [],
    flexitarien: [],
    'sans-porc': ['pork'],
    pescetarien: ['meat', 'pork'],
    vegetarien: ['meat', 'pork', 'fish', 'seafood'],
    vegan: ['meat', 'pork', 'fish', 'seafood', 'dairy', 'egg', 'honey']
  };

  var DIET_LABELS = {
    omnivore: 'Je mange de tout',
    flexitarien: 'Flexitarien (viande limitée)',
    'sans-porc': 'Sans porc',
    pescetarien: 'Pescétarien',
    vegetarien: 'Végétarien',
    vegan: 'Végan'
  };

  var ALLERGENS = {
    gluten: 'Gluten', crustaces: 'Crustacés', oeufs: 'Œufs', poissons: 'Poissons',
    arachides: 'Arachides', soja: 'Soja', lait: 'Lait', 'fruits-a-coque': 'Fruits à coque',
    celeri: 'Céleri', moutarde: 'Moutarde', sesame: 'Sésame', sulfites: 'Sulfites',
    mollusques: 'Mollusques'
  };

  // crc32, pour reproduire exactement le jitter déterministe du PHP.
  var crcTable = (function () {
    var table = [];
    for (var n = 0; n < 256; n++) {
      var c = n;
      for (var k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
      table[n] = c >>> 0;
    }
    return table;
  })();

  function crc32(str) {
    var bytes = unescape(encodeURIComponent(str));
    var crc = 0xFFFFFFFF;
    for (var i = 0; i < bytes.length; i++) {
      crc = (crc >>> 8) ^ crcTable[(crc ^ bytes.charCodeAt(i)) & 0xFF];
    }
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }

  // ------------------------------------------------------------------ catalogue

  function Catalog(data) {
    var self = this;
    this.ingredients = {};
    this.stores = {};
    this.recipes = {};

    data.ingredients.ingredients.forEach(function (ing) {
      ing.pantryDefault = PANTRY_DEFAULT.indexOf(ing.id) >= 0;
      self.ingredients[ing.id] = ing;
    });
    data.stores.stores.forEach(function (store) { self.stores[store.id] = store; });
    data.recipes.recipes.forEach(function (recipe) {
      self.recipes[recipe.id] = self.annotate(recipe);
    });
  }

  Catalog.prototype.annotate = function (recipe) {
    var self = this;
    var types = {}, allergens = {}, kcal = 0, prot = 0;

    recipe.items.forEach(function (item) {
      var ing = self.ingredients[item.i];
      if (!ing) throw new Error('Ingrédient inconnu « ' + item.i + ' » dans « ' + recipe.id + ' »');
      types[ing.type] = true;
      ing.allergens.forEach(function (a) { allergens[a] = true; });
      var factor = ing.unit === 'piece' ? item.q : item.q / 100;
      kcal += ing.kcal * factor;
      prot += ing.prot * factor;
    });

    recipe.types = Object.keys(types);
    recipe.allergens = Object.keys(allergens);
    recipe.kcal = Math.round(kcal);
    recipe.prot = Math.round(prot);
    recipe.diets = Object.keys(DIET_EXCLUDES).filter(function (diet) {
      return DIET_EXCLUDES[diet].every(function (t) { return recipe.types.indexOf(t) < 0; });
    });
    recipe.protein = this.mainProtein(recipe.items);
    return recipe;
  };

  Catalog.prototype.mainProtein = function (items) {
    var self = this;
    var ranked = ['meat', 'pork', 'fish', 'seafood', 'egg', 'dairy'];
    var best = null, bestQty = 0;
    items.forEach(function (item) {
      var ing = self.ingredients[item.i];
      if (ranked.indexOf(ing.type) < 0) return;
      var qty = ing.unit === 'piece' ? item.q * 60 : item.q;
      if (qty > bestQty) { bestQty = qty; best = item.i; }
    });
    return best || 'vegetal';
  };

  Catalog.prototype.index = function (ingredientId, storeId) {
    var ing = this.ingredients[ingredientId];
    var store = this.stores[storeId];
    if (!store) return 1;
    var aisles = store.aisles || {};
    return store.base * (aisles[ing.aisle] !== undefined ? aisles[ing.aisle] : 1);
  };

  Catalog.prototype.packPrice = function (ingredientId, storeId) {
    var ing = this.ingredients[ingredientId];
    return round2(ing.price / ing.ref * ing.pack * this.index(ingredientId, storeId));
  };

  Catalog.prototype.refPrice = function (ingredientId, storeId) {
    return round2(this.ingredients[ingredientId].price * this.index(ingredientId, storeId));
  };

  Catalog.prototype.storesByCountry = function (country) {
    var self = this;
    return Object.keys(this.stores)
      .map(function (id) { return self.stores[id]; })
      .filter(function (s) { return s.country.indexOf(country) >= 0; });
  };

  /**
   * Arrondi à 2 décimales reproduisant celui de PHP. Math.round(1.005 * 100)
   * rend 100 (1.005 vaut 1.00499… en binaire) là où PHP rend 1.01 : passer par
   * la notation exponentielle corrige ce décalage, et évite des écarts de
   * quelques centimes entre la démo et le serveur.
   */
  function round2(value) {
    var shifted = Number(value + 'e2');
    if (isNaN(shifted)) return Math.round(value * 100) / 100;
    var result = Number(Math.round(shifted) + 'e-2');
    return isNaN(result) ? Math.round(value * 100) / 100 : result;
  }

  // --------------------------------------------------------------------- moteur

  function Engine(catalog) { this.catalog = catalog; }

  Engine.prototype.normalize = function (p) {
    p = p || {};
    return {
      store: p.store || 'colruyt',
      budget: Math.max(10, Number(p.budget) || 60),
      people: Math.max(1, Math.min(12, Number(p.people) || 2)),
      days: Math.max(1, Math.min(7, Number(p.days) || 7)),
      diet: DIET_EXCLUDES[p.diet] ? p.diet : 'omnivore',
      allergens: (p.allergens || []).filter(function (a) { return ALLERGENS[a]; }),
      exclude: (p.exclude || []),
      maxTime: Number(p.maxTime) || 0,
      pantry: p.pantry !== undefined ? p.pantry : PANTRY_DEFAULT,
      seed: String(p.seed === undefined ? '1' : p.seed)
    };
  };

  Engine.prototype.candidates = function (prefs) {
    var self = this;
    return Object.keys(this.catalog.recipes).map(function (id) {
      return self.catalog.recipes[id];
    }).filter(function (recipe) {
      if (recipe.diets.indexOf(prefs.diet) < 0) return false;
      if (recipe.allergens.some(function (a) { return prefs.allergens.indexOf(a) >= 0; })) return false;
      if (prefs.maxTime > 0 && recipe.time > prefs.maxTime) return false;
      if (prefs.exclude.length) {
        var ids = recipe.items.map(function (i) { return i.i; });
        if (ids.some(function (i) { return prefs.exclude.indexOf(i) >= 0; })) return false;
      }
      return true;
    });
  };

  Engine.prototype.needs = function (recipes, prefs) {
    var needs = {};
    recipes.forEach(function (recipe) {
      recipe.items.forEach(function (item) {
        needs[item.i] = (needs[item.i] || 0) + item.q * prefs.people;
      });
    });
    return needs;
  };

  Engine.prototype.basketCost = function (needs, prefs) {
    var self = this, total = 0;
    Object.keys(needs).forEach(function (id) {
      if (prefs.pantry.indexOf(id) >= 0) return;
      var ing = self.catalog.ingredients[id];
      var packs = Math.ceil(needs[id] / ing.pack - 1e-9);
      total += packs * self.catalog.packPrice(id, prefs.store);
    });
    return round2(total);
  };

  Engine.prototype.marginalCost = function (selected, recipe, prefs) {
    var before = this.basketCost(this.needs(selected, prefs), prefs);
    var after = this.basketCost(this.needs(selected.concat([recipe]), prefs), prefs);
    return round2(after - before);
  };

  Engine.prototype.compositionAllows = function (selected, recipe, prefs) {
    var proteinCount = 0, cuisineCount = 0, meatCount = 0;
    selected.forEach(function (r) {
      if (r.protein === recipe.protein && r.protein !== 'vegetal') proteinCount++;
      if (r.cuisine === recipe.cuisine) cuisineCount++;
      if (r.types.indexOf('meat') >= 0 || r.types.indexOf('pork') >= 0) meatCount++;
    });
    if (proteinCount >= 2) return false;
    if (cuisineCount >= 3) return false;
    if (prefs.diet === 'flexitarien' && meatCount >= 3
        && (recipe.types.indexOf('meat') >= 0 || recipe.types.indexOf('pork') >= 0)) return false;
    return true;
  };

  Engine.prototype.score = function (recipe, selected, marginal, prefs) {
    var target = prefs.budget / Math.max(1, prefs.days);
    var ratio = target > 0 ? marginal / target : 1;
    var score = ratio <= 1 ? (1 - ratio) * 1.2 : -(ratio - 1) * 3.0;

    selected.forEach(function (r) {
      if (r.cuisine === recipe.cuisine) score -= 0.35;
      if (r.protein === recipe.protein && r.protein !== 'vegetal') score -= 0.55;
    });

    if (recipe.prot >= 25) score += 0.25;
    if (recipe.kcal > 950) score -= 0.20;
    if (recipe.tags.indexOf('sain') >= 0) score += 0.10;

    var isSea = recipe.types.indexOf('fish') >= 0 || recipe.types.indexOf('seafood') >= 0;
    var hasSea = selected.some(function (r) {
      return r.types.indexOf('fish') >= 0 || r.types.indexOf('seafood') >= 0;
    });
    if (isSea && !hasSea) score += 0.45;

    if (recipe.time <= 25) score += 0.20;
    else if (recipe.time >= 60) score -= 0.15;

    return score;
  };

  function jitter(key) { return (crc32(key) % 1000) / 1000; }

  function picked(selected, id) {
    return selected.some(function (r) { return r.id === id; });
  }

  Engine.prototype.select = function (candidates, prefs) {
    var self = this;
    var selected = [];

    for (var i = 0; i < prefs.days; i++) {
      var scored = [];
      candidates.forEach(function (recipe) {
        if (picked(selected, recipe.id)) return;
        if (!self.compositionAllows(selected, recipe, prefs)) return;
        var marginal = self.marginalCost(selected, recipe, prefs);
        scored.push({
          recipe: recipe,
          score: self.score(recipe, selected, marginal, prefs) + jitter(recipe.id + prefs.seed + i) * 0.30
        });
      });

      if (!scored.length) {
        candidates.forEach(function (recipe) {
          if (picked(selected, recipe.id)) return;
          var marginal = self.marginalCost(selected, recipe, prefs);
          scored.push({ recipe: recipe, score: self.score(recipe, selected, marginal, prefs) });
        });
      }
      if (!scored.length) break;

      scored.sort(function (a, b) { return b.score - a.score; });
      selected.push(scored[0].recipe);
    }
    return selected;
  };

  Engine.prototype.costliestIndex = function (selected, prefs) {
    var self = this, worst = 0, worstCost = -1;
    selected.forEach(function (recipe, i) {
      var others = selected.filter(function (_, j) { return j !== i; });
      var cost = self.marginalCost(others, recipe, prefs);
      if (cost > worstCost) { worstCost = cost; worst = i; }
    });
    return worst;
  };

  Engine.prototype.cheapestAlternative = function (selected, candidates, idx, prefs) {
    var self = this;
    var others = selected.filter(function (_, j) { return j !== idx; });
    var bestCost = this.marginalCost(others, selected[idx], prefs);
    var best = null;

    candidates.forEach(function (recipe) {
      if (picked(selected, recipe.id)) return;
      var cost = self.marginalCost(others, recipe, prefs);
      if (cost < bestCost - 0.20) { bestCost = cost; best = recipe; }
    });
    return best;
  };

  Engine.prototype.upgrade = function (selected, candidates, prefs, room) {
    var self = this, bestPlan = null, bestGain = 0;

    selected.forEach(function (current, i) {
      var others = selected.filter(function (_, j) { return j !== i; });
      var currentCost = self.marginalCost(others, current, prefs);

      candidates.forEach(function (recipe) {
        if (picked(selected, recipe.id)) return;
        if (!self.compositionAllows(others, recipe, prefs)) return;
        var delta = self.marginalCost(others, recipe, prefs) - currentCost;
        if (delta <= 0.30 || delta > room) return;
        var gain = (recipe.prot - current.prot) * 0.02 + delta * 0.10;
        if (gain > bestGain) {
          bestGain = gain;
          bestPlan = selected.slice();
          bestPlan[i] = recipe;
        }
      });
    });
    return bestPlan;
  };

  Engine.prototype.fitBudget = function (selected, candidates, prefs) {
    var pass;
    for (pass = 0; pass < 12; pass++) {
      if (this.basketCost(this.needs(selected, prefs), prefs) <= prefs.budget) break;
      var worst = this.costliestIndex(selected, prefs);
      var replacement = this.cheapestAlternative(selected, candidates, worst, prefs);
      if (!replacement) break;
      selected[worst] = replacement;
    }
    for (pass = 0; pass < 6; pass++) {
      var total = this.basketCost(this.needs(selected, prefs), prefs);
      if (total >= prefs.budget * 0.88) break;
      var upgraded = this.upgrade(selected, candidates, prefs, prefs.budget - total);
      if (!upgraded) break;
      selected = upgraded;
    }
    return selected;
  };

  Engine.prototype.orderByDay = function (selected) {
    var count = selected.length;
    selected = selected.slice().sort(function (a, b) { return b.time - a.time; });

    var ordered = new Array(count);
    var weekend = [], week = [];
    for (var d = 0; d < count; d++) (d >= 5 ? weekend : week).push(d);

    weekend.forEach(function (day) { ordered[day] = selected.shift(); });
    week.forEach(function (day, i) { ordered[day] = selected[selected.length - 1 - i]; });

    return ordered;
  };

  Engine.prototype.qtyLabel = function (qty, unit) {
    if (unit === 'piece') {
      var rounded = Math.round(qty * 2) / 2;
      var label = String(rounded).replace('.', ',');
      return label + ' ' + (rounded > 1 ? 'pièces' : 'pièce');
    }
    if (qty >= 1000) {
      var big = (qty / 1000).toFixed(2).replace(/0+$/, '').replace(/\.$/, '').replace('.', ',');
      return big + ' ' + (unit === 'ml' ? 'L' : 'kg');
    }
    return Math.round(qty) + ' ' + unit;
  };

  Engine.prototype.recipeLines = function (recipe, prefs) {
    var self = this;
    return recipe.items.map(function (item) {
      var ing = self.catalog.ingredients[item.i];
      return {
        id: item.i,
        name: ing.name,
        qty: self.qtyLabel(item.q * prefs.people, ing.unit),
        pantry: prefs.pantry.indexOf(item.i) >= 0
      };
    });
  };

  Engine.prototype.buildPlan = function (selected, prefs) {
    var self = this;
    var needs = this.needs(selected, prefs);
    var store = this.catalog.stores[prefs.store] || null;

    var aisles = {}, pantryLines = [];
    var total = 0, boughtValue = 0, perishableLoss = 0, carryOver = 0;

    Object.keys(needs).forEach(function (id) {
      var ing = self.catalog.ingredients[id];
      var qty = needs[id];
      var packPrice = self.catalog.packPrice(id, prefs.store);
      var packs = Math.ceil(qty / ing.pack - 1e-9);
      var lineTotal = round2(packs * packPrice);
      var bought = packs * ing.pack;
      var leftover = Math.max(0, bought - qty);
      var perishable = ing.shelf <= 10;
      var leftoverValue = leftover / ing.pack * packPrice;

      var line = {
        id: id, name: ing.name, aisle: ing.aisle, unit: ing.unit,
        need: Math.round(qty * 10) / 10,
        needLabel: self.qtyLabel(qty, ing.unit),
        packs: packs, packLabel: ing.packLabel,
        refPrice: self.catalog.refPrice(id, prefs.store),
        refUnit: ing.unit === 'piece' ? 'pièce' : (ing.unit === 'ml' ? 'L' : 'kg'),
        price: lineTotal,
        usedPct: Math.round(Math.min(100, qty / Math.max(bought, 0.001) * 100)),
        leftover: Math.round(leftover * 10) / 10,
        leftoverLabel: leftover > 0 ? self.qtyLabel(leftover, ing.unit) : null,
        perishable: perishable
      };

      if (prefs.pantry.indexOf(id) >= 0) {
        line.price = 0;
        pantryLines.push(line);
        return;
      }

      (aisles[ing.aisle] = aisles[ing.aisle] || []).push(line);
      total += lineTotal;
      boughtValue += lineTotal;
      if (perishable) perishableLoss += leftoverValue;
      else carryOver += leftoverValue;
    });

    var sortedAisles = [];
    Object.keys(AISLE_ORDER).forEach(function (aisle) {
      if (!aisles[aisle]) return;
      aisles[aisle].sort(function (a, b) { return a.name.localeCompare(b.name); });
      sortedAisles.push({
        id: aisle,
        label: AISLE_ORDER[aisle],
        items: aisles[aisle],
        total: round2(aisles[aisle].reduce(function (s, i) { return s + i.price; }, 0))
      });
    });

    var days = [], kcal = 0, prot = 0, time = 0;
    selected.forEach(function (recipe, i) {
      var others = selected.filter(function (_, j) { return j !== i; });
      var marginal = self.marginalCost(others, recipe, prefs);
      days.push({
        day: DAYS[i] || 'Jour ' + (i + 1),
        id: recipe.id, name: recipe.name, emoji: recipe.emoji,
        time: recipe.time, cuisine: recipe.cuisine, tags: recipe.tags,
        kcal: recipe.kcal, prot: recipe.prot, steps: recipe.steps,
        items: self.recipeLines(recipe, prefs),
        cost: marginal,
        costPerPerson: round2(marginal / prefs.people)
      });
      kcal += recipe.kcal; prot += recipe.prot; time += recipe.time;
    });

    var count = Math.max(1, selected.length);
    var meals = count * prefs.people;
    total = round2(total);

    var notice = null;
    if (total > prefs.budget + 0.01) {
      var suggested = Math.ceil(total / 5) * 5;
      notice = {
        type: 'budget_too_low',
        message: 'Avec ' + prefs.people + ' personne(s) sur ' + count + ' dîners, le panier le moins cher '
          + 'que nous ayons trouvé chez ' + (store ? store.name : '') + ' revient à ' + total + ' €. '
          + 'Comptez plutôt ' + suggested + ' € — ou réduisez le nombre de dîners.',
        minBudget: suggested
      };
    }

    return {
      ok: true,
      notice: notice,
      store: store ? { id: store.id, name: store.name, color: store.color } : null,
      prefs: prefs,
      days: days,
      shopping: {
        aisles: sortedAisles,
        pantry: pantryLines,
        lines: sortedAisles.reduce(function (s, a) { return s + a.items.length; }, 0)
      },
      totals: {
        budget: round2(prefs.budget),
        total: total,
        remaining: round2(prefs.budget - total),
        usedPct: Math.round(total / Math.max(prefs.budget, 0.01) * 100),
        perMeal: round2(total / meals),
        perDay: round2(total / count),
        people: prefs.people,
        days: count,
        savedVsReady: round2(Math.max(0, READYMEAL_PER_PERSON * meals - total)),
        wastePct: boughtValue > 0 ? Math.round(perishableLoss / boughtValue * 100) : 0,
        wasteValue: round2(perishableLoss),
        carryOver: round2(carryOver),
        feasible: total <= prefs.budget + 0.01,
        kcalPerMeal: Math.round(kcal / count),
        protPerMeal: Math.round(prot / count),
        cookMinutes: time
      }
    };
  };

  Engine.prototype.generate = function (prefs) {
    prefs = this.normalize(prefs);
    var candidates = this.candidates(prefs);

    if (candidates.length < prefs.days) {
      return {
        ok: false,
        error: 'not_enough_recipes',
        message: 'Avec ces filtres il ne reste que ' + candidates.length
          + ' recettes possibles. Retirez un allergène ou un ingrédient exclu.',
        available: candidates.length
      };
    }

    var selected = this.select(candidates, prefs);
    selected = this.fitBudget(selected, candidates, prefs);
    selected = this.orderByDay(selected);
    return this.buildPlan(selected, prefs);
  };

  Engine.prototype.swapDay = function (prefs, keepIds, replaceId) {
    var self = this;
    prefs = this.normalize(prefs);
    var candidates = this.candidates(prefs);

    var kept = keepIds.filter(function (id) { return id !== replaceId; })
      .map(function (id) { return self.catalog.recipes[id]; })
      .filter(Boolean);

    var pool = candidates.filter(function (r) {
      return r.id !== replaceId && keepIds.indexOf(r.id) < 0;
    });
    if (!pool.length) {
      return { ok: false, error: 'no_alternative', message: 'Aucune alternative disponible avec ces filtres.' };
    }

    var room = Math.max(0, prefs.budget - this.basketCost(this.needs(kept, prefs), prefs));
    var best = null, bestScore = -Infinity;

    pool.forEach(function (recipe) {
      var marginal = self.marginalCost(kept, recipe, prefs);
      var score = self.score(recipe, kept, marginal, prefs);
      if (marginal > room) score -= 3.0 * ((marginal - room) / Math.max(room, 1));
      score += jitter(recipe.id + prefs.seed) * 0.35;
      if (score > bestScore) { bestScore = score; best = recipe; }
    });

    return this.buildPlan(this.orderByDay(kept.concat([best])), prefs);
  };

  global.Budgeat = {
    Catalog: Catalog,
    Engine: Engine,
    DIET_LABELS: DIET_LABELS,
    ALLERGENS: ALLERGENS,
    PANTRY_DEFAULT: PANTRY_DEFAULT
  };
})(typeof window !== 'undefined' ? window : globalThis);
