<?php
header('Content-Type: application/json');

// Activer l'affichage des erreurs pour faciliter le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Paramètres de connexion à la base de données
$servername = "marksports.eu.mysql";
$username = "marksports_eu";
$password = "Marksports12";
$dbname = "marksports_eu";

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Lecture des données envoyées en JSON
$data = json_decode(file_get_contents('php://input'), true);

// Vérification de la présence des données
if (!isset($data['orderId']) || !isset($data['field']) || !isset($data['value'])) {
    echo json_encode(['success' => false, 'message' => 'Données JSON non valides ou non reçues']);
    exit;
}

$orderId = $data['orderId'];
$field = $data['field'];
$value = $data['value'];

// Logs pour suivre les valeurs reçues
error_log("Order ID: " . $orderId);
error_log("Field: " . $field);
error_log("Value: " . $value);

// Préparer la requête SQL en fonction du champ
if ($field === 'completed') {
    $stmt = $conn->prepare("UPDATE orders SET completed = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $orderId);
} elseif ($field === 'notes') {
    $stmt = $conn->prepare("UPDATE orders SET notes = ? WHERE id = ?");
    $stmt->bind_param("si", $value, $orderId);
} elseif ($field === 'facture_status') {  
    // Préparer la requête pour obtenir l'état actuel de la facture
    $stmt = $conn->prepare("SELECT facture FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Si une date existe déjà dans le champ facture, on la met à NULL
    if ($row['facture'] !== null) {
        $newFactureDate = NULL;  // Réinitialiser la facture à NULL
    } else {
        $newFactureDate = date('Y-m-d');  // Sinon, on définit la date actuelle
    }

    // Mettre à jour facture_status et facture
    $stmt->close();
    $stmt = $conn->prepare("UPDATE orders SET facture_status = ?, facture = ? WHERE id = ?");
    $stmt->bind_param("isi", $value, $newFactureDate, $orderId);

    // Exécution de la requête de mise à jour
    if ($stmt->execute()) {
        // Assurez-vous que seule la réponse JSON est envoyée
        echo json_encode(['success' => true, 'factureDate' => $newFactureDate]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur de mise à jour de la facture']);
    }

    // Fermeture des connexions
    $stmt->close();
    $conn->close();
    exit;  // Ne rien envoyer après cela pour éviter des erreurs JSON
}

elseif ($field === 'reprise') {  
    // Préparer la requête pour obtenir l'état actuel de la reprise
    $stmt = $conn->prepare("SELECT reprise FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Si une date existe déjà dans le champ reprise, on la met à NULL
    if ($row['reprise'] !== null) {
        $newRepriseDate = NULL;  // Réinitialiser la reprise à NULL
    } else {
        $newRepriseDate = date('Y-m-d');  // Sinon, on définit la date actuelle
    }

    // Mettre à jour reprise et reprise_status
    $stmt->close();
    $stmt = $conn->prepare("UPDATE orders SET reprise = ?, reprise_status = ? WHERE id = ?");
    $stmt->bind_param("sii", $newRepriseDate, $value, $orderId);

    // Exécution de la requête de mise à jour
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'repriseDate' => $newRepriseDate]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur de mise à jour de la reprise']);
    }

    // Fermeture des connexions
    $stmt->close();
    $conn->close();
    exit;  // Ne rien envoyer après cela pour éviter des erreurs JSON
}

elseif ($field === 'coti_status') {  // Mise à jour uniquement de coti_status
    $stmt = $conn->prepare("UPDATE orders SET coti_status = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $orderId);
} elseif ($field === 'cotisation_payee') {  
    // Si la valeur de cotisation_payee est vide, on peut la remplacer par NULL ou une chaîne vide
    $cotiDate = isset($data['value']) && $data['value'] !== '' ? $data['value'] : NULL;  // Si la date est vide, on définit NULL
    error_log("cotisation_payee: " . $cotiDate); // Log de la valeur de cotisation_payee

    $stmt = $conn->prepare("UPDATE orders SET cotisation_payee = ? WHERE id = ?");
    $stmt->bind_param("si", $cotiDate, $orderId);  // Liaison avec la base de données
    // Log avant d'exécuter la requête
    error_log("Exécution de la requête SQL : UPDATE orders SET cotisation_payee = '$cotiDate' WHERE id = $orderId");

    if ($stmt->execute()) {
        error_log("Cotisation payée mise à jour avec succès pour la commande ID: $orderId");
    } else {
        error_log("Erreur lors de la mise à jour de cotisation_payee: " . $stmt->error);
    }
} 



elseif ($field === 'jacket_missing' || $field === 'pants_missing' || $field === 'kit_missing' || $field === 'under_shirt_missing' || $field === 'bas_missing' || $field === 'option_kway_missing') {  
    // Gestion des champs jacket_missing, pants_missing, kit_missing
    $value = ($value == 1) ? 1 : 0;  // Convertir en valeur 1 ou 0
    $stmt = $conn->prepare("UPDATE orders SET $field = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $orderId);
} else {
    echo json_encode(['success' => false, 'message' => 'Champ non reconnu']);
    exit;
}

// Logs avant d'exécuter la requête
error_log("SQL Query: " . $stmt->error);

// Exécution de la requête et gestion des erreurs
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => "Commande mise à jour"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $stmt->error]);
}

// Fermer la connexion
$stmt->close();
$conn->close();
?>
