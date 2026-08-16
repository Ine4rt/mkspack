<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$app = App::get();
if ($app->user()) {
    header('Location: /compte.php');
    exit;
}

$mode = ($_GET['mode'] ?? 'connexion') === 'inscription' ? 'inscription' : 'connexion';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$referral = $_SESSION['referral'] ?? '';

layout_head(
    ($mode === 'inscription' ? 'Créer un compte' : 'Connexion') . ' — ' . $app->config['app_name'],
    'Retrouvez vos menus et vos préférences.'
);
?>

<section>
  <div class="narrow" style="max-width:440px">
    <h1><?= $mode === 'inscription' ? 'Créer un compte' : 'Se connecter' ?></h1>

    <?php if (!empty($_GET['suite'])): ?>
      <div class="flash">Créez votre compte pour finaliser votre abonnement.</div>
    <?php endif; ?>
    <?php if ($flash): ?>
      <div class="flash err"><?= e($flash) ?></div>
    <?php endif; ?>
    <?php if ($mode === 'inscription' && $referral): ?>
      <div class="flash ok">
        Parrainage <strong><?= e($referral) ?></strong> appliqué :
        <?= (int) $app->config['referral']['welcome_months'] ?> mois offert à l'inscription.
      </div>
    <?php endif; ?>

    <form method="post" action="/api/account.php" class="card card-pad">
      <input type="hidden" name="csrf" value="<?= e($app->csrfToken()) ?>">
      <input type="hidden" name="action" value="<?= $mode === 'inscription' ? 'register' : 'login' ?>">
      <?php if ($referral): ?>
        <input type="hidden" name="referral" value="<?= e($referral) ?>">
      <?php endif; ?>

      <label class="field">
        <span>Adresse e-mail</span>
        <input type="email" name="email" required autocomplete="email" autofocus>
      </label>

      <label class="field">
        <span>Mot de passe</span>
        <input type="password" name="password" required minlength="8"
               autocomplete="<?= $mode === 'inscription' ? 'new-password' : 'current-password' ?>">
        <?php if ($mode === 'inscription'): ?>
          <span class="hint">8 caractères minimum.</span>
        <?php endif; ?>
      </label>

      <button class="btn btn-primary btn-block">
        <?= $mode === 'inscription' ? 'Créer mon compte' : 'Se connecter' ?>
      </button>
    </form>

    <p class="center small" style="margin-top:1.2rem">
      <?php if ($mode === 'inscription'): ?>
        Déjà inscrit ? <a href="/connexion.php">Se connecter</a>
      <?php else: ?>
        Pas encore de compte ? <a href="/connexion.php?mode=inscription">En créer un</a>
      <?php endif; ?>
    </p>

    <p class="center small muted">
      Un compte n'est pas nécessaire pour essayer :
      <a href="/app.php">composer une semaine tout de suite</a>.
    </p>
  </div>
</section>

<?php layout_foot(); ?>
