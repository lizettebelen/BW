<?php
require_once 'db_config.php';

// Check connection type
if ($conn instanceof mysqli) {
    echo "Connection type: MySQL\n";
} else {
    echo "Connection type: SQLite\n";
}

// Count total records
$countResult = $conn->query('SELECT COUNT(*) as total FROM delivery_records');
$countRow = $countResult->fetch_assoc();
echo "Total records in database: " . $countRow['total'] . "\n\n";

$result = $conn->query('SELECT * FROM delivery_records ORDER BY id DESC LIMIT 5');
if ($result) {
    echo "Last 5 records:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Invoice: " . $row['invoice_no'] . " | Item: " . $row['item_name'] . "\n";
    }
} else {
    echo "Query failed\n";
}
?>
