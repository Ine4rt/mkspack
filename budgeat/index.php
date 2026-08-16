<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$app = App::get();
$catalog = Catalog::load();

// Un parrainage arrive par ?p=CODE : on le garde en session jusqu'à l'inscription.
if (isset($_GET['p'])) {
    $_SESSION['referral'] = strtoupper(substr((string) $_GET['p'], 0, 8));
}

// L'aperçu du hero n'est pas une maquette : c'est une vraie semaine calculée
// à l'instant, avec les prix de l'enseigne la moins chère.
$demo = $app->engine()->generate([
    'store'  => 'lidl',
    'budget' => 60,
    'people' => 2,
    'seed'   => date('W'),      // change chaque semaine
]);

$stores = $catalog->storesByCountry($app->config['country']);
$plans  = $app->config['plans'];

layout_head(
    $app->config['app_name'] . ' — vos dîners de la semaine, pile dans votre budget',
    'Choisissez votre magasin et votre budget : nous composons 7 dîners et la liste de courses correspondante, sans jamais dépasser.'
);
?>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow">🥕 <?= count($catalog->stores) ?> enseignes · prix réels</span>
      <h1>Vos dîners de la semaine,<br>pile dans votre budget.</h1>
      <p class="lede">
        Vous donnez votre magasin et votre budget. On vous rend 7 dîners, leurs recettes,
        et la liste de courses chiffrée rayon par rayon — sans jamais dépasser la somme
        que vous avez fixée.
      </p>
      <div class="hero-cta">
        <a href="/app.php" class="btn btn-primary btn-lg">Composer ma semaine — gratuit</a>
        <a href="/#fonctionnement" class="btn btn-ghost btn-lg">Voir comment ça marche</a>
      </div>
      <p class="hero-note">Sans carte bancaire. Le premier menu complet est offert.</p>
    </div>

    <div class="preview">
      <div class="card preview-card">
        <div class="preview-head">
          <div>
            <div class="small muted">Exemple calculé aujourd'hui · 2 personnes · Lidl</div>
            <div class="preview-total">
              <?= e($app->money($demo['totals']['total'])) ?>
              <small>/ <?= e($app->money($demo['totals']['budget'])) ?> de budget</small>
            </div>
          </div>
        </div>
        <div class="bar"><span style="width:<?= (int) $demo['totals']['usedPct'] ?>%"></span></div>
        <?php foreach (array_slice($demo['days'], 0, 5) as $day): ?>
          <div class="preview-row">
            <span class="emoji"><?= e($day['emoji']) ?></span>
            <span class="day"><?= e($day['day']) ?></span>
            <span><?= e($day['name']) ?></span>
            <span class="price"><?= e($app->money($day['costPerPerson'])) ?>/pers</span>
          </div>
        <?php endforeach; ?>
        <div class="preview-row" style="color:var(--ink-mute)">
          <span class="emoji">🧾</span>
          <span>+ <?= count($demo['days']) - 5 ?> autres dîners et la liste de courses complète</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <p class="small muted" style="margin-bottom:.8rem">Les prix de votre enseigne, pas une moyenne nationale :</p>
    <div class="stores">
      <?php foreach ($stores as $store): ?>
        <span class="store-chip">
          <span class="store-dot" style="background:<?= e($store['color']) ?>"></span><?= e($store['name']) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="fonctionnement">
  <div class="wrap">
    <div class="section-head">
      <h2>Trois réponses, et votre semaine est réglée</h2>
      <p>Comptez une minute. Aucune inscription pour essayer.</p>
    </div>
    <div class="grid grid-3 steps">
      <div class="card card-pad">
        <div class="step-num">1</div>
        <h3>Votre magasin</h3>
        <p class="muted">Lidl, Colruyt, Leclerc, Delhaize… Chaque enseigne a ses propres prix,
        et c'est sur ceux-là qu'on calcule. Un même menu ne coûte pas la même chose partout.</p>
      </div>
      <div class="card card-pad">
        <div class="step-num">2</div>
        <h3>Votre budget et vos contraintes</h3>
        <p class="muted">Le montant que vous ne voulez pas dépasser, le nombre de convives,
        votre régime, vos allergies, et les aliments que vous ne voulez pas voir dans l'assiette.</p>
      </div>
      <div class="card card-pad">
        <div class="step-num">3</div>
        <h3>Votre semaine, chiffrée</h3>
        <p class="muted">7 dîners, les recettes pas à pas, et la liste de courses triée
        par rayon avec les quantités exactes et le total avant de passer en caisse.</p>
      </div>
    </div>
  </div>
