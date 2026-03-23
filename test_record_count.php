<?php
require_once 'db_config.php';

$result = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
if ($result) {
    $row = $result->fetch_assoc();
    $totalRecords = isset($row['total']) ? intval($row['total']) : 0;
    echo "Total records: " . $totalRecords . "\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}

$conn->close();
?>
