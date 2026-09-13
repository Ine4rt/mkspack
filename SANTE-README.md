# Suivi santé

Petit carnet pour noter, jour après jour, les « ça ne va pas ».

## Fichiers

| Fichier | Rôle |
|---|---|
| `sante.php` | La page de saisie : une barre de texte + un bouton **Envoyer**. |
| `sante_admin.php` | La page admin : calendrier du mois (jours en rouge), détail heure + maladie, statistiques. |
| `sante_db.php` | Connexion à la base et création automatique de la table. |

## Mise en place

1. Envoyer les trois fichiers sur le serveur, à côté de `db.php` (ils réutilisent la même base MySQL).
2. Ouvrir **une fois** `https://…/sante.php` : la table `sante_entries` est créée toute seule.
3. Changer le mot de passe admin en haut de `sante_db.php` :

   ```php
   define('SANTE_ADMIN_PASSWORD', 'change-moi');
   ```

## Utilisation

- `sante.php` → à mettre en raccourci sur l'écran d'accueil du téléphone. On tape « mal de tête », on envoie, c'est daté automatiquement (heure de Belgique).
- `sante_admin.php` → mot de passe, puis le calendrier du mois. Les jours en rouge sont les jours avec au moins un message, avec le nombre de fois. Les flèches changent de mois.
- En bas : le nombre de jours et de messages du mois, le classement des plaintes les plus fréquentes, et le total depuis le début.
- La petite croix à côté d'une ligne la supprime (faute de frappe, doublon…).
