<?php
require 'db_config.php';

echo "=== CURRENT DATABASE STATUS ===\n\n";

// Check total records
$result = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
$row = $result->fetch_assoc();
echo "Total Records in Database: " . $row['total'] . "\n\n";

// Check records by company
echo "Records by Company:\n";
$companies = $conn->query("SELECT company_name, COUNT(*) as cnt FROM delivery_records GROUP BY company_name");
if ($companies) {
    while ($comp = $companies->fetch_assoc()) {
        echo "  - " . $comp['company_name'] . ": " . $comp['cnt'] . " records\n";
    }
}

// Check total quantity delivered
echo "\nTotal Quantity Delivered: ";
$qty = $conn->query("SELECT SUM(quantity) as total FROM delivery_records");
if ($qty) {
    $qrow = $qty->fetch_assoc();
    echo ($qrow['total'] ?? 0) . " units\n";
}

$conn->close();
?>
