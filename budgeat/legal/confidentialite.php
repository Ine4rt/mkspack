<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';
$app = App::get();

layout_head('Confidentialité — ' . $app->config['app_name'], 'Quelles données sont collectées, pourquoi, et comment les supprimer.');
?>
<section>
  <div class="narrow legal">
    <h1>Politique de confidentialité</h1>
    <p class="muted small">Dernière mise à jour : <?= date('d/m/Y') ?></p>

    <h2>Ce que nous collectons</h2>
    <ul>
      <li><strong>Votre adresse e-mail et un mot de passe chiffré</strong>, si vous créez un compte.
          Le mot de passe est stocké sous forme de empreinte non réversible.</li>
      <li><strong>Vos préférences de menu</strong> : magasin, budget, nombre de convives, régime,
          allergènes, ingrédients écartés. Ce sont ces données qui permettent de composer vos semaines.</li>
      <li><strong>Vos menus enregistrés</strong>, pour que vous puissiez les retrouver.</li>
      <li><strong>Des statistiques d'usage anonymes</strong> (nombre de menus générés, budgets moyens),
          pour améliorer le moteur.</li>
    </ul>
    <p>
      Les allergies et régimes alimentaires peuvent révéler des informations sur votre santé ou
      vos convictions. Nous les traitons uniquement pour filtrer les recettes, jamais à des fins
      publicitaires, et nous ne les transmettons à personne.
    </p>

    <h2>Ce que nous ne faisons pas</h2>
    <ul>
      <li>Aucune revente ni partage de vos données à des tiers.</li>
      <li>Aucun traceur publicitaire, aucun pixel de réseau social.</li>
      <li>Aucune donnée bancaire sur nos serveurs : les paiements sont traités par Stripe.</li>
    </ul>

    <h2>Cookies</h2>
    <p>
      Un seul cookie est utilisé, strictement nécessaire au fonctionnement : il maintient votre
      session et compte les menus générés. Il ne sert à aucun suivi publicitaire et ne nécessite
      donc pas de bandeau de consentement. Votre navigateur conserve également localement votre
      liste de courses cochée : ces informations ne quittent jamais votre appareil.
    </p>

    <h2>Sous-traitants</h2>
    <ul>
      <li><strong>Stripe</strong> (paiement) — traite votre e-mail et vos données de carte.</li>
      <li><strong>[Votre hébergeur]</strong> — héberge le site et la base de données.</li>
    </ul>

    <h2>Durée de conservation</h2>
    <p>
      Vos menus et préférences sont conservés tant que votre compte existe. Les données de
      facturation sont conservées dix ans, comme l'impose la loi comptable. Les statistiques
      anonymes sont conservées vingt-quatre mois.
    </p>

    <h2>Vos droits</h2>
    <p>
      Vous pouvez à tout moment demander l'accès, la rectification, la portabilité ou
      l'effacement de vos données en écrivant à
      <a href="mailto:<?= e($app->config['mail_from']) ?>"><?= e($app->config['mail_from']) ?></a>.
      La suppression de votre compte entraîne celle de vos menus et préférences sous trente jours.
      Vous pouvez également introduire une réclamation auprès de l'autorité de protection des
      données de votre pays.
    </p>
  </div>
</section>
<?php layout_foot(); ?>
