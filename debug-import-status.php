<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>📊 Inventory Debug Report</h2>";

// Total records
echo "<h3>1. Total Records in Database</h3>";
$result = @$conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
$row = $result->fetch_assoc();
$totalCount = $row['cnt'] ?? 0;
echo "<p><strong>Total records: $totalCount</strong></p>";

// Unique item codes
echo "<h3>2. Unique Item Codes</h3>";
$result = @$conn->query("SELECT COUNT(DISTINCT item_code) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
$row = $result->fetch_assoc();
$uniqueCount = $row['cnt'] ?? 0;
echo "<p><strong>Unique item codes: $uniqueCount</strong></p>";

// Check for null values
echo "<h3>3. Data Completeness</h3>";
$result = @$conn->query("
    SELECT 
        SUM(CASE WHEN item_code IS NULL OR item_code = '' THEN 1 ELSE 0 END) as null_codes,
        SUM(CASE WHEN item_name IS NULL OR item_name = '' THEN 1 ELSE 0 END) as null_names,
        SUM(CASE WHEN box_code IS NULL OR box_code = '' THEN 1 ELSE 0 END) as null_box,
        SUM(CASE WHEN model_no IS NULL OR model_no = '' THEN 1 ELSE 0 END) as null_model
    FROM delivery_records 
    WHERE company_name = 'Stock Addition'
");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<ul>";
    echo "<li>Records with NULL item_code: " . ($row['null_codes'] ?? 0) . "</li>";
    echo "<li>Records with NULL item_name: " . ($row['null_names'] ?? 0) . "</li>";
    echo "<li>Records with NULL box_code: " . ($row['null_box'] ?? 0) . "</li>";
    echo "<li>Records with NULL model_no: " . ($row['null_model'] ?? 0) . "</li>";
    echo "</ul>";
}

// Sample data
echo "<h3>4. Sample Records (first 10)</h3>";
$result = @$conn->query("
    SELECT item_code, item_name, box_code, model_no, quantity 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition'
    LIMIT 10
");
if ($result) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Code</th><th>Name</th><th>Box</th><th>Model</th><th>Qty</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['box_code'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['model_no'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>5. Expected vs Actual</h3>";
echo "<p>Expected from Excel: <strong>164</strong></p>";
echo "<p>Current in database: <strong>$totalCount</strong></p>";
echo "<p>Difference: <strong>" . (164 - $totalCount) . " records missing</strong></p>";

if (method_exists($conn, 'close')) {
    @$conn->close();
}
?>
