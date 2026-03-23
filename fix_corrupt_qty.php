<?php
require 'db_config.php';

// Fix the corrupted quantity
$updateResult = $conn->query("
    UPDATE delivery_records 
    SET quantity = 1 
    WHERE item_code = 'XT-XW00-Y-NA' 
    AND company_name = 'Stock Addition' 
    AND quantity > 9000000000000000
");

echo "Fixed corrupted quantity for XT-XW00-Y-NA\n";

// Verify the fix
$verifyResult = $conn->query("
    SELECT item_code, item_name, quantity 
    FROM delivery_records 
    WHERE item_code = 'XT-XW00-Y-NA' 
    AND company_name = 'Stock Addition' 
    LIMIT 1
");

if ($verifyResult && $verifyResult->num_rows > 0) {
    $row = $verifyResult->fetch_assoc();
    echo "Result: " . $row['item_code'] . " - " . $row['item_name'] . " - Qty: " . $row['quantity'] . "\n";
}
?>
