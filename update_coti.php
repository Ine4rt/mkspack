<?php
// Inclure la configuration de la base de données
require_once 'db.php';

// Récupérer les données envoyées en POST (en JSON)
$input = json_decode(file_get_contents('php://input'), true);

// Vérifier si les données nécessaires sont présentes
if (isset($input['orderId']) && isset($input['value'])) {
    $orderId = $input['orderId'];
    $value = $input['value'];

    // Vérification que la valeur n'est pas vide
    if (empty($value)) {
        echo json_encode(["status" => "error", "message" => "La valeur de la cotisation payée est vide."]);
        exit();  // Arrêter le script ici si la valeur est vide
    }

    try {
        // Afficher les données reçues pour débogage
        echo json_encode(["status" => "info", "orderId" => $orderId, "value" => $value]);

        // Requête de mise à jour de la cotisation payée dans la base de données
        $stmt = $pdo->prepare("UPDATE orders SET cotisation_payee = :value WHERE id = :orderId");
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':orderId', $orderId);

        // Exécuter la requête
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Cotisation mise à jour avec succès."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Erreur lors de la mise à jour de la cotisation. La requête a échoué."]);
        }
    } catch (Exception $e) {
        // En cas d'exception, afficher le message d'erreur
        echo json_encode(["status" => "error", "message" => "Une erreur est survenue : " . $e->getMessage()]);
    }
} else {
    // Si les paramètres 'orderId' ou 'value' sont manquants
    echo json_encode(["status" => "error", "message" => "Données manquantes. 'orderId' ou 'value' sont absents."]);
}
?>
