<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>📊 Import Debug - Inventory Completeness</h2>";

// Total rows in database (not grouped)
echo "<h3>1. Total Rows in Database (including duplicates)</h3>";
$result = @$conn->query("
    SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'
");
$row = $result->fetch_assoc();
$totalRows = $row['cnt'] ?? 0;
echo "<p><strong>Total database rows: $totalRows</strong></p>";

// Unique item codes (grouped)
echo "<h3>2. Unique Item Codes (what displays in table)</h3>";
$result = @$conn->query("
    SELECT COUNT(DISTINCT item_code) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'
");
$row = $result->fetch_assoc();
$uniqueItems = $row['cnt'] ?? 0;
echo "<p><strong>Unique item codes: $uniqueItems</strong></p>";

// Check for duplicates
echo "<h3>3. Duplicate Item Codes</h3>";
$result = @$conn->query("
    SELECT item_code, COUNT(*) as cnt 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition' 
    GROUP BY item_code 
    HAVING cnt > 1
    ORDER BY cnt DESC
");
$duplicates = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row;
    }
}

if (count($duplicates) > 0) {
    echo "<p style='color: orange;'><strong>⚠️ Found " . count($duplicates) . " duplicate item codes!</strong></p>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Item Code</th><th>Times Imported</th></tr>";
    foreach ($duplicates as $dup) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($dup['item_code']) . "</td>";
        echo "<td>" . $dup['cnt'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✓ No duplicates found</p>";
}

// Missing values
echo "<h3>4. Data Quality Check</h3>";
$result = @$conn->query("
    SELECT 
        SUM(CASE WHEN model_no IS NULL OR model_no = '' THEN 1 ELSE 0 END) as missing_model,
        SUM(CASE WHEN item_name IS NULL OR item_name = '' THEN 1 ELSE 0 END) as missing_name,
        SUM(CASE WHEN box_code IS NULL OR box_code = '' THEN 1 ELSE 0 END) as missing_box
    FROM delivery_records 
    WHERE company_name = 'Stock Addition'
");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<ul>";
    echo "<li>Rows with missing model_no (ITEMS): " . ($row['missing_model'] ?? 0) . "</li>";
    echo "<li>Rows with missing item_name (DESCRIPTION): " . ($row['missing_name'] ?? 0) . "</li>";
    echo "<li>Rows with missing box_code (BOX): " . ($row['missing_box'] ?? 0) . "</li>";
    echo "</ul>";
}

// Summary
echo "<h3>5. Summary</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Expected</th><th>Database Rows</th><th>Display Rows</th><th>Difference</th></tr>";
echo "<tr>";
echo "<td>160</td>";
echo "<td style='background: " . ($totalRows == 160 ? "green" : "red") . ";'>$totalRows</td>";
echo "<td>$uniqueItems</td>";
echo "<td style='background: " . ($totalRows == 160 ? "green" : "orange") . ";'>" . (160 - $totalRows) . "</td>";
echo "</tr>";
echo "</table>";

if ($totalRows < 160) {
    echo "<p style='color: red;'><strong>❌ " . (160 - $totalRows) . " rows are missing!</strong></p>";
    echo "<p>Possible reasons:</p>";
    echo "<ul>";
    echo "<li>Some rows have missing ITEMS (required field)</li>";
    echo "<li>Some rows were empty/blank</li>";
    echo "<li>Import encountered errors</li>";
    echo "</ul>";
}

if (method_exists($conn, 'close')) {
    @$conn->close();
}
?>
