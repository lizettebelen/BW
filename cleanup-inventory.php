<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

// This script cleans up any bad inventory records and prepares for fresh import

echo "<h2>Inventory Cleanup</h2>";

// Check current state
$result = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
$row = $result->fetch_assoc();
$current_count = $row['cnt'];
echo "<p>Current 'Stock Addition' records: <strong>$current_count</strong></p>";

// Check for records with NULL or empty date fields
$bad_records = $conn->query("
    SELECT COUNT(*) as cnt FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    AND (delivery_month IS NULL OR delivery_month = '' OR delivery_day IS NULL OR delivery_year IS NULL)
");
if ($bad_records) {
    $bad_row = $bad_records->fetch_assoc();
    $bad_count = $bad_row['cnt'];
    echo "<p>Records with NULL/empty date fields: <strong>$bad_count</strong></p>";
    
    if ($bad_count > 0) {
        echo "<p><form method='POST'>";
        echo "<button type='submit' name='action' value='cleanup' style='padding: 10px 20px; background: #ff6b6b; color: white; border: none; border-radius: 5px; cursor: pointer;'>";
        echo "Delete Bad Records and Start Fresh";
        echo "</button>";
        echo "</form></p>";
    }
}

// Process cleanup if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup') {
    // Delete all Stock Addition records
    $delete = $conn->query("DELETE FROM delivery_records WHERE company_name = 'Stock Addition'");
    
    if ($delete !== false) {
        echo "<p style='color: green;'><strong>✓ Cleaned up all Stock Addition records!</strong></p>";
        echo "<p>The inventory is now ready for a fresh import. Please:</p>";
        echo "<ol>";
        echo "<li>Go to <a href='upload-data.php'>Upload Data</a></li>";
        echo "<li>Upload your Inventory_Sample.xlsx file again</li>";
        echo "<li>Check <a href='inventory.php'>Inventory</a> to see the items</li>";
        echo "</ol>";
    } else {
        echo "<p style='color: red;'><strong>✗ Cleanup failed!</strong></p>";
    }
}

// Show some sample records
echo "<h3>Sample Stock Addition Records:</h3>";
$sample = $conn->query("SELECT id, item_code, item_name, quantity, delivery_month, delivery_day, delivery_year FROM delivery_records WHERE company_name = 'Stock Addition' LIMIT 5");
if ($sample && $sample->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Item Code</th><th>Item Name</th><th>Quantity</th><th>Month</th><th>Day</th><th>Year</th></tr>";
    while ($r = $sample->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $r['id'] . "</td>";
        echo "<td>" . htmlspecialchars($r['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($r['item_name']) . "</td>";
        echo "<td>" . $r['quantity'] . "</td>";
        echo "<td>" . ($r['delivery_month'] ?? 'NULL') . "</td>";
        echo "<td>" . ($r['delivery_day'] ?? 'NULL') . "</td>";
        echo "<td>" . ($r['delivery_year'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No Stock Addition records found in database.</p>";
}
?>
