<?php
require 'db.php'; // Assure-toi que $pdo est bien connecté ici

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['orderId'], $data['field'], $data['value'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$orderId = (int)$data['orderId'];
$field = $data['field'];
$value = (int)$data['value'];

// Liste blanche des champs autorisés
$allowedFields = [
  'jacket_missing', 'pants_missing', 'bas_missing',
  'jersey_missing', 'short_missing', 'kit_missing',
  'under_shirt_missing', 'initials_missing', 'kway_missing'
];

if (!in_array($field, $allowedFields)) {
    echo json_encode(['success' => false, 'message' => 'Champ invalide']);
    exit;
}

try {
    $sql = "UPDATE orders SET $field = :value WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['value' => $value, 'id' => $orderId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
