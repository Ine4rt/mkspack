<?php
// update_facture.php

// On récupère les données envoyées via la requête AJAX
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['orderId']) && isset($data['field']) && isset($data['value'])) {
    $orderId = $data['orderId'];
    $field = $data['field'];
    $value = $data['value'];

    // Connexion à la base de données (ajuster selon ta configuration)
    include 'db.php';

    // On met à jour l'état de la facture dans la base de données
    $stmt = $db->prepare("UPDATE facture SET $field = ? WHERE order_id = ?");
    $stmt->execute([$value, $orderId]);

    // On renvoie une réponse JSON
    echo json_encode(['success' => true]);
} else {
    // Si les données ne sont pas complètes
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
}
?>
