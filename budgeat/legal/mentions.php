<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';
$app = App::get();

layout_head('Mentions légales — ' . $app->config['app_name'], 'Éditeur, hébergeur et contact.');
?>
<section>
  <div class="narrow legal">
    <h1>Mentions légales</h1>
    <p class="muted small">Dernière mise à jour : <?= date('d/m/Y') ?></p>

    <div class="flash">
      <strong>À compléter avant mise en ligne.</strong> Les champs entre crochets doivent
      contenir vos informations réelles : la loi impose que l'éditeur d'un site marchand
      soit identifiable. C'est aussi le premier critère regardé par les visiteurs
      qui vérifient si un service est sérieux avant de sortir leur carte.
    </div>

    <h2>Éditeur du site</h2>
    <p>
      [Dénomination sociale ou nom et prénom]<br>
      [Forme juridique et capital social, le cas échéant]<br>
      [Adresse complète du siège social]<br>
      Numéro d'entreprise / SIRET : [numéro]<br>
      Numéro de TVA intracommunautaire : [numéro]<br>
      Directeur de la publication : [nom]<br>
      Contact : <a href="mailto:<?= e($app->config['mail_from']) ?>"><?= e($app->config['mail_from']) ?></a>
    </p>

    <h2>Hébergement</h2>
    <p>
      [Nom de l'hébergeur]<br>
      [Adresse]<br>
      [Téléphone]
    </p>

    <h2>Propriété intellectuelle</h2>
    <p>
      Les recettes, textes, visuels et le moteur de composition de menus sont la propriété de
      l'éditeur. Vous pouvez imprimer et partager vos menus pour votre usage personnel.
      Toute reproduction du catalogue de recettes ou des données de prix à des fins
      commerciales est interdite sans accord écrit.
    </p>

    <h2>Prix affichés</h2>
    <p>
      Les prix des produits alimentaires présentés dans les listes de courses sont des
      <strong>estimations</strong> établies à partir de relevés par enseigne. Ils ne constituent
      pas une offre de vente : <?= e($app->config['app_name']) ?> ne vend aucun produit
      alimentaire et n'est affilié à aucune des enseignes citées. Les marques mentionnées
      appartiennent à leurs propriétaires respectifs et ne sont utilisées qu'à titre
      d'information, pour vous permettre de choisir le magasin où vous faites vos courses.
    </p>

    <h2>Médiation de la consommation</h2>
    <p>
      Conformément à la réglementation, en cas de litige non résolu avec notre service client,
      vous pouvez recourir gratuitement à un médiateur de la consommation :
      [nom et coordonnées du médiateur]. Vous pouvez également utiliser la plateforme
      européenne de règlement en ligne des litiges.
    </p>
  </div>
</section>
<?php layout_foot(); ?>
