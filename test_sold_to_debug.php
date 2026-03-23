<?php
session_start();
require_once 'db_config.php';

echo "<h2>Debug: Checking Andison Manila Sales Data</h2>";

// Get Andison Manila records with sold_to
$result = $conn->query("
    SELECT 
        id,
        invoice_no,
        company_name,
        sold_to,
        item_code,
        quantity
    FROM delivery_records 
    WHERE company_name = 'to Andison Manila'
    ORDER BY id DESC
    LIMIT 20
");

echo "<h3>Total Records for 'to Andison Manila': " . $result->num_rows . "</h3>";
echo "<table border='1' cellpadding='10' cellspacing='0'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>ID</th><th>Invoice</th><th>Company</th><th>Item Code</th><th>Sold To (Value)</th><th>Sold To (Empty?)</th>";
echo "</tr>";

$withSoldTo = 0;
$withoutSoldTo = 0;

while ($row = $result->fetch_assoc()) {
    $hasSoldTo = !empty($row['sold_to']);
    if ($hasSoldTo) $withSoldTo++;
    else $withoutSoldTo++;
    
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['invoice_no'] . "</td>";
    echo "<td>" . $row['company_name'] . "</td>";
    echo "<td>" . $row['item_code'] . "</td>";
    echo "<td><strong>" . ($row['sold_to'] ? htmlspecialchars($row['sold_to']) : "NULL") . "</strong></td>";
    echo "<td>" . ($hasSoldTo ? "NO ✓" : "YES ✗") . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>Summary:</h3>";
echo "Records WITH sold_to filled: <strong>$withSoldTo</strong> ✓<br>";
echo "Records WITHOUT sold_to filled: <strong>$withoutSoldTo</strong> ✗<br>";
echo "<br>";

if ($withSoldTo > 0) {
    echo "<h3>Filter Check - Should appear in 'Andison Manila Sales':</h3>";
    echo "<p>If the above shows records with sold_to values, they SHOULD appear in the 'Andison Manila Sales' filter in Delivery Records.</p>";
    echo "<p>If they don't appear, the issue may be in the JavaScript filter logic.</p>";
}
?>
?>
