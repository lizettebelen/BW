<?php
session_start();
require_once 'db_config.php';

// Check records with company_name = 'to Andison Manila'
$result = $conn->query("
    SELECT id, invoice_no, item_code, sold_to, company_name
    FROM delivery_records 
    WHERE company_name = 'to Andison Manila'
    LIMIT 10
");

echo "<h2>Andison Manila Records (to Andison Manila)</h2>";
echo "Total records: " . $result->num_rows . "<br><br>";

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Invoice</th><th>Item Code</th><th>Company</th><th>Sold To</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['invoice_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['company_name']) . "</td>";
    echo "<td>" . ($row['sold_to'] ? htmlspecialchars($row['sold_to']) : "<strong>NULL/EMPTY</strong>") . "</td>";
    echo "</tr>";
}
echo "</table>";

$result->free_result();

// Show how many have sold_to filled vs empty
$withSoldTo = $conn->query("SELECT COUNT(*) as count FROM delivery_records WHERE company_name = 'to Andison Manila' AND sold_to IS NOT NULL AND sold_to != ''");
$withoutSoldTo = $conn->query("SELECT COUNT(*) as count FROM delivery_records WHERE company_name = 'to Andison Manila' AND (sold_to IS NULL OR sold_to = '')");

$withRow = $withSoldTo->fetch_assoc();
$withoutRow = $withoutSoldTo->fetch_assoc();

echo "<br><h3>Summary:</h3>";
echo "Records WITH sold_to: " . $withRow['count'] . "<br>";
echo "Records WITHOUT sold_to: " . $withoutRow['count'] . "<br>";
?>