</section>

<section style="background:var(--brand-soft)">
  <div class="wrap">
    <div class="section-head">
      <h2>Ce qui change vraiment</h2>
      <p>La plupart des planificateurs additionnent le prix des ingrédients au gramme près.
      En caisse, vous payez des paquets entiers. C'est toute la différence entre une estimation et votre ticket.</p>
    </div>
    <div class="grid grid-2">
      <div class="card card-pad">
        <h3>🧾 On compte le panier, pas la recette</h3>
        <p class="muted">Un paquet de 500 g de pâtes coûte le même prix que vous en utilisiez
        100 g ou 400 g. Nos calculs partent des conditionnements réellement vendus en rayon,
        donc le total affiché est celui du ticket.</p>
      </div>
      <div class="card card-pad">
        <h3>♻️ Les restes servent aux autres repas</h3>
        <p class="muted">Le moteur compose des semaines où les ingrédients se recoupent :
        la botte de persil, le pot de crème et le sachet d'épinards sont utilisés jusqu'au bout.
        Vous voyez exactement ce qui reste, et ce qui repart au placard.</p>
      </div>
      <div class="card card-pad">
        <h3>🗓️ Les plats longs tombent le week-end</h3>
        <p class="muted">Personne n'a envie d'un mijoté de 1 h 30 un mardi soir. Les recettes
        rapides sont placées en semaine, les plus longues le samedi et le dimanche.</p>
      </div>
      <div class="card card-pad">
        <h3>🔄 Un dîner ne vous plaît pas ?</h3>
        <p class="muted">Remplacez-le d'un clic. L'alternative respecte le budget restant,
        votre régime et vos allergies, et la liste de courses se recalcule immédiatement.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap grid grid-4 center">
    <div class="card card-pad">
      <div class="stat"><?= count($catalog->recipes) ?></div>
      <div class="small muted">recettes du quotidien, testées et chiffrées</div>
    </div>
    <div class="card card-pad">
      <div class="stat"><?= count($catalog->stores) ?></div>
      <div class="small muted">enseignes avec leur propre grille de prix</div>
    </div>
    <div class="card card-pad">
      <div class="stat"><?= e($app->money($demo['totals']['perMeal'])) ?></div>
      <div class="small muted">le prix d'une assiette dans l'exemple ci-dessus</div>
    </div>
    <div class="card card-pad">
      <div class="stat"><?= (int) $demo['totals']['cookMinutes'] / 7 < 1 ? 0 : (int) round($demo['totals']['cookMinutes'] / 7) ?> min</div>
      <div class="small muted">de cuisine par soir en moyenne</div>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div class="section-head">
      <h2>Vos contraintes sont des contraintes, pas des préférences</h2>
      <p>Un allergène coché ne réapparaît jamais dans un menu, y compris après un remplacement.</p>
    </div>
    <div class="grid grid-2">
      <div class="card card-pad">
        <h3>Régimes</h3>
        <div class="stores" style="margin-top:.8rem">
          <?php foreach (Catalog::DIET_LABELS as $label): ?>
            <span class="store-chip"><?= e($label) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card card-pad">
        <h3>Allergènes et aliments écartés</h3>
        <div class="stores" style="margin-top:.8rem">
          <?php foreach (Catalog::ALLERGENS as $label): ?>
            <span class="store-chip"><?= e($label) ?></span>
          <?php endforeach; ?>
        </div>
        <p class="small muted" style="margin-top:.9rem">
          Vous pouvez aussi bannir un ingrédient précis — les champignons, la coriandre,
          ce que vous voulez.
        </p>
      </div>
    </div>
  </div>
</section>

