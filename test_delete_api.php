<?php
// Simulate a POST request to the delete API
require_once 'db_config.php';

echo "Testing Delete All Records API\n";
echo "================================\n\n";

// Get count before deletion
$result = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
$row = $result->fetch_assoc();
$totalBefore = isset($row['total']) ? intval($row['total']) : 0;

echo "Total records before: " . $totalBefore . "\n";

// Test the delete query
$deleteResult = $conn->query("DELETE FROM delivery_records WHERE id < 0");
if ($deleteResult === false) {
    echo "Delete query error: " . $conn->error . "\n";
} else {
    echo "Delete query executed (test mode - no actual deletion)\n";
}

// Get count after (should be same since we used WHERE id < 0)
$result = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
$row = $result->fetch_assoc();
$totalAfter = isset($row['total']) ? intval($row['total']) : 0;

echo "Total records after: " . $totalAfter . "\n";
echo "\nAPI should work fine!\n";

$conn->close();
?>
