<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>📋 Last Import Verification</h2>";

// Check the notes field for import metadata
echo "<h3>Import Source Files</h3>";
$result = @$conn->query("
    SELECT DISTINCT notes FROM delivery_records 
    WHERE company_name = 'Stock Addition'
    ORDER BY notes DESC
");

if ($result) {
    $files = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['notes']) && strpos($row['notes'], 'Upload:') === 0) {
            $files[] = $row['notes'];
        }
    }
    
    if (count($files) > 0) {
        echo "<ul>";
        foreach ($files as $file) {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
        echo "</ul>";
    }
}

// Show first and last 5 rows
echo "<h3>First 5 Items Imported</h3>";
$result = @$conn->query("
    SELECT item_code, model_no, item_name, box_code, quantity 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition'
    ORDER BY created_at ASC
    LIMIT 5
");
if ($result) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Code</th><th>Model</th><th>Name</th><th>Box</th><th>Qty</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['model_no'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['box_code'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Last 5 Items Imported</h3>";
$result = @$conn->query("
    SELECT item_code, model_no, item_name, box_code, quantity 
    FROM delivery_records 
    WHERE company_name = 'Stock Addition'
    ORDER BY created_at DESC
    LIMIT 5
");
if ($result) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Code</th><th>Model</th><th>Name</th><th>Box</th><th>Qty</th></tr>";
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    foreach (array_reverse($rows) as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['model_no'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['box_code'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

if (method_exists($conn, 'close')) {
    @$conn->close();
}
?>
