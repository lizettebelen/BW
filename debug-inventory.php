<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

// Debug: Check if connection is working
echo "<h2>Database Debug - Inventory Issue</h2>";
echo "<p>User: " . $_SESSION['user_id'] . "</p>";

// Check total records in delivery_records
$total = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records");
if ($total) {
    $row = $total->fetch_assoc();
    echo "<p><strong>Total delivery_records:</strong> " . $row['cnt'] . "</p>";
}

// Check records with company_name = 'Stock Addition'
$stock_add = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
if ($stock_add) {
    $row = $stock_add->fetch_assoc();
    echo "<p><strong>Stock Addition records:</strong> " . $row['cnt'] . "</p>";
}

// Show first 10 records with 'Stock Addition'
echo "<h3>First 10 Stock Addition Records:</h3>";
$records = $conn->query("
    SELECT id, item_code, item_name, quantity, company_name, status, created_at 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    LIMIT 10
");
if ($records && $records->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Item Code</th><th>Item Name</th><th>Quantity</th><th>Company</th><th>Status</th><th>Created</th></tr>";
    while ($r = $records->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $r['id'] . "</td>";
        echo "<td>" . htmlspecialchars($r['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($r['item_name']) . "</td>";
        echo "<td>" . $r['quantity'] . "</td>";
        echo "<td>" . htmlspecialchars($r['company_name']) . "</td>";
        echo "<td>" . htmlspecialchars($r['status']) . "</td>";
        echo "<td>" . $r['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>No Stock Addition records found!</strong></p>";
}

// Test the actual inventory.php query
echo "<h3>Testing inventory.php Query:</h3>";
$items = [];
$result = $conn->query("
    SELECT 
        item_code,
        MAX(item_name) as item_name,
        COALESCE(SUM(quantity), 0) as current_stock,
        MAX(notes) as source_filename,
        MAX(updated_at) as last_updated
    FROM delivery_records
    WHERE company_name = 'Stock Addition'
    GROUP BY item_code
    ORDER BY item_name ASC
");

if ($result) {
    echo "<p><strong>Query executed successfully</strong></p>";
    echo "<p><strong>Rows returned:</strong> " . $result->num_rows . "</p>";
    
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Item Code</th><th>Item Name</th><th>Stock</th><th>Source File</th><th>Last Updated</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
            echo "<td>" . $row['current_stock'] . "</td>";
            echo "<td>" . htmlspecialchars($row['source_filename']) . "</td>";
            echo "<td>" . $row['last_updated'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>Query returned no rows!</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>Query failed: " . $conn->error . "</strong></p>";
}

// Check if there are any datasets in the system
echo "<h3>All Unique Company Names:</h3>";
$companies = $conn->query("SELECT DISTINCT company_name FROM delivery_records ORDER BY company_name");
if ($companies && $companies->num_rows > 0) {
    echo "<ul>";
    while ($c = $companies->fetch_assoc()) {
        $name = htmlspecialchars($c['company_name']);
        $count = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = '{$c['company_name']}'");
        $cnt_row = $count->fetch_assoc();
        echo "<li>$name: " . $cnt_row['cnt'] . " records</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No company names found</p>";
}
