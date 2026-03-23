<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>Database Debug - BOX Column</h2>";

// Check table structure
echo "<h3>1. Table Structure</h3>";
$result = @$conn->query("PRAGMA table_info(delivery_records)");
if ($result) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Name</th><th>Type</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . ($row['name'] ?? $row[1]) . "</td>";
        echo "<td>" . ($row['type'] ?? $row[2]) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check sample data
echo "<h3>2. Sample Data (first 5 items)</h3>";
$result = @$conn->query("
    SELECT item_code, item_name, box_code, quantity 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    LIMIT 5
");
if ($result) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Item Code</th><th>Item Name</th><th>Box Code</th><th>Quantity</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['box_code'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check column exists
echo "<h3>3. Verify box_code Column Exists</h3>";
$check = @$conn->query("PRAGMA table_info(delivery_records)");
$has_box = false;
if ($check) {
    while ($row = $check->fetch_assoc()) {
        if (($row['name'] ?? $row[1]) === 'box_code') {
            $has_box = true;
            break;
        }
    }
}
echo $has_box ? "✓ box_code column EXISTS" : "✗ box_code column MISSING";

if (method_exists($conn, 'close')) {
    @$conn->close();
}
?>
