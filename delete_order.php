<?php
// Suppression d'une commande dans la base de données
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données JSON envoyées
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['orderId'])) {
        $orderId = $data['orderId'];

        // Connexion à la base de données
        $conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');

        // Vérifier la connexion
        if ($conn->connect_error) {
            die(json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']));
        }

        // Préparer la requête de suppression
        $sql = "DELETE FROM orders WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $orderId);

        // Exécuter la requête
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Commande supprimée']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }

        // Fermer la connexion
        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'ID de la commande manquant']);
    }
}
?>
