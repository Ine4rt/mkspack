<?php
$servername = "marksports.eu.mysql";
$username = "marksports_eu";
$password = "Marksports12";
$dbname = "marksports_eu";

// Créer une connexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connexion échouée.']));
}

// Récupérer les données JSON envoyées via AJAX
$data = json_decode(file_get_contents('php://input'), true);

// Récupérer les valeurs de la requête
$order_id = $data['orderId'] ?? null;
$column = $data['column'] ?? null;
$missing = isset($data['missing']) ? ($data['missing'] ? 1 : 0) : null;

if ($order_id === null || $column === null || $missing === null) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes (orderId, column ou missing).']);
    exit;
}

// Vérifier si la colonne existe dans la table
$valid_columns = ['jacket_missing', 'pants_missing', 'kit_missing'];
if (!in_array($column, $valid_columns)) {
    echo json_encode(['success' => false, 'error' => 'Colonne invalide.']);
    exit;
}

// Mettre à jour la base de données
$sql = "UPDATE orders SET $column = ? WHERE id = ?";

// Préparer la requête
$stmt = $conn->prepare($sql);
if ($stmt) {
    // Lier les paramètres
    $stmt->bind_param('ii', $missing, $order_id);

    // Exécuter la requête
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la préparation de la requête.']);
}

$conn->close();
?>
