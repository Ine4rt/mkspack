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

// Préparer la requête SQL en fonction du champ
if ($field === 'completed') {
    $stmt = $conn->prepare("UPDATE orders SET completed = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $orderId);
} elseif ($field === 'notes') {
    $stmt = $conn->prepare("UPDATE orders SET notes = ? WHERE id = ?");
    $stmt->bind_param("si", $value, $orderId);
} elseif ($field === 'facture_status') {  // Mise à jour uniquement de facture_status
    $stmt = $conn->prepare("UPDATE orders SET facture_status = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $orderId);
} elseif ($field === 'coti_status') {  // Mise à jour uniquement de coti_status
    $stmt = $conn->prepare("UPDATE orders SET coti_status = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $orderId);
} elseif ($field === 'cotisation_payee') {  // Mise à jour de cotisation_payee et coti_status
    $date = $value; // La date envoyée par l'utilisateur
    $cotiStatus = ($value) ? 1 : 0;  // Si cotisation payée, on passe coti_status à 1
    $stmt = $conn->prepare("UPDATE orders SET cotisation_payee = ?, coti_status = ? WHERE id = ?");
    $stmt->bind_param("sii", $date, $cotiStatus, $orderId);
} else {
    echo json_encode(['success' => false, 'message' => 'Champ non reconnu']);
    exit;
}

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
