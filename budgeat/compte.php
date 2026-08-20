<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$app = App::get();
$user = $app->user();
if ($user === null) {
    header('Location: /connexion.php');
    exit;
}

$premium = $app->isPremium();
$quota = $app->quota();
$history = $app->history();
$shareUrl = $app->url('/?p=' . $user['referral_code']);

// Nombre de filleuls déjà abonnés.
$stmt = $app->db->prepare('SELECT COUNT(*) FROM users WHERE referred_by = ? AND plan != "free"');
$stmt->execute([$user['id']]);
$godchildren = (int) $stmt->fetchColumn();

layout_head('Mon compte — ' . $app->config['app_name'], 'Vos menus, votre formule et votre lien de parrainage.');
?>

<section>
  <div class="narrow" style="max-width:760px">
    <h1>Mon compte</h1>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="flash <?= ($_GET['resiliation'] ?? '') === 'ko' ? 'err' : '' ?>">
        <?= e($_SESSION['flash']) ?>
      </div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if (isset($_GET['paiement']) && $_GET['paiement'] === 'ok'): ?>
      <div class="flash ok">
        <strong>C'est activé.</strong> Vous avez désormais accès aux menus illimités.
        <?= isset($_GET['demo']) ? ' (Paiement simulé : aucun montant n\'a été débité.)' : '' ?>
      </div>
    <?php endif; ?>

    <div class="card card-pad" style="margin-bottom:1.2rem">
      <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center">
        <div>
          <div class="small muted"><?= e($user['email']) ?></div>
          <h3 style="margin:.2rem 0">
            <?php
            $labels = ['free' => 'Formule découverte', 'monthly' => 'Illimité mensuel',
                       'yearly' => 'Illimité annuel', 'lifetime' => 'Accès à vie'];
            echo e($labels[$user['plan']] ?? $user['plan']);
            ?>
          </h3>
          <?php if ($premium && $user['plan'] !== 'lifetime' && $user['plan_until']): ?>
            <div class="small muted">
              Actif jusqu'au <?= e(date('d/m/Y', strtotime($user['plan_until']))) ?>
              <?= $user['stripe_sub'] ? ' · renouvellement automatique' : '' ?>
            </div>
          <?php elseif (!$premium): ?>
            <div class="small muted">
              <?= (int) $quota['left'] ?> menu gratuit restant cette semaine
            </div>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <?php if (!$premium): ?>
            <a href="/tarifs.php" class="btn btn-primary btn-sm">Passer en illimité</a>
          <?php elseif ($user['plan'] !== 'lifetime'): ?>
            <a href="/api/account.php?action=resilier" class="btn btn-ghost btn-sm"
               onclick="return confirm('Confirmer la résiliation ? Votre accès reste actif jusqu\'à la fin de la période payée.')">
               Résilier
            </a>
          <?php endif; ?>
          <a href="/api/account.php?action=logout" class="btn btn-ghost btn-sm">Déconnexion</a>
        </div>
      </div>
    </div>

    <div class="card card-pad" style="margin-bottom:1.2rem">
      <h3>Parrainage</h3>
      <p class="small muted">
        Partagez ce lien : la personne qui s'inscrit reçoit
        <?= (int) $app->config['referral']['welcome_months'] ?> mois offert, et vous gagnez
        <?= (int) $app->config['referral']['reward_months'] ?> mois dès qu'elle s'abonne.
      </p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <input type="text" id="refLink" value="<?= e($shareUrl) ?>" readonly style="flex:1;min-width:240px">
        <button type="button" class="btn btn-soft btn-sm" onclick="
          document.getElementById('refLink').select();
          navigator.clipboard.writeText(document.getElementById('refLink').value);
          this.textContent='Copié !';">Copier</button>
      </div>
      <p class="small muted" style="margin:.8rem 0 0">
        <?= $godchildren ?> filleul(s) abonné(s) grâce à vous.
      </p>
    </div>

    <h2 style="margin-top:2rem">Mes semaines</h2>
    <?php if ($history === []): ?>
      <div class="card card-pad center">
        <p class="muted">Aucun menu enregistré pour l'instant.</p>
        <a href="/app.php" class="btn btn-primary">Composer ma première semaine</a>
      </div>
    <?php else: ?>
      <div class="card">
        <?php foreach ($history as $row): ?>
          <div class="list-row">
            <div class="list-main">
              <span class="list-name">Semaine du <?= e(date('d/m/Y', strtotime($row['created_at']))) ?></span>
              <span class="list-detail">
                <?= e($app->money((float) $row['total'])) ?>
                sur <?= e($app->money((float) $row['budget'])) ?> de budget
              </span>
            </div>
            <div class="list-price">
              <a class="btn btn-ghost btn-sm" href="/menu.php?t=<?= e($row['token']) ?>">Revoir</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php layout_foot(); ?>
