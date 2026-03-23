<?php
require 'db_config.php';

// Insert test data
$testData = [
    ['Acme Corp', 'SKU-001', 'Cylinder A', 2026, 3, 9, 10],
    ['Acme Corp', 'SKU-002', 'Cylinder B', 2026, 3, 9, 5],
    ['to Andison Manila', 'SKU-003', 'Cylinder C', 2026, 3, 9, 15],
    ['TechSupply Ltd', 'SKU-001', 'Cylinder A', 2026, 3, 9, 8],
    ['Global Distributors', 'SKU-004', 'Regulator X', 2026, 3, 9, 12],
];

echo "Adding test data...\n";
$count = 0;

foreach ($testData as $data) {
    list($company, $serial, $item, $year, $month, $day, $units) = $data;
    
    $sql = "INSERT INTO delivery_records (company_name, item_code, item_name, delivery_year, delivery_month, delivery_day, quantity, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Delivered')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssiiis', $company, $serial, $item, $year, $month, $day, $units);
    
    if ($stmt->execute()) {
        $count++;
        echo "✓ Inserted: $company - $item\n";
    } else {
        echo "✗ Failed: " . $stmt->error . "\n";
    }
}

echo "\nTotal inserted: $count records\n";

// Verify
$result = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
$row = $result->fetch_assoc();
echo "Total records in database: " . $row['total'] . "\n";

$conn->close();
?>
