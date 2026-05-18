<?php
header('Content-Type: application/json');

// Lecture des données envoyées en JSON
$data = json_decode(file_get_contents('php://input'), true);

// Vérification des données
if (isset($data['orderId']) && isset($data['field']) && isset($data['value'])) {
    // Si les données sont présentes, afficher les valeurs reçues
    echo json_encode(['success' => true, 'message' => 'Données reçues : ' . json_encode($data)]);
} else {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
}
?>
