# Budgeat

Application web qui compose vos dîners de la semaine **en tenant dans le budget que
vous fixez**, aux prix de l'enseigne où vous faites vos courses, et qui produit la
liste de courses chiffrée qui va avec.

Le principe reprend celui de services comme Pimzi (magasin + budget → semaine de dîners
+ liste de courses), avec un moteur de calcul différent et plusieurs manques comblés.
Le nom, les textes, les recettes et le code sont originaux : il ne s'agit pas d'une
copie d'un site tiers mais d'une implémentation propre du même service.

---

## Ce que ça fait

1. L'utilisateur choisit son magasin (21 enseignes belges), son budget hebdomadaire,
   le nombre de convives et de dîners.
2. Il précise son régime (6 profils), ses allergènes (13 filtres) et les ingrédients
   qu'il ne veut pas voir.
3. Le moteur compose la semaine, chaque dîner étant chiffré, et produit la liste de
   courses triée dans l'ordre du parcours en magasin, avec les quantités, les
   conditionnements et le total à prévoir en caisse.
4. Chaque dîner peut être remplacé ; la liste se recalcule.

## Ce qui change par rapport aux planificateurs classiques

| | Approche habituelle | Ici |
|---|---|---|
| **Coût d'une recette** | somme des ingrédients au prorata du poids | coût **marginal sur le panier réel** : on paie des paquets entiers, et un ingrédient déjà acheté pour un autre dîner ne recompte pas |
| **Prix** | une moyenne nationale | un indice par enseigne **et par rayon** (Lidl n'est pas cher partout de la même façon) |
| **Restes** | ignorés | séparés en pertes réelles (périssables) et report au placard (riz, conserves, épices) |
| **Placard** | tout est acheté | sel, huile et épices supposés présents par défaut, option « je pars de zéro » |
| **Budget infaisable** | un menu hors budget quand même | on l'annonce, avec le montant minimum réaliste |
| **Répartition** | aléatoire | plats longs le week-end, rapides en semaine |

C'est le calcul du panier réel qui fait la différence sur la promesse « pile dans votre
budget » : optimiser la somme des recettes ne donne pas le même résultat que d'optimiser
le ticket de caisse, et c'est le ticket que le client compare.

## Sources de revenus

Trois leviers sont implémentés :

1. **Abonnement** (cœur du modèle) — un menu gratuit par semaine, puis mensuel, annuel
   ou accès à vie. Stripe Checkout, webhooks, résiliation en un clic, prix dans
   `config.php`. Sans clé Stripe, le tunnel tourne en **mode démonstration** :
   le parcours complet fonctionne, rien n'est débité.
2. **Parrainage** — chaque compte a un lien ; le filleul démarre avec un mois offert,
   le parrain gagne un mois dès que son filleul s'abonne. C'est le canal d'acquisition
   le moins cher pour ce type de service.
3. **Affiliation courses en ligne** — `api/partenaire.php` transmet la liste de courses
   à un drive partenaire et trace le clic (table `affiliate_clicks`). Déclarez vos
   liens d'affilié dans `config.php > affiliates`, la commission tombe sur les paniers
   convertis.

Pistes complémentaires, non implémentées, par ordre d'effort croissant :

- **Recette sponsorisée** clairement identifiée (une marque paie pour figurer dans le
  vivier). À faire avec parcimonie : la crédibilité du service tient à la neutralité
  des prix.
- **Version « pro »** pour diététiciens et coachs : plusieurs profils clients, export
  des menus à leur nom. Panier moyen bien supérieur à celui du particulier.
- **Comparateur d'enseignes** : le même menu chiffré dans les 21 enseignes, en réservant
  la fonction aux abonnés. Le moteur sait déjà le faire, il ne manque que l'écran.
- **Menus saisonniers** vendus à l'unité (fêtes, rentrée, batch cooking).

## Installation

Prérequis : PHP 8.1+ avec `pdo_sqlite` et `curl`. Aucun Composer, aucun build.

### Par FTP (hébergement mutualisé)

```bash
php scripts/build_release.php      # produit dist/budgeat-AAAA-MM-JJ.zip
```

Décompressez, envoyez le contenu dans le dossier public, ouvrez
`https://votre-domaine/install.php` : la page contrôle l'hébergement, crée la base,
crée votre compte, puis vous demande de la supprimer. La marche à suivre détaillée
est dans `LISEZMOI.txt`, écrite pour être lue sans connaissance technique.

**Un point mérite votre attention.** Le `.htaccess` livré protège `storage/`, mais il
n'est lu que par Apache : sur un hébergement nginx, la base de données serait
téléchargeable. `install.php` teste réellement cet accès et vous alerte. Le fichier
porte aussi un nom aléatoire, ce qui le met hors de portée d'un scan automatique.
La seule protection complète reste de placer `storage/` **hors du dossier public** et
d'indiquer son chemin dans `config.php`.

### Manuellement

```bash
cp config.example.php config.php     # puis renseignez base_url, pays, prix, Stripe
mkdir -p storage && chmod 775 storage
```

Pointez le domaine (ou un sous-dossier) sur ce répertoire. La base SQLite et son schéma
sont créés au premier chargement. Tant que `base_url` n'est pas renseigné, l'URL est
déduite de la requête : le site fonctionne dès la copie des fichiers.

### Passer en encaissement réel

1. Créez un compte Stripe, récupérez `sk_...` et `pk_...` → `config.php`.
2. Déclarez le webhook `https://votre-domaine/api/webhook.php` sur les événements
   `checkout.session.completed`, `invoice.paid`, `customer.subscription.deleted`,
   et copiez le `whsec_...` dans `config.php`.
3. Complétez `legal/mentions.php` (identité de l'éditeur) — obligatoire pour vendre,
   et premier réflexe des acheteurs qui vérifient à qui ils ont affaire.

### Tests

```bash
php tests/engine_test.php     # 50 assertions — moteur, budgets, filtres, liste
php tests/prices_test.php     # 14 assertions — collecte ouverte et recalage
php tests/scraper_test.php    # 23 assertions — extraction sur les sites d'enseignes
php tests/package_test.php    # 13 assertions — contenu de l'archive de déploiement
```

Le premier couvre la cohérence du catalogue, le respect du budget sur 8 scénarios,
les filtres régime/allergènes/temps/exclusions, le remplacement d'un dîner, la liste
de courses et la performance. Le second couvre la chaîne de prix à partir d'une
réponse d'API figée : filtrage pays et enseigne, exclusion des promotions, conversion
vers les conditionnements, médiane, seuils, priorité des relevés manuels.

## Démo publique

`demo/budgeat-demo.html` est une page autonome (aucun serveur, aucune requête réseau) :
le moteur y est porté en JavaScript et les données sont inlinées. Régénérez-la après
toute modification du catalogue ou du style :

```bash
php scripts/build_demo.php
```

Le port JS suit le PHP pas à pas ; le PHP reste la référence, c'est lui qui est couvert
par les tests.

## Structure

```
budgeat/
├── index.php           landing (l'aperçu du hero est un vrai calcul, pas une maquette)
├── app.php             configuration + résultats
├── menu.php            menu enregistré, imprimable
├── tarifs.php compte.php connexion.php
├── api/                generate, swap, checkout, webhook, account, partenaire
├── lib/
│   ├── Catalog.php     chargement des données, prix par enseigne, régimes déduits
│   ├── Engine.php      sélection sous contrainte, panier réel, liste de courses
│   ├── App.php         config, SQLite, sessions, comptes, quotas
│   └── Billing.php     Stripe sans SDK, parrainage, résiliation
├── data/               stores.json · ingredients.json · recipes.json
├── install.php         diagnostic d'hébergement et création du premier compte
├── LISEZMOI.txt        notice d'installation par FTP, sans jargon
├── scripts/
│   ├── collect_stores.php  collecte sur les sites d'enseignes (robots.txt, cache)
│   ├── mapper_produits.php prépare les pages produits à renseigner
│   ├── diagnose_page.php   que contient une page, quels sélecteurs utiliser
│   ├── fetch_openprices.php collecte sur la base ouverte Open Prices
│   ├── carnet_releves.php  quels produits relever en magasin, par ordre d'impact
│   ├── import_prices.php   recalage du catalogue sur des relevés réels
│   ├── build_demo.php      assemblage de la démo autonome
│   └── build_release.php   archive prête pour le FTP
├── demo/               moteur porté en JS + page de démonstration
├── legal/              mentions, CGV, confidentialité
└── tests/engine_test.php
```

## Faire évoluer le catalogue

- **Ajouter une recette** : une entrée dans `data/recipes.json` avec les quantités
  *par personne*. Régime, allergènes, calories et protéines sont **déduits
  automatiquement** des ingrédients — impossible d'avoir une recette étiquetée
  végétarienne qui contient des lardons.
- **Ajouter un ingrédient** : `data/ingredients.json`, avec son conditionnement réel
  (`pack`) et sa durée de conservation (`shelf`), qui sert au calcul du gaspillage.
## Calibrer les prix — à faire avant toute mise en ligne

**Les prix livrés sont des ordres de grandeur posés à la main, pas des relevés.**
`data/stores.json` porte d'ailleurs un drapeau `"calibrated": false`. Ils suffisent à
faire tourner et démontrer le produit ; ils ne suffisent pas à tenir la promesse
« pile dans votre budget » devant un client payant.

Deux chemins, complémentaires : la collecte automatique couvre vite les produits
courants, le relevé terrain comble les trous et sert de référence.

### Option A — collecte sur les sites des enseignes

```bash
php scripts/mapper_produits.php 30 colruyt,delhaize,lidl,aldi   # une seule fois
# … vous collez les adresses des fiches produits dans data/collectors.json …
php scripts/collect_stores.php colruyt --limite=3               # essai
php scripts/collect_stores.php colruyt,delhaize,lidl,aldi       # collecte
php scripts/import_prices.php
```

C'est la source la plus juste : le prix vient du magasin où vos utilisateurs font
leurs courses. C'est aussi celle qui demande le plus de précautions.

**Ce que vous devez savoir avant de lancer.** Les prix sont des faits, mais les sites
qui les publient sont couverts par leurs conditions d'utilisation et, en Europe, par
un droit propre aux bases de données. Extraire des prix d'un site marchand est donc un
risque contractuel qui vous appartient. Le collecteur est écrit pour le réduire : il
lit `robots.txt` et renonce à ce qui est interdit, attend deux secondes entre deux
pages, s'annonce avec votre adresse de contact, met en cache, et **s'arrête net sur un
refus du site sans jamais chercher à le contourner**. Un passage par trimestre suffit à
garder un catalogue juste ; un robot qui tourne en continu se fait bloquer et donne
raison à l'enseigne.

**Ce qui demande votre travail.** L'extraction lit d'abord les données structurées
schema.org que publient la plupart des sites marchands — quand elles sont là, rien à
configurer. Il faut en revanche indiquer, une fois, quelle page correspond à quel
produit : c'est ce que prépare `mapper_produits.php`. Comptez deux à trois heures pour
les 30 produits qui pèsent le plus, puis la collecte se rejoue en une commande.

Si une enseigne ne rend rien, le diagnostic montre ce que contient la page et propose
les sélecteurs à corriger dans `data/collectors.json` :

```bash
php scripts/diagnose_page.php "https://…/une-fiche-produit"
```

Deux réserves franches : **je n'ai pas pu tester ces collecteurs sur les vrais sites**
(l'environnement de développement n'avait pas d'accès réseau), donc les sélecteurs de
repli sont des hypothèses — le diagnostic est là pour ça. Et **Lidl comme Aldi
publient peu de prix permanents en ligne** : sur ces deux enseignes, attendez-vous à
compléter en magasin.

### Option B — collecte automatique (Open Prices)

```bash
php scripts/fetch_openprices.php colruyt,aldi,delhaize,lidl
php scripts/import_prices.php --dry-run     # inspection
php scripts/import_prices.php               # application
```

[Open Prices](https://prices.openfoodfacts.org) est la base de prix d'Open Food Facts :
les relevés sont contribués par les utilisateurs, avec la photo de l'étiquette en
preuve, sous licence ouverte ODbL. C'est ce qui permet de les réutiliser légalement,
là où un scraping de site marchand se heurte aux conditions d'utilisation et au droit
sui generis sur les bases de données.

Le script interroge l'API par catégorie de produit, ne garde que les magasins belges
des enseignes demandées, **écarte les prix en promotion** (on cherche le prix habituel),
convertit les prix au kilo vers nos conditionnements, et retient la médiane quand il y
a au moins 3 relevés. Il ne touche jamais à un prix que vous avez saisi à la main :
un relevé terrain prime toujours sur la collecte.

Deux réserves à connaître :

- **La couverture dépend des contributions.** Sur les produits peu documentés en
  Belgique, le script ne trouvera rien et le dira ; ces produits restent estimés.
  Ce qui manque se complète par l'option B.
- **Les tags de catégorie du fichier `data/openprices_map.json` n'ont pas pu être
  vérifiés en ligne** (l'environnement de développement n'avait pas accès au réseau).
  Le script liste les catégories qui ne ramènent rien : corrigez le tag fautif dans ce
  fichier — il se vérifie sur `world.openfoodfacts.org/category/<tag>` — et relancez.

La logique du script est couverte par `tests/prices_test.php`, sur une réponse d'API
figée. L'appel réseau lui-même, non : c'est la première chose à vérifier chez vous.

### Option C — relevé terrain

C'est la méthode lente mais incontestable, et le complément naturel de la collecte
automatique sur les produits qu'elle ne couvre pas.

#### 1. Savoir quoi relever

```bash
php scripts/carnet_releves.php 30 colruyt,lidl,aldi,delhaize,carrefour
```

Le script simule 175 semaines et classe les produits par poids réel dans les paniers.
Les 30 premiers pèsent environ **76 % de la valeur des courses** : relever ceux-là
suffit, le reste peut rester estimé sans fausser le budget. Il écrit
`data/carnet.txt` (à imprimer et emporter en magasin) et `data/releves.csv`
(à compléter).

Comptez environ deux heures par enseigne pour 30 produits. Les prix des drives en
ligne font gagner du temps, mais vérifiez leurs conditions d'utilisation avant tout
relevé automatisé : un scraper qui tourne en continu se fait bloquer, et le sujet
n'est pas neutre juridiquement. Un relevé manuel trimestriel est plus lent mais
incontestable.

#### 2. Recaler le catalogue

```bash
php scripts/import_prices.php            # ajoute --dry-run pour voir sans écrire
```

Le prix d'un produit chez Delhaize est élevé pour deux raisons mêlées : le produit
lui-même et l'enseigne. Le script sépare les deux par quelques passes d'ajustement
alterné, sur des médianes (une promo isolée ne déforme donc pas le résultat). Il
recalcule les prix de référence, l'indice de chaque enseigne et ses écarts par rayon,
puis passe `calibrated` à `true`. Une enseigne avec moins de 5 relevés garde sa valeur
d'origine plutôt que d'être calibrée sur du vide.

Relancez ensuite `php tests/engine_test.php` : si les prix changent beaucoup, les
scénarios de budget vous diront tout de suite si les menus tiennent toujours.

## Limites connues

- Les prix sont des **estimations par enseigne** tant que le calibrage n'a pas été
  fait, et ensuite des moyennes — jamais des relevés magasin par magasin en temps
  réel. C'est écrit sur la page d'accueil, dans la FAQ et dans les CGV : mieux vaut
  annoncer une estimation honnête qu'un prix exact qui ne l'est pas.
- Les 58 recettes sont écrites pour ce projet. Elles couvrent le quotidien belge et
  français ; c'est peu pour un abonné qui reste un an, il faut prévoir d'en ajouter
  régulièrement (c'est aussi ce qui justifie l'abonnement).
- Le service ne couvre que les dîners.
- Les quantités par personne visent un adulte ; un foyer avec de jeunes enfants
  consommera moins.
