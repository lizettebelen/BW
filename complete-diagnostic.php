<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>🔧 Complete Database Diagnostic</h2>";

// 1. Determine which database is being used
echo "<h3>Database System</h3>";
$db_type = get_class($conn);
echo "<p><strong>Type:</strong> " . $db_type . "</p>";

if ($db_type === 'mysqli') {
    echo "<p style='color: green;'>✓ Using MySQL/MariaDB</p>";
    $info = $conn->get_server_info();
    echo "<p>Version: " . $info . "</p>";
} elseif ($db_type === 'SqliteConn') {
    echo "<p style='background: lightyellow; padding: 10px;'>⚠ Using SQLite (MySQL not available)</p>";
    echo "<p style='font-size: 12px;'>This might cause issues with certain queries.</p>";
} else {
    echo "<p style='color: red;'>✗ Unknown database type: " . $db_type . "</p>";
}

// 2. Try a simple test query
echo "<h3>Connection Test</h3>";
$test = $conn->query("SELECT 1 as test");
if ($test) {
    echo "<p style='color: green;'>✓ Query execution works</p>";
} else {
    echo "<p style='color: red;'>✗ Query failed</p>";
}

// 3. Check table existence
echo "<h3>Table Check</h3>";
$tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
if (!$tables) {
    // Try MySQL style
    $tables = $conn->query("SHOW TABLES");
}

if ($tables && $tables->num_rows > 0) {
    echo "<p style='color: green;'>✓ Tables found: " . $tables->num_rows . "</p>";
    echo "<ul>";
    while ($t = $tables->fetch_assoc()) {
        $table_name = array_values($t)[0];
        echo "<li>" . htmlspecialchars($table_name) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ No tables found</p>";
}

// 4. Direct count query
echo "<h3>Record Counts</h3>";

// Test 1: Count all delivery_records
$count1 = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records");
if ($count1) {
    $r1 = $count1->fetch_assoc();
    echo "<p>Total delivery_records: <strong>" . $r1['cnt'] . "</strong></p>";
}

// Test 2: Count Stock Addition specifically
$count2 = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
if ($count2) {
    $r2 = $count2->fetch_assoc();
    echo "<p>Stock Addition records: <strong>" . $r2['cnt'] . "</strong></p>";
}

// Test 3: Distinct item codes
$count3 = $conn->query("SELECT COUNT(DISTINCT item_code) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
if ($count3) {
    $r3 = $count3->fetch_assoc();
    echo "<p>Distinct item codes: <strong>" . $r3['cnt'] . "</strong></p>";
}

// 5. Show raw data
echo "<h3>Raw Stock Addition Records (First 5)</h3>";
$raw = $conn->query("SELECT * FROM delivery_records WHERE company_name = 'Stock Addition' LIMIT 5");
if ($raw && $raw->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='font-size: 12px; border-collapse: collapse;'>";
    
    // Get column headers from first row
    $first_row = $raw->fetch_assoc();
    echo "<tr style='background: #f0f0f0;'>";
    foreach (array_keys($first_row) as $key) {
        echo "<th>" . htmlspecialchars($key) . "</th>";
    }
    echo "</tr>";
    
    // First row
    echo "<tr>";
    foreach ($first_row as $val) {
        $display = strlen($val) > 30 ? substr($val, 0, 30) . "..." : $val;
        echo "<td>" . htmlspecialchars($display) . "</td>";
    }
    echo "</tr>";
    
    // Remaining rows
    for ($i = 0; $i < 4 && $row = $raw->fetch_assoc(); $i++) {
        echo "<tr>";
        foreach ($row as $val) {
            $display = strlen($val) > 30 ? substr($val, 0, 30) . "..." : $val;
            echo "<td>" . htmlspecialchars($display) . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>✗ NO records found!</strong></p>";
}

// 6. Test the GROUP BY query
echo "<h3>Group By Query Result</h3>";
$group = $conn->query("
    SELECT 
        item_code,
        MAX(item_name) as item_name,
        COALESCE(SUM(quantity), 0) as current_stock,
        COUNT(*) as row_count
    FROM delivery_records
    WHERE company_name = 'Stock Addition'
    GROUP BY item_code
    LIMIT 5
");

if ($group) {
    echo "<p>Rows returned: " . $group->num_rows . "</p>";
    if ($group->num_rows > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Item Code</th><th>Item Name</th><th>Stock</th><th>Row Count</th>";
        echo "</tr>";
        while ($g = $group->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($g['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($g['item_name'], 0, 30)) . "</td>";
            echo "<td>" . $g['current_stock'] . "</td>";
            echo "<td>" . $g['row_count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
}

// 7. Check for any errors in database
echo "<h3>Database Errors</h3>";
if ($db_type === 'mysqli') {
    if ($conn->errno) {
        echo "<p style='color: red;'>Last error: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>No errors</p>";
    }
} else {
    if ($conn->error) {
        echo "<p style='color: red;'>Last error: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>No errors</p>";
    }
}

?>
