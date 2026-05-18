<?php
// Connexion à la base de données
$servername = 'marksports.eu.mysql';
$username = 'marksports_eu';
$password = 'Marksports12';
$dbname = 'marksports_eu';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']));
}

// Récupérer l'ID de la commande depuis la requête GET
$orderId = isset($_GET['orderId']) ? intval($_GET['orderId']) : 0;

if ($orderId === 0) {
    echo json_encode(['success' => false, 'error' => 'ID de commande invalide']);
    exit();
}

// Requête pour récupérer l'email associé à la commande
$sql = "SELECT email FROM orders WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($email);

if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'email' => $email]);
} else {
    echo json_encode(['success' => false, 'error' => 'Commande non trouvée']);
}

$stmt->close();
$conn->close();
?>
