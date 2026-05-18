<?php
header('Content-Type: application/json');
require 'db.php'; // ton fichier de connexion à la base

$club = isset($_GET['club']) ? trim($_GET['club']) : '';
$firstname = isset($_GET['firstname']) ? trim($_GET['firstname']) : '';
$lastname = isset($_GET['lastname']) ? trim($_GET['lastname']) : '';

if (!$club || !$firstname || !$lastname) {
    echo json_encode(['success' => false, 'message' => 'Informations manquantes']);
    exit;
}

// Préparer la requête pour chercher par club + prénom + nom
$sql = "SELECT * FROM orders 
        WHERE club = :club 
        AND firstname LIKE :firstname 
        AND name LIKE :lastname 
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':club' => $club,
    ':firstname' => $firstname,
    ':lastname' => $lastname
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Aucune commande trouvée']);
    exit;
}

// Réponse identique à get_order_details.php
echo json_encode([
    'success' => true,
    'order' => $order
]);
