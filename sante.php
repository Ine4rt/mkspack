<?php
// ---------------------------------------------------------------------------
// Page de saisie : une barre de texte, un bouton. Rien d'autre.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/sante_db.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brut = $_POST['symptome'] ?? '';
    $texte = is_string($brut) ? trim($brut) : '';
    $texte = preg_replace('/\s+/u', ' ', $texte);

    if ($texte === '') {
        $erreur = 'Écris quelque chose.';
    } else {
        if (function_exists('mb_substr')) {
            $texte = mb_substr($texte, 0, 255);
        } else {
            $texte = substr($texte, 0, 255);
        }

        $date = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO sante_entries (entry_text, created_at) VALUES (?, ?)");
        $stmt->bind_param("ss", $texte, $date);

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            // Redirection après POST pour éviter les doublons si on rafraîchit.
            header('Location: sante.php?ok=1');
            exit;
        }

        $erreur = "Erreur lors de l'enregistrement.";
        $stmt->close();
    }
}

$enregistre = isset($_GET['ok']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Comment ça va ?</title>
<style>
    body {
        font-family: system-ui, -apple-system, Arial, sans-serif;
        margin: 0;
        padding: 60px 20px;
        background: #fff;
        color: #000;
    }
    form {
        max-width: 480px;
        margin: 0 auto;
        display: flex;
        gap: 8px;
    }
    input {
        flex: 1;
        min-width: 0;
        padding: 12px;
        font-size: 16px;
        border: 1px solid #999;
        border-radius: 4px;
    }
    button {
        padding: 12px 18px;
        font-size: 16px;
        border: 1px solid #999;
        border-radius: 4px;
        background: #f2f2f2;
        cursor: pointer;
    }
    p {
        max-width: 480px;
        margin: 16px auto 0;
        font-size: 14px;
    }
    .ok { color: #060; }
    .ko { color: #c00; }
</style>
</head>
<body>

<form method="post" action="sante.php">
    <input type="text" name="symptome" maxlength="255" autofocus autocomplete="off"
           placeholder="Ex : mal de tête">
    <button type="submit">Envoyer</button>
</form>

<?php if ($enregistre): ?>
    <p class="ok">Enregistré.</p>
<?php elseif ($erreur !== ''): ?>
    <p class="ko"><?php echo htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

</body>
</html>
