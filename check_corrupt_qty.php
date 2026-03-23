<?php
require 'db_config.php';

echo "Checking for corrupted quantities (> 1,000,000):\n";
echo str_repeat("=", 80) . "\n";

$result = $conn->query("
    SELECT item_code, item_name, quantity, groupings 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    AND quantity > 1000000 
    ORDER BY quantity DESC 
    LIMIT 20
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo $row['item_code'] . " | " . $row['item_name'] . " | Qty: " . number_format($row['quantity']) . " | Group: " . $row['groupings'] . "\n";
    }
    echo "\n";
    
    echo "Total records with qty > 1,000,000: ";
    $countResult = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition' AND quantity > 1000000");
    $countRow = $countResult->fetch_assoc();
    echo $countRow['cnt'] . "\n";
} else {
    echo "No corrupted quantities found.\n";
}

echo "\nNow checking Group B total:\n";
echo str_repeat("=", 80) . "\n";

$groupBResult = $conn->query("
    SELECT SUM(quantity) as total 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    AND (groupings LIKE '%Multi%' OR groupings LIKE '%Group B%')
");

if ($groupBResult) {
    $row = $groupBResult->fetch_assoc();
    echo "Group B Total Quantity: " . $row['total'] . "\n";
    echo "Group B Total (formatted): " . number_format($row['total']) . "\n";
}
?>
