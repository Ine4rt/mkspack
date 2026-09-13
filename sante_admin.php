<?php
// ---------------------------------------------------------------------------
// Page admin : calendrier du mois (jours en rouge = "ça ne va pas"),
// détail heure + maladie, et statistiques du mois.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/sante_db.php';

session_start();

// --- Connexion -------------------------------------------------------------
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: sante_admin.php');
    exit;
}

if (empty($_SESSION['sante_admin'])) {
    $mauvais = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['motdepasse'])) {
        $saisi = is_string($_POST['motdepasse']) ? $_POST['motdepasse'] : '';
        if (hash_equals(SANTE_ADMIN_PASSWORD, $saisi)) {
            $_SESSION['sante_admin'] = true;
            header('Location: sante_admin.php');
            exit;
        }
        $mauvais = true;
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin</title>
    <style>
        body { font-family: system-ui, -apple-system, Arial, sans-serif; padding: 60px 20px; }
        form { max-width: 320px; margin: 0 auto; display: flex; gap: 8px; }
        input, button { padding: 10px; font-size: 16px; border: 1px solid #999; border-radius: 4px; }
        input { flex: 1; min-width: 0; }
        p { max-width: 320px; margin: 16px auto 0; color: #c00; font-size: 14px; }
    </style>
    </head>
    <body>
    <form method="post">
        <input type="password" name="motdepasse" autofocus placeholder="Mot de passe">
        <button type="submit">Entrer</button>
    </form>
    <?php if ($mauvais): ?><p>Mot de passe incorrect.</p><?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

// --- Suppression d'une ligne ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer'])) {
    $id = (int) $_POST['supprimer'];
    $stmt = $conn->prepare("DELETE FROM sante_entries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $moisPoste = is_string($_POST['mois'] ?? null) ? $_POST['mois'] : '';
    $moisPoste = preg_replace('/[^0-9\-]/', '', $moisPoste);
    header('Location: sante_admin.php?m=' . urlencode($moisPoste));
    exit;
}

// --- Mois affiché ----------------------------------------------------------
$mois = is_string($_GET['m'] ?? null) ? $_GET['m'] : date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
    $mois = date('Y-m');
}

$premierJour = DateTime::createFromFormat('Y-m-d H:i:s', $mois . '-01 00:00:00');
if (!$premierJour) {
    $mois = date('Y-m');
    $premierJour = DateTime::createFromFormat('Y-m-d H:i:s', $mois . '-01 00:00:00');
}

$moisSuivant = (clone $premierJour)->modify('+1 month');
$moisPrecedent = (clone $premierJour)->modify('-1 month');

$debut = $premierJour->format('Y-m-d H:i:s');
$fin = $moisSuivant->format('Y-m-d H:i:s');

// --- Entrées du mois -------------------------------------------------------
$stmt = $conn->prepare("SELECT id, entry_text, created_at
                        FROM sante_entries
                        WHERE created_at >= ? AND created_at < ?
                        ORDER BY created_at ASC");
$stmt->bind_param("ss", $debut, $fin);
$stmt->execute();
$res = $stmt->get_result();

$parJour = [];   // 'Y-m-d' => [ ['id', 'heure', 'texte'], ... ]
$compteur = [];  // maladie (minuscule) => nombre de fois
$total = 0;

while ($row = $res->fetch_assoc()) {
    $jour = substr($row['created_at'], 0, 10);
    $parJour[$jour][] = [
        'id'    => $row['id'],
        'heure' => substr($row['created_at'], 11, 5),
        'texte' => $row['entry_text'],
    ];

    $cle = function_exists('mb_strtolower')
        ? mb_strtolower($row['entry_text'], 'UTF-8')
        : strtolower($row['entry_text']);
    $compteur[$cle] = ($compteur[$cle] ?? 0) + 1;
    $total++;
}
$stmt->close();

arsort($compteur);

// --- Totaux depuis le début ------------------------------------------------
$depuisToujours = ['messages' => 0, 'jours' => 0];
$res = $conn->query("SELECT COUNT(*) AS messages, COUNT(DISTINCT DATE(created_at)) AS jours
                     FROM sante_entries");
if ($res && ($row = $res->fetch_assoc())) {
    $depuisToujours = $row;
}

// --- Construction du calendrier -------------------------------------------
$nbJours = (int) $premierJour->format('t');
$decalage = (int) $premierJour->format('N') - 1; // 0 = lundi
$aujourdhui = date('Y-m-d');

$nomsMois = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$titreMois = $nomsMois[(int) $premierJour->format('n')] . ' ' . $premierJour->format('Y');

function e($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin – suivi santé</title>
<style>
    body {
        font-family: system-ui, -apple-system, Arial, sans-serif;
        margin: 0 auto;
        padding: 24px 16px 60px;
        max-width: 720px;
        color: #000;
        background: #fff;
    }
    h1 { font-size: 20px; margin: 0 0 4px; }
    h2 { font-size: 16px; margin: 32px 0 8px; }
    .nav { display: flex; align-items: center; justify-content: space-between; margin: 16px 0 8px; }
    .nav a { text-decoration: none; color: #000; border: 1px solid #999; padding: 4px 10px; border-radius: 4px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #ddd; text-align: center; padding: 6px 2px; font-size: 13px; height: 38px; }
    th { background: #f6f6f6; font-weight: normal; color: #666; }
    td.vide { border: none; }
    td.malade { background: #e53935; }
    td.malade a { color: #fff; font-weight: bold; text-decoration: none; display: block; }
    td.aujourdhui { outline: 2px solid #000; outline-offset: -2px; }
    .nb { display: block; font-size: 10px; font-weight: normal; }
    .jour { margin: 14px 0; }
    .jour .date { font-weight: bold; font-size: 14px; }
    .jour ul { margin: 4px 0 0; padding-left: 18px; }
    .jour li { font-size: 14px; margin-bottom: 2px; }
    .jour form { display: inline; }
    .jour button { border: none; background: none; color: #999; cursor: pointer; font-size: 12px; padding: 0 4px; }
    .stats { background: #f6f6f6; padding: 12px 16px; border-radius: 4px; font-size: 14px; }
    .stats ul { margin: 6px 0 0; padding-left: 18px; }
    .rien { color: #666; font-size: 14px; }
    .bas { margin-top: 40px; font-size: 12px; }
    .bas a { color: #666; }
</style>
</head>
<body>

<h1>Suivi santé</h1>

<div class="nav">
    <a href="?m=<?php echo $moisPrecedent->format('Y-m'); ?>">&larr;</a>
    <strong><?php echo e($titreMois); ?></strong>
    <a href="?m=<?php echo $moisSuivant->format('Y-m'); ?>">&rarr;</a>
</div>

<table>
    <tr>
        <th>Lu</th><th>Ma</th><th>Me</th><th>Je</th><th>Ve</th><th>Sa</th><th>Di</th>
    </tr>
    <tr>
    <?php
    for ($i = 0; $i < $decalage; $i++) {
        echo '<td class="vide"></td>';
    }

    $colonne = $decalage;
    for ($jour = 1; $jour <= $nbJours; $jour++) {
        $dateJour = sprintf('%s-%02d', $mois, $jour);
        $classes = [];
        if (isset($parJour[$dateJour])) {
            $classes[] = 'malade';
        }
        if ($dateJour === $aujourdhui) {
            $classes[] = 'aujourdhui';
        }
        echo '<td class="' . implode(' ', $classes) . '">';
        if (isset($parJour[$dateJour])) {
            $nb = count($parJour[$dateJour]);
            echo '<a href="#jour-' . $dateJour . '">' . $jour
               . '<span class="nb">' . $nb . '&times;</span></a>';
        } else {
            echo $jour;
        }
        echo '</td>';

        $colonne++;
        if ($colonne % 7 === 0 && $jour < $nbJours) {
            echo '</tr><tr>';
        }
    }

    while ($colonne % 7 !== 0) {
        echo '<td class="vide"></td>';
        $colonne++;
    }
    ?>
    </tr>
</table>

<h2>Détail du mois</h2>

<?php if (empty($parJour)): ?>
    <p class="rien">Aucun message ce mois-ci.</p>
<?php else: ?>
    <?php foreach ($parJour as $dateJour => $entrees): ?>
        <div class="jour" id="jour-<?php echo e($dateJour); ?>">
            <div class="date"><?php echo e(date('d/m/Y', strtotime($dateJour))); ?></div>
            <ul>
                <?php foreach ($entrees as $entree): ?>
                    <li>
                        <?php echo e($entree['heure']); ?> — <?php echo e($entree['texte']); ?>
                        <form method="post" onsubmit="return confirm('Supprimer cette ligne ?');">
                            <input type="hidden" name="supprimer" value="<?php echo (int) $entree['id']; ?>">
                            <input type="hidden" name="mois" value="<?php echo e($mois); ?>">
                            <button type="submit" title="Supprimer">&times;</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Statistiques</h2>

<div class="stats">
    <?php echo e($titreMois); ?> :
    <strong><?php echo count($parJour); ?></strong> jour(s) sur <?php echo $nbJours; ?>,
    <strong><?php echo $total; ?></strong> message(s).

    <?php if (!empty($compteur)): ?>
        <ul>
            <?php $i = 0; foreach ($compteur as $maladie => $nb): if ($i++ >= 10) break; ?>
                <li><?php echo e($maladie); ?> : <?php echo (int) $nb; ?>&times;</li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p style="margin-bottom:0">
        Depuis le début :
        <strong><?php echo (int) $depuisToujours['jours']; ?></strong> jour(s),
        <strong><?php echo (int) $depuisToujours['messages']; ?></strong> message(s).
    </p>
</div>

<p class="bas">
    <a href="sante.php">Page de saisie</a> &nbsp;·&nbsp;
    <a href="?logout=1">Se déconnecter</a>
</p>

</body>
</html>
<?php $conn->close(); ?>
