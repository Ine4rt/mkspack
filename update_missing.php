<?php
// Connexion à la base de données
$conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');

// Vérifier la connexion
if ($conn->connect_error) {
    die(json_encode([
        "success" => false, 
        "message" => "Erreur de connexion : " . $conn->connect_error
    ]));
}

// Récupérer les données envoyées par la requête
$data = json_decode(file_get_contents('php://input'), true);
$orderId = $data['orderId'] ?? null;
$field = $data['field'] ?? null;
$isMissing = $data['isMissing'] ?? null;

// Vérifier que les données requises sont présentes
if ($orderId === null || $field === null || $isMissing === null) {
    echo json_encode([
        "success" => false, 
        "message" => "Données manquantes : orderId, field ou isMissing absent"
    ]);
    exit;
}

// Assurer que le champ est valide
$allowedFields = ['jacket_missing', 'pants_missing', 'kit_missing'];
if (!in_array($field, $allowedFields)) {
    echo json_encode([
        "success" => false, 
        "message" => "Champ invalide"
    ]);
    exit;
}

// Échapper dynamiquement le champ pour éviter toute injection SQL
if (!preg_match('/^[a-z_]+$/i', $field)) {
    echo json_encode([
        "success" => false, 
        "message" => "Champ contient des caractères non autorisés"
    ]);
    exit;
}

// Préparer la mise à jour
$isMissingValue = $isMissing ? 1 : 0;
$query = "UPDATE orders SET $field = ? WHERE id = ?";

// Préparer et exécuter la requête
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('ii', $isMissingValue, $orderId);
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Mise à jour réussie"
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Erreur lors de l'exécution de la requête : " . $stmt->error
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Erreur lors de la préparation de la requête : " . $conn->error
    ]);
}

$conn->close();
?>
