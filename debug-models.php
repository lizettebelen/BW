<?php
require_once 'db_config.php';

echo "<h2>Database Status Check</h2>";

// Test 1: Check table exists
$result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>✓ Total records in delivery_records: <strong>" . $row['cnt'] . "</strong></p>";
} else {
    echo "<p>❌ Error querying delivery_records: " . $conn->error . "</p>";
}

// Test 2: Check Stock Addition records
$result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>✓ Stock Addition items: <strong>" . $row['cnt'] . "</strong></p>";
} else {
    echo "<p>❌ Error: " . $conn->error . "</p>";
}

// Test 3: Check groupings column
$result = $conn->query("SHOW COLUMNS FROM delivery_records LIKE 'groupings'");
if ($result && $result->num_rows > 0) {
    echo "<p>✓ Groupings column exists</p>";
} else {
    echo "<p>❌ Groupings column missing!</p>";
}

// Test 4: Sample Stock Addition items
echo "<h3>Sample Stock Addition Items:</h3>";
$result = $conn->query("SELECT item_code, item_name, groupings FROM delivery_records WHERE company_name = 'Stock Addition' LIMIT 10");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Code</th><th>Name</th><th>Grouping</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['groupings'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No Stock Addition items found</p>";
}

// Test 5: Show models.php query result
echo "<h3>What models.php would display:</h3>";
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
$monthRegex = implode('|', $monthNames);

$result = $conn->query("
    SELECT 
        item_code,
        MAX(item_name) as item_name,
        MAX(groupings) as groupings,
        SUM(quantity) as total_qty,
        COUNT(*) as order_count
    FROM delivery_records
    WHERE company_name = 'Stock Addition'
      AND item_code IS NOT NULL 
      AND item_code != ''
      AND item_code NOT REGEXP '^\s*\$'
    GROUP BY item_code
    HAVING item_name IS NOT NULL AND item_name != ''
    ORDER BY total_qty DESC
");

if ($result) {
    $count = $result->num_rows;
    echo "<p>Query returned: <strong>" . $count . "</strong> items</p>";
    
    if ($count > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Code</th><th>Name</th><th>Grouping</th><th>Qty</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['groupings'] ?? 'auto-detect') . "</td>";
            echo "<td>" . $row['total_qty'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>❌ Query error: " . $conn->error . "</p>";
}
?>
