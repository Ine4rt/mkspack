<?php
include 'db.php';

$sql = "SELECT * FROM orders ORDER BY created_at DESC";
$result = $conn->query($sql);

$orders = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
<td class="facture-cell <?php echo ($row['facture_status'] == 1) ? 'selected' : ''; ?>">
    <input type="date" value="<?php echo htmlspecialchars($row['facture']); ?>" onchange="saveFacture(this)">
</td>

header('Content-Type: application/json');
echo json_encode($orders);

$conn->close();
?>
