<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

// Connexion à la base de données
$conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    die("Aucun ID fourni.");
}

// Récupérer la commande
$sql = "SELECT * FROM orders WHERE id = $id LIMIT 1";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Commande introuvable.");
}

$order = $result->fetch_assoc();


// Construction dynamique du récapitulatif
function addRow($label, $value) {
    return (!empty($value)) ? "<tr><td><strong>$label</strong></td><td>$value</td></tr>" : "";
}

$recap = '<h3 style="margin-top:30px;">Récapitulatif de votre pack :</h3>';
$recap .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-family: Arial; margin: auto;">';
$recap .= addRow("Nom", $order['name']);
$recap .= addRow("Prénom", $order['firstname']);
$recap .= addRow("Email", $order['email']);
$recap .= addRow("Téléphone", $order['phone']);
$recap .= addRow("Catégorie", $order['category']);
$recap .= addRow("Club", $order['club']);
$recap .= addRow("Taille veste", $order['jacket_size']);
$recap .= addRow("Taille pantalon", $order['pants_size']);
$recap .= addRow("Taille maillot", $order['jersey_size']);
$recap .= addRow("Taille short", $order['short_size']);
$recap .= addRow("Taille kit", $order['kit_size']);
$recap .= addRow("Taille polo", $order['polo_size']);
$recap .= addRow("Taille sous-maillot", $order['under_shirt_size']);
$recap .= addRow("Option K-way", $order['option_kway']);
$recap .= addRow("Option bas", $order['option_bas']);
$recap .= addRow("Initiales demandées", $order['initials_requested'] ? "Oui - " . $order['initials'] : "Non");
$recap .= '</table>';

// Message HTML complet
$body = '
<div style="text-align: center; font-family: Arial, sans-serif; margin-top: 20px;">
    <h2 style="color: red; font-size: 24px; margin-bottom: 10px;">
        Votre pack a bien été enregistré.
    </h2>
    <h3 style="font-weight: bold; font-size: 18px; margin-top: 20px;">Et ensuite ?</h3>
    <ul style="list-style: none; padding: 0; font-size: 16px; color: #333; text-align: left; max-width: 600px; margin: 0 auto;">
        <li>1. Vous payez la cotisation au club.</li>
        <li>2. Le club nous confirme que la cotisation est payée.</li>
        <li>3. Nous préparons le pack (comptez un délai de 15 jours).</li>
        <li>4. Vous recevez un e-mail lorsque le pack est prêt.</li>
        <li>5. Il sera à retirer chez Marksports – <strong>Adresse :</strong> Rue Chaussée 92, 4342 Awans.</li>
        <li>6. Si vous avez sélectionné les initiales, le supplément sera à payer lors de l’enlèvement du pack.</li>
    </ul>
    <div style="margin-top: 20px; background-color: #ffe6e6; padding: 10px; border: 1px solid red; border-radius: 5px; color: #900;">
        <strong>Attention :</strong> En cas de double encodage, <strong>le premier encodage sera pris en compte</strong> !
    </div>
</div>' . $recap;

if ($order['initials_requested']) {
    $body .= "<p><strong>Un supplément sera à payer pour l'impression des initiales lors de l’enlèvement du pack.</strong></p>";
}

// Envoi de l’e-mail
try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'mailout.one.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'pack@marksports.eu';
    $mail->Password = 'Marksports12';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('pack@marksports.eu', 'Marksports Arena');
    $mail->addAddress($order['email'], $order['firstname'] . ' ' . $order['name']);
    $mail->addReplyTo('pack@marksports.eu', 'Marksports Arena');

    $mail->isHTML(true);
    $mail->Subject = "Récapitulatif de votre pack Marksports";
    $mail->Body = $body;

    $mail->send();
    echo "E-mail envoyé avec succès à " . $order['email'];
} catch (Exception $e) {
    echo "Erreur lors de l'envoi de l'e-mail : " . $mail->ErrorInfo;
}
?>