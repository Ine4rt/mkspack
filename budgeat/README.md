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

1. L'utilisateur choisit son magasin (20 enseignes belges et françaises), son budget
   hebdomadaire, le nombre de convives et de dîners.
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
- **Comparateur d'enseignes** : le même menu chiffré dans les 20 magasins, en réservant
  la fonction aux abonnés. Le moteur sait déjà le faire, il ne manque que l'écran.
- **Menus saisonniers** vendus à l'unité (fêtes, rentrée, batch cooking).

## Installation

Prérequis : PHP 8.1+ avec `pdo_sqlite` et `curl`. Aucun Composer, aucun build.

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
php tests/engine_test.php
```

47 assertions : cohérence du catalogue, respect du budget sur 8 scénarios, filtres
régime/allergènes/temps/exclusions, remplacement, liste de courses, performance.

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
- **Mettre à jour les prix** : les prix de référence sont dans `ingredients.json`,
  les indices par enseigne dans `stores.json`. Un relevé trimestriel sur une vingtaine
  de produits repères suffit à garder l'ensemble crédible.

## Limites connues

- Les prix sont des **estimations par enseigne**, pas des relevés magasin par magasin
  en temps réel. C'est écrit sur la page d'accueil, dans la FAQ et dans les CGV : mieux
  vaut annoncer une estimation honnête qu'un prix exact qui ne l'est pas.
- Le service ne couvre que les dîners.
- Les quantités par personne visent un adulte ; un foyer avec de jeunes enfants
  consommera moins.
