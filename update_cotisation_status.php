<?php
// Connexion à la base de données
include 'db.php'; // Inclure la connexion à la base de données

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $orderId = $_POST['order_id'];
    $cotisationDate = $_POST['cotisation_date']; // La date de la cotisation

    // Préparer la requête SQL pour mettre à jour la cotisation payée
    $query = "UPDATE orders SET cotisation_payee = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$cotisationDate, $orderId]);

    if ($stmt->rowCount() > 0) {
        echo "Cotisation mise à jour avec succès.";
    } else {
        echo "Aucune modification apportée.";
    }
}
?>
