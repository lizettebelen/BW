<?php
require_once 'db_config.php';

// Check records
$result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records");
$row = $result->fetch_assoc();
echo "Total records: " . $row['cnt'] . "\n\n";

// Show sample records
if ($row['cnt'] > 0) {
    $result = $conn->query("SELECT item_code, item_name, company_name, quantity FROM delivery_records LIMIT 5");
    while ($r = $result->fetch_assoc()) {
        echo "{$r['item_code']} - {$r['item_name']} -> {$r['company_name']}: {$r['quantity']}\n";
    }
} else {
    echo "Database is empty. Adding test data...\n\n";
    
    // Add test data
    $testData = [
        "('MCX3-BC1', 'BW Gas Detector - Model 3 BC1', 'Stock Addition', 50, 'January', 5, 2025, 'Delivered', 'Initial stock'),
         ('MCX3-BC1', 'BW Gas Detector - Model 3 BC1', 'Company A', 10, 'January', 15, 2025, 'Delivered', 'Delivered'),
         ('MCX3-BC1', 'BW Gas Detector - Model 3 BC1', 'Company B', 5, 'January', 20, 2025, 'Delivered', 'Delivered'),
         ('MCX3-BC2', 'BW Gas Detector - Model 3 BC2', 'Stock Addition', 30, 'January', 5, 2025, 'Delivered', 'Initial stock'),
         ('MCX3-BC2', 'BW Gas Detector - Model 3 BC2', 'Company C', 8, 'January', 18, 2025, 'Delivered', 'Delivered')"
    ];
    
    $sql = "INSERT INTO delivery_records (item_code, item_name, company_name, quantity, delivery_month, delivery_day, delivery_year, status, notes) VALUES " . implode(",", $testData);
    
    if ($conn->query($sql)) {
        echo "✓ Test data added successfully!\n";
        echo "\nTest records created:\n";
        echo "- MCX3-BC1: 50 added (Stock) - 10 to Company A - 5 to Company B = 35 current\n";
        echo "- MCX3-BC2: 30 added (Stock) - 8 to Company C = 22 current\n";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
