<?php
// Inclure PHPMailer manuellement
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Connexion à la base de données pour récupérer l'email du destinataire
$servername = 'marksports.eu.mysql';
$username = 'marksports_eu';
$password = 'Marksports12';
$dbname = 'marksports_eu';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']));
}

// Récupérer l'ID de la commande et l'email à partir de la requête POST
$orderId = isset($_POST['orderId']) ? $_POST['orderId'] : null;  // Récupérer l'ID de commande
$email = isset($_POST['email']) ? $_POST['email'] : null;  // Récupérer l'email du client

// Vérifier si l'ID de la commande et l'email sont présents
if (empty($orderId) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'L\'ID de la commande ou l\'email est manquant']);
    exit();
}

try {
    // Créer une instance de PHPMailer
    $mail = new PHPMailer(true);

    // Paramètres du serveur SMTP
    $mail->isSMTP();
    $mail->Host = 'send.one.com';  // Votre serveur SMTP
    $mail->SMTPAuth = true;
    $mail->Username = 'info@ineart.be';  // Votre email SMTP
    $mail->Password = 'Choukroutraxou12';  // Votre mot de passe SMTP
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;  // Utilisez 465 pour SSL ou 587 pour TLS

    // Expéditeur et destinataire
    $mail->setFrom('info@ineart.be', 'Nom');
    $mail->addAddress($email, 'Marksports');  // L'email du client depuis la base de données
    $mail->addReplyTo('info@ineart.be', 'Nom');

    // Contenu de l'email
    $mail->isHTML(true);
    $mail->Subject = 'Pack terminé';
    $mail->Body    = 'Bonjour, Votre pack numero ' . $orderId . ' est disponible pour le retrait. Votre pack est disponible en magasin du lundi au samedi de 10h a 18h';

    // Envoyer l'email
    if ($mail->send()) {
        echo json_encode(['success' => true, 'email' => $email]);
    } else {
        echo json_encode(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur PHPMailer : ' . $e->getMessage()]);
}
?>
