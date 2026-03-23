<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>🔍 Inventory Debug - Full Diagnosis</h2>";

// 1. Check connection
echo "<h3>1. Database Connection</h3>";
if ($conn) {
    echo "<p style='color: green;'>✓ Database connected</p>";
    echo "<p>Connection type: " . (get_class($conn)) . "</p>";
} else {
    echo "<p style='color: red;'>✗ Database connection FAILED</p>";
    exit;
}

// 2. Check table structure
echo "<h3>2. Table Structure</h3>";
$structure = $conn->query("DESCRIBE delivery_records");
if ($structure) {
    echo "<p>Columns: " . $structure->num_rows . "</p>";
} else {
    echo "<p style='color: red;'>✗ Could not read table structure</p>";
}

// 3. Check total records
echo "<h3>3. Total Records in Database</h3>";
$total = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records");
$totalRow = $total->fetch_assoc();
echo "<p><strong>Total delivery_records:</strong> " . $totalRow['cnt'] . "</p>";

// 4. Check Stock Addition company records
echo "<h3>4. 'Stock Addition' Records</h3>";
$stock_add = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
$stock_row = $stock_add->fetch_assoc();
echo "<p><strong>Total 'Stock Addition' records:</strong> " . $stock_row['cnt'] . "</p>";

// 5. Show sample Stock Addition records with ALL fields
echo "<h3>5. Sample 'Stock Addition' Records (Full Details)</h3>";
$sample = $conn->query("
    SELECT id, item_code, item_name, quantity, company_name, status, 
           delivery_month, delivery_day, delivery_year, notes, created_at, updated_at
    FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    LIMIT 10
");
if ($sample && $sample->num_rows > 0) {
    echo "<p><strong>Found " . $sample->num_rows . " records</strong></p>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Code</th><th>Name</th><th>Qty</th><th>Company</th><th>Status</th><th>Month</th><th>Day</th><th>Year</th><th>Notes</th>";
    echo "</tr>";
    while ($r = $sample->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $r['id'] . "</td>";
        echo "<td>" . htmlspecialchars($r['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($r['item_name'], 0, 20)) . "</td>";
        echo "<td>" . $r['quantity'] . "</td>";
        echo "<td>" . htmlspecialchars($r['company_name']) . "</td>";
        echo "<td>" . htmlspecialchars($r['status']) . "</td>";
        echo "<td>" . ($r['delivery_month'] ?? 'NULL') . "</td>";
        echo "<td>" . ($r['delivery_day'] ?? 'NULL') . "</td>";
        echo "<td>" . ($r['delivery_year'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars(substr($r['notes'] ?? '', 0, 20)) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>NO records found!</strong></p>";
}

// 6. Check if any records are missing required fields
echo "<h3>6. Records with NULL Date Fields</h3>";
$null_check = $conn->query("
    SELECT COUNT(*) as cnt FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    AND (delivery_month IS NULL OR delivery_day IS NULL OR delivery_year IS NULL OR delivery_month = '')
");
$null_row = $null_check->fetch_assoc();
echo "<p>Records with NULL/empty date fields: " . $null_row['cnt'] . "</p>";

// 7. Test the actual inventory.php query
echo "<h3>7. Testing Inventory.php Query</h3>";
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
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Item Code</th><th>Item Name</th><th>Stock</th><th>Source File</th><th>Last Updated</th>";
        echo "</tr>";
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
            echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
            echo "<td>" . $row['current_stock'] . "</td>";
            echo "<td>" . htmlspecialchars($row['source_filename']) . "</td>";
            echo "<td>" . $row['last_updated'] . "</td>";
            echo "</tr>";
            $count++;
            if ($count >= 10) break;
        }
        echo "</table>";
        echo "<p>Showing first 10 of " . $result->num_rows . " total items</p>";
    } else {
        echo "<p style='color: red;'><strong>Query returned NO rows!</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>Query failed: " . $conn->error . "</strong></p>";
}

// 8. Check unique datasets
echo "<h3>8. Unique Dataset Sources</h3>";
$datasets = $conn->query("
    SELECT DISTINCT notes as source_file, COUNT(*) as item_count
    FROM delivery_records
    WHERE company_name = 'Stock Addition'
    GROUP BY notes
");
if ($datasets && $datasets->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    while ($d = $datasets->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($d['source_file']) . "</td><td>" . $d['item_count'] . " items</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No datasets found</p>";
}

// 9. Check all company_name values
echo "<h3>9. All Company Names in Database</h3>";
$companies = $conn->query("SELECT DISTINCT company_name, COUNT(*) as cnt FROM delivery_records GROUP BY company_name ORDER BY cnt DESC");
if ($companies && $companies->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    while ($c = $companies->fetch_assoc()) {
        $style = $c['company_name'] === 'Stock Addition' ? 'background: #ffffcc;' : '';
        echo "<tr style='" . $style . "'><td>" . htmlspecialchars($c['company_name']) . "</td><td>" . $c['cnt'] . " records</td></tr>";
    }
    echo "</table>";
}

?>
