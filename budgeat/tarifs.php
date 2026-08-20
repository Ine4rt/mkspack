<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Billing.php';
require_once __DIR__ . '/lib/layout.php';

$app = App::get();
$billing = new Billing($app);
$plans = $app->config['plans'];
$csrf = $app->csrfToken();

layout_head(
    'Tarifs — ' . $app->config['app_name'],
    'Un menu gratuit par semaine, ou l\'accès illimité pour le prix d\'un plat préparé.'
);
?>

<section>
  <div class="wrap">
    <div class="section-head" style="margin-inline:auto;text-align:center">
      <h1>Un prix simple, sans surprise</h1>
      <p>Le premier menu est gratuit et complet. Vous ne payez que si le service vous fait
      réellement gagner du temps et de l'argent.</p>
    </div>

    <?php if (isset($_GET['erreur'])): ?>
      <div class="flash err narrow"><?= e((string) $_GET['erreur']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['paiement']) && $_GET['paiement'] === 'annule'): ?>
      <div class="flash narrow">Paiement annulé — aucun montant n'a été débité.</div>
    <?php endif; ?>
    <?php if (!$billing->isLive()): ?>
      <div class="flash narrow">
        <strong>Mode démonstration.</strong> Aucune clé de paiement n'est configurée :
        le tunnel fonctionne de bout en bout mais rien n'est débité. Renseignez vos clés
        Stripe dans <code>config.php</code> pour encaisser réellement.
      </div>
    <?php endif; ?>

    <div class="grid grid-3" style="margin-top:2rem">
      <div class="card price-card">
        <h3>Découverte</h3>
        <div class="price-tag">0 €</div>
        <p class="small muted">Pour voir si ça vous convient.</p>
        <ul class="check-list">
          <li>1 menu complet par semaine</li>
          <li>7 recettes détaillées</li>
          <li>Liste de courses chiffrée par rayon</li>
          <li>Régimes, allergènes, ingrédients bannis</li>
          <li class="off">2 remplacements par menu</li>
          <li class="off">Pas d'historique</li>
        </ul>
        <a href="/app.php" class="btn btn-ghost btn-block" style="margin-top:auto">Commencer gratuitement</a>
      </div>

      <div class="card price-card featured">
        <span class="price-badge">Le plus choisi</span>
        <h3>Mensuel</h3>
        <div class="price-tag">
          <?= e(number_format($plans['monthly']['price'] / 100, 2, ',', ' ')) ?> €<small>/mois</small>
        </div>
        <p class="small muted">Moins qu'un plat préparé pour deux.</p>
        <ul class="check-list">
          <li>Menus illimités</li>
          <li>Remplacements illimités</li>
          <li>Historique de vos semaines</li>
          <li>Liste à cocher, à imprimer et à partager</li>
          <li>Résiliable en un clic, à tout moment</li>
        </ul>
        <form method="post" action="/api/checkout.php" style="margin-top:auto">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="kind" value="monthly">
          <button type="submit" class="btn btn-primary btn-block">Passer en illimité</button>
        </form>
      </div>

      <div class="card price-card">
        <h3>À vie</h3>
        <div class="price-tag">
          <?= e(number_format($plans['lifetime']['price'] / 100, 2, ',', ' ')) ?> €<small> une fois</small>
        </div>
        <p class="small muted">
          Rentabilisé en <?= (int) ceil($plans['lifetime']['price'] / $plans['monthly']['price']) ?> mois.
        </p>
        <ul class="check-list">
          <li>Tout le mensuel, sans limite de durée</li>
          <li>Un seul paiement, jamais de renouvellement</li>
          <li>Les recettes ajoutées à l'avenir incluses</li>
          <li>Transférable sur tous vos appareils</li>
        </ul>
        <form method="post" action="/api/checkout.php" style="margin-top:auto">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="kind" value="lifetime">
          <button type="submit" class="btn btn-ghost btn-block">Payer une seule fois</button>
        </form>
      </div>
    </div>

    <p class="center small muted" style="margin-top:1.6rem">
      Il existe aussi une formule annuelle à
      <?= e(number_format($plans['yearly']['price'] / 100, 2, ',', ' ')) ?> €
      (<a href="/api/checkout.php" onclick="event.preventDefault();document.getElementById('yearly').submit()">la choisir</a>).
    </p>
    <form method="post" action="/api/checkout.php" id="yearly">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="kind" value="yearly">
    </form>
  </div>
</section>

<section style="background:var(--brand-soft);padding-top:2.5rem">
  <div class="narrow">
    <h2 style="text-align:center">Ce que nous garantissons</h2>
    <div class="grid grid-2" style="margin-top:1.6rem">
      <div class="card card-pad">
        <h3>14 jours pour changer d'avis</h3>
        <p class="small muted">Droit de rétractation légal, remboursement intégral sur simple
        demande à l'adresse indiquée dans nos <a href="/legal/cgv.php">CGV</a>. Sans justification.</p>
      </div>
      <div class="card card-pad">
        <h3>Résiliation immédiate</h3>
        <p class="small muted">Un bouton dans votre compte, pas un e-mail à écrire. L'accès reste
        actif jusqu'au terme de la période déjà payée.</p>
      </div>
      <div class="card card-pad">
        <h3>Vos données restent les vôtres</h3>
        <p class="small muted">Aucune revente à des tiers, aucun traceur publicitaire.
        Vous pouvez supprimer votre compte et vos menus quand vous le voulez.</p>
      </div>
      <div class="card card-pad">
        <h3>Parrainage</h3>
        <p class="small muted">Chaque personne inscrite avec votre lien vous offre
        <?= (int) $app->config['referral']['reward_months'] ?> mois lorsqu'elle s'abonne —
        et elle démarre elle-même avec
        <?= (int) $app->config['referral']['welcome_months'] ?> mois offert.</p>
      </div>
    </div>
  </div>
</section>

<?php layout_foot(); ?>
