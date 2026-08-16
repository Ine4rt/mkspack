<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$app = App::get();
$catalog = Catalog::load();
$stores = $catalog->storesByCountry($app->config['country']);
$quota = $app->quota();

// Ingrédients qu'on peut bannir : on ne propose pas le sel ni le poivre.
$excludable = array_values(array_filter(
    $catalog->ingredients,
    fn($i) => !$i['pantryDefault'] && $i['id'] !== 'sel-poivre'
));
usort($excludable, fn($a, $b) => strcmp($a['name'], $b['name']));

layout_head(
    'Composer ma semaine — ' . $app->config['app_name'],
    'Choisissez votre magasin et votre budget, obtenez 7 dîners et la liste de courses.',
    'app-page'
);
?>

<main class="wrap app-main">

<form id="config" class="config">
  <h1 class="config-title">Composons votre semaine</h1>

  <?php if (!$quota['unlimited']): ?>
    <p class="hint" style="margin-top:-.6rem;margin-bottom:1.6rem">
      <?php if ($quota['left'] > 0): ?>
        Formule découverte : <strong><?= (int) $quota['left'] ?> menu gratuit</strong> disponible cette semaine.
      <?php else: ?>
        Vous avez utilisé votre menu gratuit de la semaine.
        <a href="/tarifs.php">Passer en illimité</a>.
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <fieldset class="block">
    <legend>Votre magasin</legend>
    <div class="chips" id="stores">
      <?php foreach ($stores as $i => $store): ?>
        <label class="chip">
          <input type="radio" name="store" value="<?= e($store['id']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
          <span><span class="store-dot" style="background:<?= e($store['color']) ?>"></span><?= e($store['name']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <fieldset class="block">
    <legend>Votre budget pour la semaine</legend>
    <div class="budget-row">
      <input type="range" id="budgetRange" min="20" max="200" step="5" value="60">
      <div class="budget-value">
        <input type="number" id="budget" name="budget" min="10" max="500" step="1" value="60">
        <span>€</span>
      </div>
    </div>
    <p class="hint" id="budgetHint"></p>
  </fieldset>

  <div class="block-row">
    <fieldset class="block">
      <legend>Convives</legend>
      <div class="stepper">
        <button type="button" data-step="people" data-delta="-1" aria-label="Moins">−</button>
        <input type="number" id="people" name="people" min="1" max="12" value="2" readonly>
        <button type="button" data-step="people" data-delta="1" aria-label="Plus">+</button>
      </div>
    </fieldset>

    <fieldset class="block">
      <legend>Dîners à prévoir</legend>
      <div class="stepper">
        <button type="button" data-step="days" data-delta="-1" aria-label="Moins">−</button>
        <input type="number" id="days" name="days" min="1" max="7" value="7" readonly>
        <button type="button" data-step="days" data-delta="1" aria-label="Plus">+</button>
      </div>
    </fieldset>

    <fieldset class="block">
      <legend>Temps max en cuisine</legend>
      <select id="maxTime" name="maxTime">
        <option value="0">Peu importe</option>
        <option value="25">25 minutes</option>
        <option value="35">35 minutes</option>
        <option value="45">45 minutes</option>
      </select>
    </fieldset>
  </div>

  <fieldset class="block">
    <legend>Régime</legend>
    <div class="chips">
      <?php foreach (Catalog::DIET_LABELS as $id => $label): ?>
        <label class="chip">
          <input type="radio" name="diet" value="<?= e($id) ?>" <?= $id === 'omnivore' ? 'checked' : '' ?>>
          <span><?= e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <details class="block advanced">
    <summary>Allergies et aliments à éviter</summary>

    <p class="hint">Aucun menu ne contiendra ces allergènes, y compris après un remplacement.</p>
    <div class="chips">
      <?php foreach (Catalog::ALLERGENS as $id => $label): ?>
        <label class="chip">
          <input type="checkbox" name="allergens" value="<?= e($id) ?>">
          <span><?= e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <label class="field" style="margin-top:1.4rem">
      <span>Ingrédients que vous ne voulez pas voir</span>
      <select id="exclude" multiple size="6">
        <?php foreach ($excludable as $ing): ?>
          <option value="<?= e($ing['id']) ?>"><?= e($ing['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Maintenez Ctrl (ou ⌘) pour en sélectionner plusieurs.</span>
    </label>

    <label class="field">
      <span>
        <input type="checkbox" id="emptyPantry"> Je pars de zéro
      </span>
      <span class="hint">
        Par défaut, on considère que vous avez déjà sel, poivre, huile et épices de base.
        Cochez cette case pour les inclure dans le budget et la liste.
      </span>
    </label>
  </details>

  <button type="submit" class="btn btn-primary btn-lg btn-block" id="go">
    Composer ma semaine
  </button>
  <p class="hint center" style="margin-top:.8rem">Calcul instantané. Aucune carte bancaire.</p>
</form>

<div id="result" hidden></div>

</main>

<div id="paywall" class="modal" hidden>
  <div class="modal-card">
    <button class="modal-close" data-close aria-label="Fermer">×</button>
    <h2>Encore un menu ?</h2>
    <p id="paywallMsg" class="muted"></p>
    <ul class="check-list">
      <li>Menus illimités, à tout moment</li>
      <li>Remplacements illimités</li>
      <li>Historique et menus favoris</li>
      <li>Liste de courses à cocher et à partager</li>
    </ul>
    <a href="/tarifs.php" class="btn btn-primary btn-block">Voir les formules</a>
    <p class="hint center" style="margin-top:.8rem">Sans engagement · résiliable en un clic</p>
  </div>
</div>

<template id="tpl-recipe">
  <article class="day-card">
    <div class="day-head">
      <span class="day-emoji"></span>
      <div class="day-meta">
        <span class="day-name"></span>
        <h3 class="day-title"></h3>
        <div class="day-tags"></div>
      </div>
      <div class="day-price"></div>
    </div>
    <div class="day-actions">
      <button class="btn btn-ghost btn-sm" data-toggle>Voir la recette</button>
      <button class="btn btn-soft btn-sm" data-swap>Changer ce dîner</button>
    </div>
    <div class="day-body" hidden>
      <div class="day-cols">
        <div>
          <h4>Ingrédients</h4>
          <ul class="ing-list"></ul>
        </div>
        <div>
          <h4>Préparation</h4>
          <ol class="step-list"></ol>
        </div>
      </div>
    </div>
  </article>
</template>

<script src="/assets/app.js?v=1"></script>
<?php layout_foot(); ?>
