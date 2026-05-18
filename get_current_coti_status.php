<?php
header('Content-Type: application/json');

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
if (!isset($data['orderId'])) {
    echo json_encode(['success' => false, 'message' => 'Données non valides ou non reçues']);
    exit;
}

$orderId = $data['orderId'];

// Récupérer la valeur actuelle de coti_status
$stmt = $conn->prepare("SELECT coti_status FROM orders WHERE id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$stmt->bind_result($coti_status);
$stmt->fetch();
$stmt->close();

// Vérifier si coti_status a été trouvé
if ($coti_status !== null) {
    echo json_encode(['success' => true, 'coti_status' => $coti_status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
}

// Fermer la connexion
$conn->close();
?>
