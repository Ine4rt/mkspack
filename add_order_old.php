<?php
// Connexion à la base de données
$conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');

// Vérifier la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Récupérer le dernier numéro de commande
$result = $conn->query("SELECT MAX(order_number) AS max_order_number FROM orders");
if ($result) {
    $row = $result->fetch_assoc();
    $nextOrderNumber = $row['max_order_number'] ? $row['max_order_number'] + 1 : 1;
} else {
    $nextOrderNumber = 1; // Par défaut, commence à 1 si aucun numéro de commande n'existe
}

// Récupérer les données du formulaire en toute sécurité
$name = isset($_POST['name']) ? $conn->real_escape_string($_POST['name']) : '';
$firstname = isset($_POST['firstname']) ? $conn->real_escape_string($_POST['firstname']) : '';
$category = isset($_POST['category']) ? $conn->real_escape_string($_POST['category']) : '';
$phone = isset($_POST['phone']) ? $conn->real_escape_string($_POST['phone']) : '';
$email = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : '';
$jacket_size = isset($_POST['jacket_size']) ? $conn->real_escape_string($_POST['jacket_size']) : '';
$pants_size = isset($_POST['pants_size']) ? $conn->real_escape_string($_POST['pants_size']) : '';
$kit_size = isset($_POST['kit_size']) ? $conn->real_escape_string($_POST['kit_size']) : '';
$initials_requested = isset($_POST['initials_requested']) ? 1 : 0;
$initials = isset($_POST['initials']) ? $conn->real_escape_string($_POST['initials']) : '';
$club = isset($_POST['club']) ? $conn->real_escape_string($_POST['club']) : ''; // Ajouter cette ligne

// Vérification des champs obligatoires
if (empty($name) || empty($firstname) || empty($category) || empty($phone) || empty($email)) {
    die("Erreur : Tous les champs obligatoires doivent être remplis.");
}

// Insérer une nouvelle commande dans la base de données
$sql = "INSERT INTO orders (order_number, name, firstname, category, phone, email, jacket_size, pants_size, kit_size, initials_requested, initials, club, completed) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"; // Ajouter 'club' ici

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param(
        "issssssssiss",
        $nextOrderNumber,
        $name,
        $firstname,
        $category,
        $phone,
        $email,
        $jacket_size,
        $pants_size,
        $kit_size,
        $initials_requested,
        $initials,
        $club // Lier la valeur du club ici
    );

    if ($stmt->execute()) {
        echo "Nouvelle commande créée avec succès. Numéro de commande : $nextOrderNumber";
    } else {
        echo "Erreur lors de l'ajout de la commande : " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Erreur lors de la préparation de la requête : " . $conn->error;
}

// Fermer la connexion
$conn->close();
?>
