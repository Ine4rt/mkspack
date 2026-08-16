<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$app = App::get();
$record = $app->loadPlan((string) ($_GET['t'] ?? ''));

if ($record === null || $record['owner_key'] !== $app->ownerKey()) {
    http_response_code(404);
    layout_head('Menu introuvable — ' . $app->config['app_name']);
    echo '<section class="narrow center"><h1>Ce menu n\'existe plus</h1>'
       . '<p class="muted">Le lien est peut-être expiré, ou il appartient à un autre compte.</p>'
       . '<a class="btn btn-primary" href="/app.php">Composer une nouvelle semaine</a></section>';
    layout_foot();
    exit;
}

$plan = $record['plan'];
$totals = $plan['totals'];
$date = date('d/m/Y', strtotime($record['created_at']));

layout_head("Semaine du $date — " . $app->config['app_name'], 'Menu enregistré et liste de courses.');
?>

<main class="wrap app-main">
  <div class="card summary">
    <div class="summary-head">
      <div>
        <div class="small muted">
          Semaine du <?= e($date) ?> ·
          <?= (int) $totals['days'] ?> dîners ·
          <?= (int) $totals['people'] ?> personne<?= $totals['people'] > 1 ? 's' : '' ?>
          <?= $plan['store'] ? ' · ' . e($plan['store']['name']) : '' ?>
        </div>
        <div class="summary-total">
          <strong><?= e($app->money((float) $totals['total'])) ?></strong>
          <small>sur <?= e($app->money((float) $totals['budget'])) ?> de budget</small>
        </div>
      </div>
      <div class="summary-actions">
        <a href="/app.php" class="btn btn-ghost btn-sm">Nouvelle semaine</a>
        <button class="btn btn-ghost btn-sm" onclick="window.print()">Imprimer</button>
      </div>
    </div>
    <div class="bar"><span style="width:<?= min(100, (int) $totals['usedPct']) ?>%"></span></div>
    <div class="summary-stats">
      <div class="summary-stat"><strong><?= e($app->money((float) $totals['perMeal'])) ?></strong><span>par assiette</span></div>
      <div class="summary-stat"><strong><?= e($app->money((float) $totals['remaining'])) ?></strong><span>restant sur le budget</span></div>
      <div class="summary-stat"><strong><?= (int) $totals['wastePct'] ?> %</strong><span>de pertes estimées</span></div>
      <div class="summary-stat"><strong><?= (int) $totals['protPerMeal'] ?> g</strong><span>de protéines par assiette</span></div>
      <div class="summary-stat"><strong><?= (int) round($totals['cookMinutes'] / max(1, $totals['days'])) ?> min</strong><span>de cuisine par soir</span></div>
      <div class="summary-stat"><strong><?= e($app->money((float) $totals['savedVsReady'])) ?></strong><span>économisés vs plats préparés</span></div>
    </div>
  </div>

  <h2>Les dîners</h2>
  <div class="days-pane">
    <?php foreach ($plan['days'] as $day): ?>
      <article class="day-card">
        <div class="day-head">
          <span class="day-emoji"><?= e($day['emoji']) ?></span>
          <div class="day-meta">
            <span class="day-name"><?= e($day['day']) ?></span>
            <h3 class="day-title"><?= e($day['name']) ?></h3>
            <div class="day-tags">
              <span class="tag"><?= (int) $day['time'] ?> min</span>
              <span class="tag"><?= e($day['cuisine']) ?></span>
            </div>
          </div>
          <div class="day-price">
            <strong><?= e($app->money((float) $day['costPerPerson'])) ?></strong>
            <span>par personne</span>
          </div>
        </div>
        <div class="day-body">
          <div class="day-cols">
            <div>
              <h4>Ingrédients</h4>
              <ul class="ing-list">
                <?php foreach ($day['items'] as $item): ?>
                  <li>
                    <span><?= e($item['name']) ?></span>
                    <span class="qty"><?= e($item['qty']) ?><?= $item['pantry'] ? ' · placard' : '' ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div>
              <h4>Préparation</h4>
              <ol class="step-list">
                <?php foreach ($day['steps'] as $step): ?>
                  <li><?= e($step) ?></li>
                <?php endforeach; ?>
              </ol>
            </div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <h2 style="margin-top:2.5rem">Liste de courses</h2>
  <?php foreach ($plan['shopping']['aisles'] as $aisle): ?>
    <section class="aisle card">
      <div class="aisle-head">
        <h3><?= e($aisle['label']) ?></h3>
        <span class="small muted"><?= e($app->money((float) $aisle['total'])) ?></span>
      </div>
      <?php foreach ($aisle['items'] as $item): ?>
        <div class="list-row">
          <input type="checkbox">
          <div class="list-main">
            <span class="list-name"><?= e($item['name']) ?></span>
            <span class="list-detail"><?= (int) $item['packs'] ?> × <?= e($item['packLabel']) ?></span>
          </div>
          <div class="list-price">
            <strong><?= e($app->money((float) $item['price'])) ?></strong>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <?php if (!empty($plan['shopping']['pantry'])): ?>
    <section class="aisle card pantry">
      <div class="aisle-head"><h3>Déjà dans vos placards</h3><span class="small muted">non compté</span></div>
      <p class="small muted pantry-list">
        <?= e(implode(' · ', array_map(
            fn($i) => $i['name'] . ' (' . $i['needLabel'] . ')',
            $plan['shopping']['pantry']
        ))) ?>
      </p>
    </section>
  <?php endif; ?>
</main>

<?php layout_foot(); ?>
