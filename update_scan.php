<?php
// update_scan.php

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['orderId'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$orderId = intval($data['orderId']);

// Connexion à ta base de données (adapter selon ta config)
$mysqli = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Erreur connexion BDD']);
    exit;
}

// Préparation de la requête
$stmt = $mysqli->prepare("UPDATE orders SET scan = 1 WHERE id = ?");
$stmt->bind_param("i", $orderId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur mise à jour base']);
}

$stmt->close();
$mysqli->close();

