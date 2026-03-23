<?php
require 'db_config.php';

echo "Testing DELETE API directly:\n";
echo "Current record count: ";
$count = $conn->query('SELECT COUNT(*) as total FROM delivery_records');
$row = $count->fetch_assoc();
echo $row['total'] . "\n";

echo "Attempting DELETE...\n";
$deleteResult = $conn->query('DELETE FROM delivery_records');
echo "Delete result type: " . gettype($deleteResult) . "\n";

if ($deleteResult === false) {
    echo "DELETE FAILED: " . $conn->error . "\n";
} else {
    echo "DELETE SUCCEEDED\n";
}

echo "New record count: ";
$count2 = $conn->query('SELECT COUNT(*) as total FROM delivery_records');
$row2 = $count2->fetch_assoc();
echo $row2['total'] . "\n";

$conn->close();
?>
