<?php
// Connexion à la base de données
$conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');

// Vérifier la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Récupérer le dernier numéro de commande pour le club sélectionné
$club = isset($_POST['club']) ? $conn->real_escape_string($_POST['club']) : ''; // Récupérer le club depuis le formulaire
$result = $conn->query("SELECT MAX(order_number) AS max_order_number FROM orders WHERE club = '$club'");

if ($result) {
    $row = $result->fetch_assoc();
    // Si un numéro de commande existe déjà pour ce club, on l'incrémente, sinon on commence à 1
    $nextOrderNumber = $row['max_order_number'] ? $row['max_order_number'] + 1 : 1;
} else {
    $nextOrderNumber = 1; // Si aucune commande n'est trouvée pour ce club, commencer à 1
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
$under_shirt_size = isset($_POST['under_shirt_size']) ? $conn->real_escape_string($_POST['under_shirt_size']) : '';
$option_kway = isset($_POST['option_kway']) ? $conn->real_escape_string($_POST['option_kway']) : '';
$bas_size = isset($_POST['bas_size']) ? $conn->real_escape_string($_POST['bas_size']) : '';
$initials_requested = isset($_POST['initials_requested']) ? 1 : 0;
$initials = isset($_POST['initials']) ? $conn->real_escape_string($_POST['initials']) : '';
$role = isset($_POST['role']) ? $conn->real_escape_string($_POST['role']) : 'default_role'; // Valeur par défaut si non définie

// Vérification des champs obligatoires
if (empty($name) || empty($firstname) || empty($category) || empty($phone) || empty($email)) {
    die("Erreur : Tous les champs obligatoires doivent être remplis.");
}

// Mise à jour de la requête SQL pour inclure 'role'
$sql = "INSERT INTO orders (order_number, name, firstname, category, phone, email, jacket_size, pants_size, kit_size, bas_size, under_shirt_size, option_kway, initials_requested, initials, club, role) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if ($stmt) {
    // Lier le numéro de commande généré
    $stmt->bind_param(
        "ssssssssssssssss", // 15 paramètres à lier
        $nextOrderNumber,     // order_number
        $name,             // name
        $firstname,        // firstname
        $category,         // category
        $phone,            // phone
        $email,            // email
        $jacket_size,      // jacket_size
        $pants_size,       // pants_size
        $kit_size,   
        $bas_size,		// socks_size
        $under_shirt_size, // under_shirt_size
        $option_kway,      // option_kway
        $initials_requested, // initials_requested
        $initials,         // initials
        $club,             // club
        $role              // role
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