<section id="tarifs" style="background:var(--brand-soft)">
  <div class="wrap">
    <div class="section-head">
      <h2>Le premier menu est offert</h2>
      <p>Essayez sur une vraie semaine de courses. Si ça vous fait gagner du temps et de l'argent,
      l'accès complet coûte moins cher qu'un plat préparé.</p>
    </div>
    <div class="grid grid-3">
      <div class="card price-card">
        <h3>Découverte</h3>
        <div class="price-tag">0 €</div>
        <ul class="check-list">
          <li>1 menu complet par semaine</li>
          <li>Recettes et liste de courses chiffrée</li>
          <li>Régimes et allergènes</li>
          <li class="off">2 remplacements par menu</li>
          <li class="off">Pas d'historique</li>
        </ul>
        <a href="/app.php" class="btn btn-ghost btn-block" style="margin-top:auto">Commencer</a>
      </div>
      <div class="card price-card featured">
        <span class="price-badge">Le plus choisi</span>
        <h3>Mensuel</h3>
        <div class="price-tag"><?= e(explode('/', $plans['monthly']['label'])[0]) ?><small>/mois</small></div>
        <ul class="check-list">
          <li>Menus illimités</li>
          <li>Remplacements illimités</li>
          <li>Historique et menus favoris</li>
          <li>Liste de courses à cocher et à partager</li>
          <li>Sans engagement, résiliable en un clic</li>
        </ul>
        <a href="/tarifs.php" class="btn btn-primary btn-block" style="margin-top:auto">Choisir cette formule</a>
      </div>
      <div class="card price-card">
        <h3>À vie</h3>
        <div class="price-tag"><?= e(explode(' une', $plans['lifetime']['label'])[0]) ?><small> une fois</small></div>
        <ul class="check-list">
          <li>Tout le mensuel, pour toujours</li>
          <li>Un seul paiement, aucun renouvellement</li>
          <li>Toutes les recettes ajoutées à l'avenir</li>
          <li>Rentabilisé en <?= (int) ceil($app->config['plans']['lifetime']['price'] / $app->config['plans']['monthly']['price']) ?> mois</li>
        </ul>
        <a href="/tarifs.php" class="btn btn-ghost btn-block" style="margin-top:auto">Choisir cette formule</a>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="narrow">
    <h2>Questions fréquentes</h2>

    <details class="faq" open>
      <summary>Les prix affichés sont-ils vraiment ceux de mon magasin ?</summary>
      <p>Ce sont des prix de référence par enseigne, ajustés rayon par rayon à partir de relevés
      réguliers. Ils sont fiables à quelques centimes près sur les produits courants, mais une
      promotion locale ou un changement de fournisseur peut créer un écart. Nous affichons donc
      toujours le prix au kilo et le conditionnement retenu, pour que vous puissiez vérifier en rayon.</p>
    </details>

    <details class="faq">
      <summary>Que se passe-t-il si mon budget est trop serré ?</summary>
      <p>Le moteur remplace d'abord les dîners les plus chers par des équivalents moins coûteux.
      Si même la semaine la moins chère dépasse votre budget, on vous le dit franchement, avec le
      montant minimum réaliste pour le nombre de convives. On ne vous livre jamais un menu
      soi-disant dans le budget qui ne l'est pas.</p>
    </details>

    <details class="faq">
      <summary>Faut-il créer un compte ?</summary>
      <p>Non pour essayer : le premier menu complet s'obtient sans inscription. Le compte devient
      utile pour retrouver vos menus, garder vos préférences et passer en illimité.</p>
    </details>

    <details class="faq">
      <summary>Et si je n'aime pas un plat proposé ?</summary>
      <p>Chaque dîner peut être remplacé. L'alternative proposée respecte votre budget restant,
      votre régime, vos allergènes et vos ingrédients bannis. La liste de courses se met à jour
      dans la foulée.</p>
    </details>

    <details class="faq">
      <summary>Comment résilier l'abonnement ?</summary>
      <p>Depuis votre compte, en un clic, sans avoir à écrire à qui que ce soit. L'accès reste
      actif jusqu'à la fin de la période déjà payée. Vous disposez également d'un droit de
      rétractation de 14 jours, détaillé dans nos <a href="/legal/cgv.php">conditions de vente</a>.</p>
    </details>

    <details class="faq">
      <summary>Les midis et les petits-déjeuners sont-ils prévus ?</summary>
      <p>Pas encore : nous nous concentrons sur le dîner, le repas qui pose le plus de questions
      en semaine. Les restes des recettes marquées « batch » couvrent souvent le déjeuner du lendemain,
      et c'est indiqué sur la fiche.</p>
    </details>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap card card-pad center" style="padding:3rem 1.5rem">
    <h2 style="margin-bottom:.4rem">Votre semaine est à une minute d'ici</h2>
    <p class="muted" style="max-width:48ch;margin-inline:auto">
      Choisissez votre magasin, fixez votre budget, et voyez ce que ça donne.
    </p>
    <a href="/app.php" class="btn btn-primary btn-lg" style="margin-top:1rem">Composer ma semaine — gratuit</a>
  </div>
</section>

<?php layout_foot(); ?>
