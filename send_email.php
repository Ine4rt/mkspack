<?php
// Activer le rapport d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // Forcer la réponse JSON

// Inclure PHPMailer
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Connexion à la base de données
$servername = 'marksports.eu.mysql';
$username = 'marksports_eu';
$password = 'Marksports12';
$dbname = 'marksports_eu';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']));
}

// Vérifier et récupérer les valeurs POST
$orderId = isset($_POST['orderId']) ? $_POST['orderId'] : null;
$email = isset($_POST['email']) ? $_POST['email'] : null;

// Vérifier si les valeurs sont présentes
if (empty($orderId) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'L\'ID de la commande ou l\'email est manquant']);
    exit();
}

// Récupérer les informations du client
$sql = "SELECT name, firstname, club, category, order_number, phone, initials_requested FROM orders WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
    exit();
}

// Récupération des données
$row = $result->fetch_assoc();
$clientName = $row['name'];
$clientFirstname = $row['firstname'];
$club = $row['club'];
$category = $row['category'];
$orderNumber = $row['order_number']; // On récupère maintenant le numéro de commande
$phone = $row['phone']; // On récupère le numéro de téléphone
$initialsRequested = $row['initials_requested']; // On récupère la valeur pour initials_requested

$stmt->close();
$conn->close();

try {
    // Création de l'instance PHPMailer
    $mail = new PHPMailer(true);

    // Configuration SMTP
    $mail->isSMTP();
    $mail->Host = 'mailout.one.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'pack@marksports.eu';
    $mail->Password = 'Marksports12';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Expéditeur et destinataire
    $mail->setFrom('pack@marksports.eu', 'Marksports Arena');
    $mail->addAddress($email, "$clientFirstname $clientName");
    $mail->addReplyTo('pack@marksports.eu', 'Marksports Arena');

    // Contenu de l'email
    $mail->isHTML(true);
    $mail->Subject = "[ $orderNumber ] - $clientName - Votre pack est disponible";

    // Construire le corps de l'email

        $body = "
        <p>Cher client,</p>
        <p>Votre pack <strong>" . (!empty($club) ? "du club $club" : "") . "</strong> est disponible au magasin.</p>
        <p><strong>Numéro du pack :</strong> $orderNumber</p>
        <p><strong>Nom client :</strong> $clientName</p>
        <p><strong>Prénom client :</strong> $clientFirstname</p>
        <p><strong>Catégorie :</strong> $category</p>
        <p><strong>Téléphone :</strong> $phone</p>
        <p>Votre pack est disponible en magasin du lundi au vendredi de 10h00 à 12h30 et de 13h00 à 18h00. - Rue Chaussée 92, 4342 Awans</p>
        <p>Les impressions et les articles demandés en supplément seront à régler lors du retrait du pack en magasin.</p>
    ";

    // Ajouter le message pour les initiales si `initials_requested` est 1
    if ($initialsRequested == 1) {
        $body .= "<p><strong>Le montant pour l'impression des initiales est de :</strong> [Montant]</p>";
    }

    // Ajouter la fin du message
    $body .= "<p>Bien à vous,<br>L'équipe Marksports Arena</p>";

    $mail->Body = $body;

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
