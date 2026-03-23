<?php
require_once 'db_config.php';

echo "Testing inventory population...\n\n";

// Test 1: Check if delivery_records table exists
$result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
$row = $result->fetch_assoc();
echo "✓ Current Stock Addition items: " . $row['cnt'] . "\n";

// Test 2: Insert a single test item
$test_code = "TEST-ITEM-001";
$test_name = "Test Quattro Multi Gas Detector";
$test_group = "Group B - Multi Gas";

// Check if already exists
$checkSql = "SELECT id FROM delivery_records WHERE item_code = ? AND company_name = 'Stock Addition' LIMIT 1";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $test_code);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $now = date('Y-m-d H:i:s');
    $delivery_month = date('F');
    $delivery_day = intval(date('j'));
    $delivery_year = intval(date('Y'));
    $qty = 10;
    
    $insertSql = "INSERT INTO delivery_records 
                  (delivery_month, delivery_day, delivery_year, item_code, item_name, company_name, quantity, groupings, status, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, 'Stock Addition', ?, ?, 'Pending', ?, ?)";
    
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        echo "❌ Prepare failed: " . $conn->error . "\n";
    } else {
        $insertStmt->bind_param("siissiiss", $delivery_month, $delivery_day, $delivery_year, $test_code, $test_name, $qty, $test_group, $now, $now);
        
        if ($insertStmt->execute()) {
            echo "✓ Test item inserted successfully!\n";
        } else {
            echo "❌ Insert failed: " . $insertStmt->error . "\n";
        }
        $insertStmt->close();
    }
} else {
    echo "✓ Test item already exists\n";
}
$checkStmt->close();

// Test 3: Check groupings are stored
$result = $conn->query("SELECT item_code, groupings FROM delivery_records WHERE company_name = 'Stock Addition' LIMIT 5");
echo "\n✓ Sample items:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - {$row['item_code']}: {$row['groupings']}\n";
}

echo "\n✓ Database test complete! Now run populate-groupings.php\n";
?>
