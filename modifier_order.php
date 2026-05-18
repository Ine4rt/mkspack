<?php
header('Content-Type: application/json');

// Connexion à la base de données
$conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');

if ($conn->connect_error) {
    die(json_encode(["success" => false, "error" => "Connexion échouée : " . $conn->connect_error]));
}

// Lire les données JSON envoyées
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "error" => "Aucune donnée reçue"]);
    exit;
}

// Debugging : enregistrement des données reçues
file_put_contents('debug_log.txt', print_r($data, true), FILE_APPEND);

// Vérifier si l'ID est fourni
if (!isset($data['id'])) {
    echo json_encode(["success" => false, "error" => "ID manquant"]);
    exit;
}

$id = $conn->real_escape_string($data['id']);
$updates = [];
$columns = []; // Pour garder une trace des colonnes qui doivent être mises à jour

// Préparer les données à mettre à jour, uniquement si la valeur n'est pas vide
foreach ($data as $column => $value) {
    if ($column !== "id" && !empty($column) && $value !== '') {
        $value = $conn->real_escape_string($value); // Échapper les caractères spéciaux
        $updates[] = "`$column` = ?";
        $columns[] = $value;
    }
}

if (!empty($updates)) {
    // Création de la requête SQL
    $sql = "UPDATE orders SET " . implode(", ", $updates) . " WHERE id = ?";
    
    // Debugging : afficher la requête SQL et les valeurs des colonnes
    file_put_contents('debug_log.txt', "SQL: $sql\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "Columns: " . print_r($columns, true) . "\n", FILE_APPEND);
    
    // Utilisation de requêtes préparées pour éviter les injections SQL
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Ajout de l'ID à la fin de la liste des paramètres
        $columns[] = $id;
        
        // Dynamique de liaison des paramètres
        $types = str_repeat('s', count($columns) - 1) . 'i'; // 's' pour string, 'i' pour integer (id)
        $stmt->bind_param($types, ...$columns);

        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Erreur SQL : " . $stmt->error, "query" => $sql]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "error" => "Erreur de préparation de la requête SQL", "query" => $sql]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Aucune donnée à mettre à jour"]);
}

$conn->close();
?>
