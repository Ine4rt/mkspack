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

// Vérifier la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Récupérer le club depuis le formulaire
$club = isset($_POST['club']) ? $conn->real_escape_string($_POST['club']) : NULL;
if (empty($club)) {
    die("Erreur : Le club doit être spécifié.");
}

// Démarrer une transaction
$conn->begin_transaction();

try {
    // Récupérer le dernier numéro de commande pour ce club en verrouillant la ligne
    $result = $conn->query("SELECT MAX(order_number) AS max_order_number FROM orders WHERE club = '$club' FOR UPDATE");
    if ($result) {
        $row = $result->fetch_assoc();
        $nextOrderNumber = $row['max_order_number'] ? $row['max_order_number'] + 1 : 1;
    } else {
        $nextOrderNumber = 1; // Par défaut, commence à 1 si aucun numéro de commande n'existe pour ce club
    }

    // Récupérer les données du formulaire en toute sécurité
    $name = isset($_POST['name']) ? $conn->real_escape_string($_POST['name']) : NULL;
    $firstname = isset($_POST['firstname']) ? $conn->real_escape_string($_POST['firstname']) : NULL;
    $category = isset($_POST['category']) ? $conn->real_escape_string($_POST['category']) : NULL;
    $phone = isset($_POST['phone']) ? $conn->real_escape_string($_POST['phone']) : NULL;
    $email = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : NULL;
    $jacket_size = isset($_POST['jacket_size']) ? $conn->real_escape_string($_POST['jacket_size']) : NULL;
    $pants_size = isset($_POST['pants_size']) ? $conn->real_escape_string($_POST['pants_size']) : NULL;
    $kit_size = isset($_POST['kit_size']) ? $conn->real_escape_string($_POST['kit_size']) : NULL; // Valeur par défaut
    $under_shirt_size = isset($_POST['under_shirt_size']) ? $conn->real_escape_string($_POST['under_shirt_size']) : NULL;
    $option_kway = isset($_POST['option_kway']) ? $conn->real_escape_string($_POST['option_kway']) : NULL;
	$option_bas = isset($_POST['option_bas']) ? $conn->real_escape_string($_POST['option_bas']) : NULL;
    $bas_size = isset($_POST['bas_size']) ? $conn->real_escape_string($_POST['bas_size']) : NULL;
    $initials_requested = isset($_POST['initials_requested']) ? (int)$_POST['initials_requested'] : 0;
    $initials = isset($_POST['initials']) ? $conn->real_escape_string($_POST['initials']) : NULL;
    $role = isset($_POST['role']) ? $conn->real_escape_string($_POST['role']) : 'default_role'; // Valeur par défaut si non définie
    $jersey_size = isset($_POST['jersey_size']) ? $conn->real_escape_string($_POST['jersey_size']) : null;
    $short_size = isset($_POST['short_size']) ? $conn->real_escape_string($_POST['short_size']) : null;
    $polo_size = isset($_POST['polo_size']) ? $conn->real_escape_string($_POST['polo_size']) : null;

    // Vérification des champs obligatoires
    if (empty($name) || empty($firstname) || empty($category) || empty($phone) || empty($email)) {
        die("Erreur : Tous les champs obligatoires doivent être remplis.");
    }

    // Mise à jour de la requête SQL pour inclure 'role'
    $sql = "INSERT INTO orders (order_number, name, firstname, category, phone, email, jacket_size, pants_size, kit_size, bas_size, under_shirt_size, option_kway, option_bas, initials_requested, initials, club, role, jersey_size, short_size, polo_size) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Préparer et lier les paramètres
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Lier les paramètres en tenant compte de la possibilité de NULL
        $stmt->bind_param(
        "ssssssssssssssssssss", // 16 paramètres à lier (ajout d'un 's' pour jersey_size)
        $nextOrderNumber,     // order_number
        $name,             // name
        $firstname,        // firstname
        $category,         // category
        $phone,            // phone
        $email,            // email
        $jacket_size,      // jacket_size
        $pants_size,       // pants_size
        $kit_size,   
        $bas_size,         // socks_size
        $under_shirt_size, // under_shirt_size
        $option_kway, 
$option_bas, 		// option_kway
        $initials_requested, // initials_requested
        $initials,         // initials
        $club,             // club
        $role,             // role
        $jersey_size,       // jersey_size
        $short_size,
        $polo_size
        );

        // Exécution de la requête
               if ($stmt->execute()) {
            // Commit de la transaction
            $conn->commit();

            echo '
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
</div>
<script>
    window.parent.postMessage("scrollToTop", "*");
</script>';

            // Envoi de l'e-mail de confirmation
            try {
                $mail = new PHPMailer(true);
                $mail->CharSet = 'UTF-8';

                // Config SMTP
                $mail->isSMTP();
                $mail->Host = 'send.one.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'pack@marksports.eu';
                $mail->Password = 'Marksports12';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Infos expéditeur / destinataire
                $mail->setFrom('pack@marksports.eu', 'Marksports Arena');
                $mail->addAddress($email, "$firstname $name");
                $mail->addReplyTo('pack@marksports.eu', 'Marksports Arena');

                // Sujet et contenu
                $mail->isHTML(true);
                $mail->Subject = "Merci d'avoir encodé votre pack";

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
</div>';
                if ($initials_requested == 1) {
                    $body .= "<p><strong>Un supplément sera à payer pour l'impression des initiales a l'enlevement du pack.</strong></p>";
                }
// Construction dynamique du récapitulatif
$recap = '<h3 style="margin-top:30px;">Récapitulatif de votre pack :</h3>';
$recap .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-family: Arial; margin: auto;">';

function addRow($label, $value) {
    return (!empty($value)) ? "<tr><td><strong>$label</strong></td><td>$value</td></tr>" : '';
}

$recap .= addRow("Nom", $name);
$recap .= addRow("Prénom", $firstname);
$recap .= addRow("Catégorie", $category);
$recap .= addRow("Téléphone", $phone);
$recap .= addRow("Email", $email);
$recap .= addRow("Club", $club);
$recap .= addRow("Veste", $jacket_size);
$recap .= addRow("Pantalon", $pants_size);
$recap .= addRow("Maillot", $jersey_size);
$recap .= addRow("Short", $short_size);
$recap .= addRow("Bas", $bas_size);
$recap .= addRow("Sous-Maillot", $under_shirt_size);
$recap .= addRow("Polo", $polo_size);
$recap .= addRow("Option K-way", $option_kway);
$recap .= addRow("Option Bas", $option_bas);
$recap .= addRow("Kit", $kit_size);
if (strtolower($club) !== 'hannut') {
    $recap .= addRow("Initiales demandées", $initials_requested ? "Oui ($initials)" : "Non");
}


$recap .= '</table>';

// Ajouter au corps du mail
$body .= $recap;


                $body .= "<p>Bien à vous,<br>L'équipe Marksports Arena</p>";

                $mail->Body = $body;
                $mail->send();
            } catch (Exception $e) {
                error_log("Erreur PHPMailer : " . $mail->ErrorInfo);
            }

        } else {
            // En cas d'erreur à l'exécution
            $conn->rollback();
            echo "Erreur lors de l'ajout de la commande : " . $stmt->error;
        }

        // Fermer le statement et la connexion
        $stmt->close();
    } else {
        // Erreur de préparation de la requête
        $conn->rollback();
        echo "Erreur lors de la préparation de la requête : " . $conn->error;
    }

} catch (Exception $e) {
    // Erreur générale pendant la transaction
    $conn->rollback();
    echo "Erreur lors du traitement de la commande : " . $e->getMessage();
}

// Fermer la connexion
$conn->close();
