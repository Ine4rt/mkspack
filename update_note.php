<?php
$servername = "marksports.eu.mysql";
$username = "marksports_eu";
$password = "Marksports12";
$dbname = "marksports_eu";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = intval($_POST['order_id']);
    $note = $conn->real_escape_string($_POST['note']);

    $sql = "UPDATE orders SET notes='$note' WHERE id=$orderId";
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
}

$conn->close();
?>
