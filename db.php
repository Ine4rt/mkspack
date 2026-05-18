<?php
$host = 'marksports.eu.mysql'; // Remplacez par votre serveur MySQL
$username = 'marksports_eu'; // Remplacez par votre nom d'utilisateur
$password = 'Marksports12'; // Remplacez par votre mot de passe
$database = 'marksports_eu'; // Remplacez par votre base de données

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}
?>
