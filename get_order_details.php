<?php
// Connexion à la base de données
$servername = "marksports.eu.mysql";
$username = "marksports_eu";
$password = "Marksports12";
$dbname = "marksports_eu";

$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifie la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Récupère l'ID de la commande depuis l'URL
$orderId = isset($_GET['orderId']) ? (int)$_GET['orderId'] : 0;

if ($orderId > 0) {
    // Prépare la requête pour récupérer tous les détails de la commande
    $sql = "SELECT * FROM orders WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $orderId);  // Lier l'ID de la commande à la requête
    $stmt->execute();
    $result = $stmt->get_result();

    // Vérifie si la commande existe
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        // Retourne toutes les informations sous forme de JSON
        echo json_encode([
            'success' => true,
            'order' => $order  // Retourne directement toutes les informations de la commande
        ]);
    } else {
        // Si la commande n'existe pas
        echo json_encode([
            'success' => false,
            'message' => 'Commande introuvable'
        ]);
    }

    $stmt->close();
} else {
    // Si l'ID de la commande est invalide
    echo json_encode([
        'success' => false,
        'message' => 'ID de commande invalide'
    ]);
}

$conn->close();
?>
