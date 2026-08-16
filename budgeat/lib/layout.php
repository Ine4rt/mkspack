<?php
declare(strict_types=1);

require_once __DIR__ . '/App.php';

function layout_head(string $title, string $description = '', string $bodyClass = ''): void
{
    $app = App::get();
    $name = $app->config['app_name'];
    ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#1f7a4d">
<link rel="manifest" href="/manifest.json">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥕</text></svg>">
<link rel="stylesheet" href="/assets/app.css?v=1">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-head">
  <div class="wrap">
    <a class="logo" href="/"><span class="logo-mark">🥕</span><?= e($name) ?></a>
    <nav class="site-nav">
      <a href="/#fonctionnement" class="hide-sm">Comment ça marche</a>
      <a href="/tarifs.php" class="hide-sm">Tarifs</a>
      <?php if ($app->user()): ?>
        <a href="/compte.php">Mon compte</a>
      <?php else: ?>
        <a href="/connexion.php" class="hide-sm">Connexion</a>
      <?php endif; ?>
      <a href="/app.php" class="btn btn-primary btn-sm">Composer ma semaine</a>
    </nav>
  </div>
</header>
<?php
}

function layout_foot(): void
{
    $app = App::get();
    $name = $app->config['app_name'];
    ?>
<footer class="site-foot">
  <div class="wrap foot-grid">
    <div>
      <div class="logo" style="margin-bottom:.6rem"><span class="logo-mark">🥕</span><?= e($name) ?></div>
      <p class="small" style="max-width:34ch">Des dîners qui tiennent dans votre budget, aux prix réels de votre magasin.</p>
    </div>
    <div class="foot-links">
      <a href="/app.php">Composer ma semaine</a>
      <a href="/tarifs.php">Tarifs</a>
      <a href="/legal/mentions.php">Mentions légales</a>
      <a href="/legal/cgv.php">CGV</a>
      <a href="/legal/confidentialite.php">Confidentialité</a>
    </div>
  </div>
  <div class="wrap small" style="margin-top:2rem">
    © <?= date('Y') ?> <?= e($name) ?>. Les prix affichés sont des estimations calculées
    à partir de relevés par enseigne : ils peuvent différer de quelques centimes en caisse.
  </div>
</footer>
</body>
</html>
<?php
}
