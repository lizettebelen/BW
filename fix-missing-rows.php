<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>❌ Missing Rows Analysis</h2>";
echo "<p><strong>Detected: 160</strong> | <strong>Imported: 155</strong> | <strong>Missing: 5 rows</strong></p>";

echo "<h3>Possible Reasons for Missing Rows:</h3>";
echo "<ul>";
echo "<li>The ITEMS column is empty (even if other columns have data)</li>";
echo "<li>The row is not being read correctly from Excel</li>";
echo "<li>Special characters or encoding issues</li>";
echo "</ul>";

echo "<h3>Solution:</h3>";
echo "<ol>";
echo "<li><strong>Open your Excel file</strong></li>";
echo "<li><strong>Go through rows 1-160</strong> and check each one</li>";
echo "<li><strong>Make sure EVERY row has a value in the ITEMS column</strong></li>";
echo "<li><strong>Delete any row with empty ITEMS</strong></li>";
echo "<li><strong>Re-upload the file</strong></li>";
echo "</ol>";

echo "<h3>Current Database Status:</h3>";
$result = @$conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
$row = $result->fetch_assoc();
$count = $row['cnt'] ?? 0;
echo "<p>Records in database: <strong>$count</strong></p>";

echo "<h3>Dataset Info:</h3>";
$result = @$conn->query("SELECT dataset_name FROM delivery_records WHERE company_name = 'Stock Addition' LIMIT 1");
$row = $result->fetch_assoc();
if ($row) {
    echo "<p>Dataset name: <strong>" . htmlspecialchars($row['dataset_name']) . "</strong></p>";
}

echo "<h3>Sample of Imported Items:</h3>";
$result = @$conn->query("SELECT model_no, item_name FROM delivery_records WHERE company_name = 'Stock Addition' ORDER BY created_at DESC LIMIT 10");
if ($result) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ITEMS (Model)</th><th>DESCRIPTION</th></tr>";
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    foreach (array_reverse($rows) as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['model_no'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

if (method_exists($conn, 'close')) {
    @$conn->close();
}
?>
