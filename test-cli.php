<?php
// Direct test - no HTTP needed
require_once __DIR__ . '/db_config.php';

echo "\n=== BW Gas Detector - Database Diagnostic ===\n\n";

// Which database?
if ($conn instanceof mysqli) {
    echo "✓ Database: MySQL\n";
} else {
    echo "✓ Database: SQLite\n";
}

// Test 1: Total records
$result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✓ Total records: " . $row['cnt'] . "\n";
} else {
    echo "✗ Error: " . ($conn instanceof mysqli ? $conn->error : 'Unknown') . "\n";
}

// Test 2: Stock Addition records
$result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✓ Stock Addition items: " . $row['cnt'] . "\n";
} else {
    echo "✗ Error\n";
}

// Test 3: Show sample items
echo "\n=== Sample Stock Addition Items ===\n";
$result = $conn->query("SELECT item_code, item_name, groupings FROM delivery_records WHERE company_name = 'Stock Addition' LIMIT 3");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['item_code'] . " | " . substr($row['item_name'], 0, 40) . " | " . ($row['groupings'] ?? 'NULL') . "\n";
    }
} else {
    echo "No items found\n";
}

// Test 4: Models query
echo "\n=== Models Query Result ===\n";
$result = $conn->query("
    SELECT 
        item_code,
        MAX(item_name) as item_name,
        MAX(groupings) as groupings,
        SUM(quantity) as total_qty
    FROM delivery_records
    WHERE company_name = 'Stock Addition'
      AND item_code IS NOT NULL 
      AND item_code != ''
    GROUP BY item_code
    ORDER BY total_qty DESC
    LIMIT 5
");

if ($result) {
    echo "Query returned: " . $result->num_rows . " rows\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['item_code'] . " | Qty: " . $row['total_qty'] . " | Group: " . ($row['groupings'] ?? 'NULL') . "\n";
    }
} else {
    echo "✗ Query error\n";
}

echo "\n=== Done ===\n";
?>
