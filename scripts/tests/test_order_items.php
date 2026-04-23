<?php
require_once __DIR__ . '/../../db_config.php';

$result = $conn->query("SELECT id, item_code, item_name, quantity FROM delivery_records WHERE company_name = 'Orders' ORDER BY id DESC LIMIT 10");

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Qty</th></tr>";

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['item_code'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['quantity'] ?? '') . "</td>";
        echo "</tr>";
    }
}

echo "</table>";
?>
