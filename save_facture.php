<?php
// Connexion à la base de données
$conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérification des données envoyées par AJAX
if (isset($_POST['order_id']) && isset($_POST['facture'])) {
    $orderId = $_POST['order_id'];
    $facture = $_POST['facture'];

    // Mise à jour de la facture dans la base de données
    $sql = "UPDATE orders SET facture = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $facture, $orderId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de la facture.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
}

$conn->close();
?>
