<?php
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=commandes_bas_oha.xls");

$conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM orders WHERE club = 'Bas-Oha'";
$result = $conn->query($sql);

echo "Pack\tDate\tNom\tPrénom\tCatégorie\tTéléphone\tEmail\tRôle\tVeste\tPantalon\tKit\tBas\tSous-pull\tKway\tInitiales\tCoti Payée\tFacturé\tTerminé\n";

while ($row = $result->fetch_assoc()) {
    echo htmlspecialchars($row['order_number']) . "\t";
    echo date('d/m/Y', strtotime($row['created_at'])) . "\t";
    echo htmlspecialchars($row['name']) . "\t";
    echo htmlspecialchars($row['firstname']) . "\t";
    echo htmlspecialchars($row['category']) . "\t";
    echo htmlspecialchars($row['phone']) . "\t";
    echo htmlspecialchars($row['email']) . "\t";
    echo htmlspecialchars($row['role']) . "\t";
    echo htmlspecialchars($row['jacket_size']) . "\t";
    echo htmlspecialchars($row['pants_size']) . "\t";
    echo htmlspecialchars($row['kit_size']) . "\t";
    echo htmlspecialchars($row['bas_size']) . "\t";
    echo htmlspecialchars($row['under_shirt_size']) . "\t";
    echo htmlspecialchars($row['option_kway']) . "\t";
    echo htmlspecialchars($row['initials']) . "\t";
    echo htmlspecialchars($row['cotisation_payee']) . "\t";
    echo htmlspecialchars($row['facture']) . "\t";
    echo ($row['completed'] == 1) ? "Oui" : "Non";
    echo "\n";
}

$conn->close();
?>
