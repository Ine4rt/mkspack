<?php
// get_facture_date.php
include('db.php');  // Inclure la connexion à la base de données

if (isset($_GET['orderId'])) {
    $orderId = $_GET['orderId'];
    $query = "SELECT facture_date FROM orders WHERE order_id = :orderId";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':orderId', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    $factureDate = $stmt->fetchColumn();

    if ($factureDate) {
        echo json_encode(['success' => true, 'factureDate' => $factureDate]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Date de facture introuvable']);
    }
}
?>
