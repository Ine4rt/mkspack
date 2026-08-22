# NEON SURGE

Jeu d'arcade mobile du genre « foule + multiplicateurs » : un canon tire un flux
continu d'unités qui remonte une piste, traverse des portails qui multiplient ou
réduisent la foule, casse des murs, capture des tours, et va détruire le noyau
adverse avant la fin du chrono.

Web pur (HTML/CSS/JS), **aucune dépendance, aucun asset externe** : tous les
graphismes sont dessinés au trait dans le canvas et les sons sont synthétisés via
la Web Audio API. Le jeu tient dans trois fichiers.

## Lancer

```bash
npx http-server -p 8080 .      # ou n'importe quel serveur statique
```

Puis ouvrir `http://localhost:8080/`. Un simple double-clic sur `index.html`
fonctionne aussi. Sur téléphone, « Ajouter à l'écran d'accueil » le lance en
plein écran (manifeste PWA fourni).

## Commandes

- **Mobile** : glisser le doigt n'importe où sur l'écran pour déplacer le canon.
- **Desktop** : glisser à la souris, ou flèches gauche/droite.

Le tir est automatique — tout le jeu tient dans le choix du couloir.

## Boucle de jeu

| Élément | Effet |
|---|---|
| Portail `×N` / `+N` | Multiplie ou augmente le paquet d'unités qui le traverse |
| Portail `÷N` / `−N` | Divise ou retranche |
| Mur | Absorbe une unité par point de vie, puis cède |
| Tour | Produit des unités pour son camp ; se capture en l'attaquant, et se reprend |
| Noyau | Chaque unité arrivée retire 1 PV ; à 0, la manche est gagnée |

Les unités adverses s'annihilent une pour une au contact. Les crédits gagnés
achètent trois améliorations permanentes : cadence, volée, propulsion.

## Notes de conception

**Couloir d'or.** Une unité conserve sa position latérale après le tir : le
joueur choisit donc un *couloir* et non un portail isolé. Le générateur garantit
qu'au moins un couloir traverse tout le niveau en n'enchaînant que des bonus —
sans cette garantie, un tirage aléatoire produit régulièrement des niveaux sans
aucun trajet viable.

**PV du noyau calibrés, pas fixés.** Les points de vie ne sont pas une simple
fonction du numéro de niveau : ils sont déduits du potentiel réel du niveau tiré
(meilleur trajet possible à travers les rangées), puis bornés par le débit
maximal livrable — la foule sature à `MAXU` unités, donc au-delà d'environ ×22
un multiplicateur n'apporte plus rien. Sans ce calibrage, un tirage riche en `×N`
se gagnait en 4 s et un tirage additif était infaisable.

**Pression ennemie.** Le débit adverse doit rester sous le débit brut du canon.
Au-delà, la foule est annihilée *avant* d'atteindre les multiplicateurs et la
partie s'effondre sans que le joueur puisse rien y faire.

**Projection.** Perspective maison, mélangée à 60 % avec une interpolation
linéaire : la perspective pure tasse trop le fond de piste. L'échelle reste
perspective (les objets lointains restent petits) mais les hauteurs et les textes
sont réhaussés avec la distance pour rester lisibles.

## Structure

```
index.html   structure et surcouches d'interface
style.css    thème néon, HUD, menus, boutique
game.js      projection, génération de niveaux, simulation, rendu, interface
```
