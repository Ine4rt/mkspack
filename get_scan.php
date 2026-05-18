<?php
header('Content-Type: application/json');

// Connexion à la BDD
require_once 'db.php';

// Lecture du corps de la requête JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Vérification de la présence de l'orderId
if (!isset($data['orderId'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètre orderId manquant']);
    exit;
}

$orderId = intval($data['orderId']); // Sécurisation

// Requête SQL
$stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $order = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $order]);
} else {
    echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
}

$stmt->close();
$conn->close();
?>
