<?php
require_once 'db_config.php';

echo "Testing exact queries:\n\n";

// Test 1: Simple query
echo "Test 1 - Simple SELECT:\n";
$result = $conn->query("SELECT item_code, item_name FROM delivery_records LIMIT 5");
echo "Result: " . ($result ? $result->num_rows : $conn->error) . " rows\n";
if ($result) {
    while ($r = $result->fetch_assoc()) {
        echo "  - " . $r['item_code'] . "\n";
    }
}

// Test 2: With conditions
echo "\nTest 2 - With NULL check:\n";
$result = $conn->query("SELECT item_code, item_name FROM delivery_records WHERE item_code IS NOT NULL LIMIT 5");
echo "Result: " . ($result ? $result->num_rows : $conn->error) . " rows\n";

// Test 3: With empty string check
echo "\nTest 3 - With empty check:\n";
$result = $conn->query("SELECT item_code, item_name FROM delivery_records WHERE item_code IS NOT NULL AND item_code != '' LIMIT 5");
echo "Result: " . ($result ? $result->num_rows : $conn->error) . " rows\n";

// Test 4: With DISTINCT
echo "\nTest 4 - With DISTINCT:\n";
$result = $conn->query("SELECT DISTINCT item_code, item_name FROM delivery_records WHERE item_code IS NOT NULL AND item_code != '' LIMIT 5");
echo "Result: " . ($result ? $result->num_rows : $conn->error) . " rows\n";
if ($result) {
    while ($r = $result->fetch_assoc()) {
        echo "  - " . $r['item_code'] . " | " . $r['item_name'] . "\n";
    }
}

// Test 5: With REGEXP
echo "\nTest 5 - With REGEXP condition:\n";
$result = $conn->query("SELECT DISTINCT item_code, item_name FROM delivery_records WHERE item_code IS NOT NULL AND item_code != '' AND item_code NOT REGEXP '^\\s*$' LIMIT 5");
echo "Result: " . ($result ? $result->num_rows : ($conn->error ? "ERROR: " . $conn->error : "0")) . " rows\n";
if ($result) {
    while ($r = $result->fetch_assoc()) {
        echo "  - " . $r['item_code'] . " | " . $r['item_name'] . "\n";
    }
}

// Test 6: Full query from delivery-records.php
echo "\nTest 6 - Full query:\n";
$result = $conn->query("
    SELECT DISTINCT item_code, item_name
    FROM delivery_records
    WHERE item_code IS NOT NULL 
      AND item_code != ''
      AND item_code NOT REGEXP '^\s*$'
      AND item_name IS NOT NULL
      AND item_name != ''
    ORDER BY item_code ASC
");
echo "Result: " . ($result ? $result->num_rows : ($conn->error ? "ERROR: " . $conn->error : "0")) . " rows\n";
if ($result) {
    $count = 0;
    while ($r = $result->fetch_assoc()) {
        if ($count < 5) {
            echo "  - " . $r['item_code'] . " | " . $r['item_name'] . "\n";
        }
        $count++;
    }
    echo "  ... (total " . $count . " rows)\n";
}
?>
