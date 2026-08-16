<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';
$app = App::get();
$plans = $app->config['plans'];

layout_head('Conditions générales de vente — ' . $app->config['app_name'], 'Abonnement, résiliation et droit de rétractation.');
?>
<section>
  <div class="narrow legal">
    <h1>Conditions générales de vente</h1>
    <p class="muted small">Dernière mise à jour : <?= date('d/m/Y') ?></p>

    <div class="flash">
      <strong>Modèle à faire relire.</strong> Ce texte couvre les obligations habituelles d'un
      service par abonnement, mais il doit être complété avec votre identité d'entreprise
      (voir les <a href="/legal/mentions.php">mentions légales</a>) et validé selon le pays
      depuis lequel vous vendez.
    </div>

    <h2>1. Objet</h2>
    <p>
      <?= e($app->config['app_name']) ?> est un service numérique de planification de repas.
      À partir du magasin, du budget et des contraintes alimentaires que vous indiquez, il
      compose des menus de dîners, les recettes correspondantes et une liste de courses chiffrée.
    </p>
    <p>
      Le service ne vend, ne livre et ne prépare aucun produit alimentaire. Il ne se substitue
      pas à un avis médical ou diététique : si vous suivez un régime prescrit, vérifiez les
      menus avec un professionnel de santé.
    </p>

    <h2>2. Formules et prix</h2>
    <ul>
      <li><strong>Découverte (gratuite)</strong> : un menu complet par semaine, sans engagement ni carte bancaire.</li>
      <li><strong>Mensuelle</strong> : <?= e(number_format($plans['monthly']['price'] / 100, 2, ',', ' ')) ?> € TTC par mois,
          reconduite automatiquement chaque mois jusqu'à résiliation.</li>
      <li><strong>Annuelle</strong> : <?= e(number_format($plans['yearly']['price'] / 100, 2, ',', ' ')) ?> € TTC par an,
          reconduite automatiquement chaque année jusqu'à résiliation.</li>
      <li><strong>Accès à vie</strong> : <?= e(number_format($plans['lifetime']['price'] / 100, 2, ',', ' ')) ?> € TTC,
          paiement unique, sans reconduction.</li>
    </ul>
    <p>
      Les prix sont indiqués toutes taxes comprises, en euros. « Accès à vie » s'entend pour
      la durée d'exploitation du service ; en cas d'arrêt définitif du service, les
      utilisateurs concernés en sont informés au moins trois mois à l'avance.
    </p>

    <h2>3. Paiement</h2>
    <p>
      Les paiements sont traités par notre prestataire Stripe. Aucune donnée de carte bancaire
      ne transite ni n'est conservée sur nos serveurs. Une facture est disponible sur demande à
      <a href="mailto:<?= e($app->config['mail_from']) ?>"><?= e($app->config['mail_from']) ?></a>.
    </p>

    <h2>4. Reconduction et résiliation</h2>
    <p>
      Les formules mensuelle et annuelle sont reconduites automatiquement à échéance. Vous
      pouvez y mettre fin à tout moment depuis la page « Mon compte », en un clic et sans avoir
      à justifier votre décision. La résiliation interrompt le prochain prélèvement :
      <strong>votre accès reste actif jusqu'au terme de la période déjà payée</strong>, et
      aucune somme supplémentaire n'est prélevée.
    </p>

    <h2>5. Droit de rétractation</h2>
    <p>
      Vous disposez de quatorze (14) jours à compter de la souscription pour vous rétracter et
      obtenir le remboursement intégral, sans avoir à vous justifier. Il suffit d'écrire à
      <a href="mailto:<?= e($app->config['mail_from']) ?>"><?= e($app->config['mail_from']) ?></a>.
      Le remboursement intervient sous quatorze jours par le moyen de paiement d'origine.
    </p>
    <p>
      En souscrivant, vous demandez l'exécution immédiate du service. Conformément au droit de
      la consommation, nous renonçons volontairement à opposer la perte du droit de
      rétractation liée à cette exécution immédiate : le délai de 14 jours vous reste acquis.
    </p>

    <h2>6. Exactitude des prix alimentaires</h2>
    <p>
      Les montants affichés dans les listes de courses sont des estimations calculées à partir
      de relevés de prix par enseigne, mis à jour régulièrement. Les prix pratiqués varient
      selon les magasins, les promotions et les périodes : un écart avec votre ticket de caisse
      est possible et ne constitue pas un défaut du service. Nous affichons systématiquement le
      prix au kilo ou au litre retenu ainsi que le conditionnement, pour que vous puissiez
      vérifier en rayon.
    </p>

    <h2>7. Disponibilité</h2>
    <p>
      Nous nous efforçons d'assurer un accès continu au service. Des interruptions peuvent
      survenir pour maintenance ou pour des causes indépendantes de notre volonté. Une
      indisponibilité durable supérieure à sept jours consécutifs ouvre droit, sur demande,
      à un remboursement au prorata.
    </p>

    <h2>8. Parrainage</h2>
    <p>
      Chaque compte dispose d'un lien de parrainage. La personne inscrite via ce lien reçoit
      <?= (int) $app->config['referral']['welcome_months'] ?> mois d'accès offert ; le parrain
      reçoit <?= (int) $app->config['referral']['reward_months'] ?> mois lorsque son filleul
      souscrit une formule payante. Les mois offerts ne sont ni cessibles ni convertibles en
      espèces. Toute création de comptes fictifs entraîne l'annulation des avantages.
    </p>

    <h2>9. Données personnelles</h2>
    <p>
      Le traitement de vos données est décrit dans notre
      <a href="/legal/confidentialite.php">politique de confidentialité</a>.
    </p>

    <h2>10. Droit applicable</h2>
    <p>
      Les présentes conditions sont soumises au droit [pays de l'éditeur]. En cas de litige,
      une solution amiable sera recherchée avant toute action judiciaire. Le consommateur peut
      saisir gratuitement le médiateur de la consommation mentionné dans nos mentions légales.
    </p>
  </div>
</section>
<?php layout_foot(); ?>
