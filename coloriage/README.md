# 🎨 Mon Coloriage Magique

Application web de coloriage pour enfants. On importe une photo, l'application la
transforme en dessin noir et blanc, puis l'enfant colorie au doigt — la couleur ne
sort jamais des contours.

**Tout est calculé dans le navigateur.** Aucune image n'est envoyée sur un serveur.

---

## 1. Installation sur votre FTP

Envoyez le dossier `coloriage/` complet à la racine de votre site :

```
/coloriage/
├── index.html                    ← l'application entière (HTML + CSS + JS)
├── manifest.webmanifest          ← PWA : nom, icônes, mode standalone
├── sw.js                         ← Service Worker : fonctionnement hors ligne
└── icons/
    ├── icon-192.png
    ├── icon-512.png
    └── icon-maskable-512.png
```

Puis ouvrez `https://votre-site.be/coloriage/`.

### Le fichier unique

`index.html` **est autonome** : CSS, JavaScript, traitement d'image et Web Worker
sont tous à l'intérieur, sans aucune dépendance externe ni CDN. Si vous ne
téléversez que ce fichier, l'application fonctionne intégralement — elle fabrique
alors son manifeste PWA elle-même. Les trois autres fichiers n'ajoutent que le
confort PWA complet : icônes de qualité sur l'écran d'accueil et **fonctionnement
hors ligne** (le Service Worker doit obligatoirement être un fichier séparé, c'est
une règle des navigateurs).

> ⚠️ Le mode PWA (installation, hors ligne) exige **HTTPS**. En `http://` simple,
> l'application fonctionne mais ne s'installe pas.

---

## 2. Utilisation

1. **📷 Importer une image** — appareil photo, galerie, fichier, ou glisser-déposer
   (JPG, PNG, WEBP, GIF, BMP). On peut aussi coller une image (Ctrl+V).
2. **✨ Transformer en dessin** — l'analyse tourne dans un Web Worker, l'interface
   reste fluide.
3. **Réglages** — épaisseur des traits (Fin / Normal / Épais) et niveau de détail
   (Simple / Normal / Détaillé), avec aperçu original ↔ résultat côte à côte.
4. **✨ Utiliser ce dessin** — on passe au coloriage.

### Les outils

| Outil | Rôle |
|---|---|
| 🪄 **Magique** | Colorie **uniquement la zone touchée**. La couleur apparaît progressivement sous le doigt et ne franchit jamais un contour, même si l'enfant déborde largement. |
| 🪣 **Remplir** | Remplit toute une zone d'un coup, avec une petite animation. |
| 🖌️ **Pinceau** | Dessin libre (mais jamais par-dessus les traits noirs). |
| 🧽 **Gomme** | Efface la couleur. |
| ↩️ ↪️ | Annuler / Rétablir (aussi Ctrl+Z / Ctrl+Maj+Z). |
| 🔄 | Repartir d'une page blanche. |
| 🌈 | **Couleurs originales** : chaque zone révèle la couleur de la photo importée. |

### Gestes

- **1 doigt** = colorier. La page ne bouge pas, ne défile pas, ne zoome pas.
- **2 doigts** = zoom et déplacement de l'image.
- Souris et stylet fonctionnent de la même façon (Pointer Events).

### Enregistrer

- **💾** sauvegarde le coloriage dans la galerie locale (IndexedDB).
- **📥** télécharge un PNG complet. Sur mobile, le partage système est proposé
  s'il est disponible (enregistrement dans la pellicule).
- Le retour à l'accueil depuis l'éditeur sauvegarde automatiquement.

---

## 3. Comment ça marche

### Photo couleur → dessin

1. Réduction intelligente de la photo (max 1280 px sur le grand côté, par
   divisions successives pour éviter l'aliasing). Une photo 6000×4000 est traitée
   sans ralentir l'appareil.
2. Flou séparable (3 passes de flou boîte ≈ gaussien, coût constant par pixel).
3. Détection de contours **couleur** : gradient de Sobel calculé sur R, G et B
   séparément, puis maximum. Un contour rouge sur vert est détecté même quand les
   deux ont la même luminosité — ce qu'une conversion en niveaux de gris rate.
4. Différence de gaussiennes (DoG) pour rattraper les détails fins.
5. **Seuillage par hystérésis** (principe de Canny) : les pixels très marqués
   amorcent les traits, puis on prolonge le long des pixels moyennement marqués.
   Sans cela, une image riche en petits détails consomme tout le budget de traits
   et les grands contours disparaissent.
6. Nettoyage morphologique : suppression du bruit, fermeture des trous du contour
   (dilatation + érosion), puis épaississement selon le réglage choisi.

### Dessin → zones coloriables

7. Étiquetage en composantes connexes : chaque surface blanche fermée par des
   traits devient une **zone** avec son identifiant.
8. Fusion des zones minuscules avec leur plus gros voisin (union-find, 3 passes) :
   c'est le réglage « niveau de détail ».
9. Couleur moyenne de chaque zone calculée sur la photo d'origine, légèrement
   avivée → c'est ce qui alimente le mode 🌈.

### Coloriage

La carte des zones est un `Int32Array` de la taille de l'image. À chaque
déplacement du doigt, seuls les pixels du disque du pinceau **dont l'identifiant
de zone correspond à la zone verrouillée** sont composés en alpha. Un pixel de
trait noir n'est jamais peint. Seul le rectangle modifié est réinjecté dans le
canvas (`putImageData` avec rectangle sale), et le rendu est calé sur
`requestAnimationFrame` : **60 FPS mesurés** sur une image de 1,4 Mpx.

Le changement de zone en cours de tracé n'a lieu que si le doigt est franchement à
l'intérieur d'une autre zone (8 sondes sur un cercle, 7 sur 8 doivent concorder)
pendant plusieurs mesures. Un débordement passager ne peut donc jamais salir la
zone voisine.

L'historique ne stocke que le rectangle modifié par chaque geste (avant/après),
plafonné à ~56 Mo, ce qui permet 40 annulations sans saturer la mémoire.

---

## 4. Vie privée

- Aucune requête réseau vers un serveur tiers, aucune analytique, aucun CDN.
- Les photos ne quittent jamais l'appareil : elles sont lues en local et stockées
  dans IndexedDB (dans le navigateur).
- **⚙️ Paramètres → 🗑️ Supprimer toutes mes données** efface la galerie, les
  réglages et le cache hors ligne.

---

## 5. Limites connues

- **HEIC/HEIF (iPhone)** : si le navigateur ne sait pas décoder le format, l'image
  est refusée. Les iPhones convertissent en JPEG lors du partage habituel ; sinon,
  réglez *Réglages → Appareil photo → Formats → Le plus compatible*.
- **Photos très floues ou très sombres** : peu de contours détectables. Le réglage
  « Détaillé » aide, mais un sujet net et contrasté donne toujours un meilleur
  dessin.
- **Portraits et scènes très texturées** (herbe, feuillage, foule) : beaucoup de
  petites zones. Utilisez « Simple » pour de grandes surfaces faciles à colorier.
- La segmentation est géométrique, pas sémantique : elle ne « comprend » pas qu'un
  chien est un chien. Si un contour est ouvert dans la photo, deux zones voisines
  peuvent fusionner. Augmenter le niveau de détail referme généralement le contour.
- L'export est en PNG (fond blanc, coloriage + traits). Le JPG n'est pas proposé :
  il dégrade les aplats de couleur pour un gain de taille négligeable ici.
- Le traitement est plafonné à 1280 px pour rester fluide sur téléphone. Le dessin
  exporté a donc cette résolution, pas celle de la photo d'origine.
