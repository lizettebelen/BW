<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>Manual Test Import</h2>";

// Check connection type
echo "<p><strong>Database Type:</strong> " . get_class($conn) . "</p>";

// Create test data like the import would
$test_data = [
    ['ITEMS' => 'TEST001', 'DESCRIPTION' => 'Test Item 1', 'UOM' => 'UNITS', 'INVENTORY' => '50', 'BOX' => 'A'],
    ['ITEMS' => 'TEST002', 'DESCRIPTION' => 'Test Item 2', 'UOM' => 'UNITS', 'INVENTORY' => '75', 'BOX' => 'B'],
];

echo "<h3>Test Data to Import:</h3>";
echo "<pre>";
print_r($test_data);
echo "</pre>";

// Process like the API does
$now = date('Y-m-d H:i:s');
$current_year = date('Y');
$imported = 0;
$failed = 0;

$conn->begin_transaction();

foreach ($test_data as $i => $row_data) {
    $modelNo = trim($row_data['ITEMS'] ?? '');
    $desc = trim($row_data['DESCRIPTION'] ?? '');
    $qty = intval($row_data['INVENTORY'] ?? 0);
    $box = trim($row_data['BOX'] ?? '');
    
    if (!$modelNo) continue;
    
    $code = $box ? $box . '-' . substr($modelNo, 0, 3) : $modelNo;
    $name = $desc ?: $modelNo;
    $src = "Test Upload";
    
    $code_esc = $conn->real_escape_string($code);
    $name_esc = $conn->real_escape_string($name);
    $src_esc = $conn->real_escape_string($src);
    
    $sql = "INSERT INTO delivery_records (item_code, item_name, quantity, company_name, notes, status, delivery_month, delivery_day, delivery_year, created_at, updated_at) 
            VALUES ('$code_esc', '$name_esc', $qty, 'Stock Addition', '$src_esc', 'Inventory', 'Inventory', 1, $current_year, '$now', '$now')";
    
    echo "<h4>SQL Query $i:</h4>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    
    if (@$conn->query($sql)) {
        $imported++;
        echo "<p style='color: green;'>✓ Insert successful</p>";
    } else {
        $failed++;
        echo "<p style='color: red;'>✗ Insert failed: " . $conn->error . "</p>";
    }
}

$conn->commit();

echo "<h3>Import Results:</h3>";
echo "<p>Imported: $imported, Failed: $failed</p>";

// Verify
echo "<h3>Verification - Check for Test Items:</h3>";
$verify = $conn->query("SELECT * FROM delivery_records WHERE company_name = 'Stock Addition' AND item_code LIKE 'TEST%'");
if ($verify && $verify->num_rows > 0) {
    echo "<p style='color: green;'><strong>✓ Found " . $verify->num_rows . " test items in database!</strong></p>";
    echo "<table border='1' cellpadding='10'>";
    while ($v = $verify->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($v['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($v['item_name']) . "</td>";
        echo "<td>" . $v['quantity'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>✗ NO test items found in database!</strong></p>";
}

// Now test the inventory query
echo "<h3>Testing Inventory Query:</h3>";
$inv_query = $conn->query("
    SELECT 
        item_code,
        MAX(item_name) as item_name,
        COALESCE(SUM(quantity), 0) as current_stock
    FROM delivery_records
    WHERE company_name = 'Stock Addition'
    GROUP BY item_code
");

if ($inv_query) {
    echo "<p>Query returned " . $inv_query->num_rows . " grouped items</p>";
    if ($inv_query->num_rows > 0) {
        echo "<table border='1' cellpadding='10'>";
        while ($inv = $inv_query->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($inv['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($inv['item_name']) . "</td>";
            echo "<td>" . $inv['current_stock'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
}

?>
