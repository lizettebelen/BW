<?php
session_start();

// Get total record count from database
require_once 'db_config.php';
$totalRecords = 0;

if ($conn) {
    $result = @$conn->query("SELECT COUNT(*) as total FROM delivery_records");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && isset($row['total'])) {
            $totalRecords = intval($row['total']);
        }
    }
}

echo "Total Records: " . number_format($totalRecords) . "\n";
echo "Count value (raw): " . $totalRecords . "\n";
?>
