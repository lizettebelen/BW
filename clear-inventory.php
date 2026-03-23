<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>Clear Old Inventory Data</h2>";

$result = @$conn->query("
    SELECT COUNT(*) as cnt 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition'
");

$count = 0;
if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['cnt'] ?? 0;
}

echo "<p>Current inventory records: <strong>$count</strong></p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $delete = @$conn->query("
        DELETE FROM delivery_records 
        WHERE company_name = 'Stock Addition'
    ");
    
    if ($delete) {
        echo "<p style='color: green; font-weight: bold;'>✓ Deleted! All old inventory data cleared.</p>";
        echo "<p>Now go to Upload and re-import your Excel file.</p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
    }
} else if ($count > 0) {
    echo "<form method='POST'>";
    echo "<button type='submit' name='confirm_delete' value='1' style='padding: 10px 20px; background: red; color: white; border: none; cursor: pointer;'>";
    echo "🗑️ DELETE ALL OLD INVENTORY DATA";
    echo "</button>";
    echo "</form>";
    echo "<p style='color: orange; font-size: 12px;'><strong>⚠️ Warning:</strong> This will delete all existing inventory records. You'll need to re-import from Excel.</p>";
} else {
    echo "<p style='color: green;'>✓ No inventory data to delete. Ready for fresh import!</p>";
}

if (method_exists($conn, 'close')) {
    @$conn->close();
}
?>
