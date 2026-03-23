<?php
// Simulate the API call
header('Content-Type: application/json');

echo "=== Testing DELETE API Directly ===\n\n";

require 'db_config.php';

// Count before
echo "Records BEFORE delete:\n";
$before = $conn->query("SELECT COUNT(*) as t FROM delivery_records");
$b_row = $before->fetch_assoc();
echo "  Total: " . $b_row['t'] . "\n\n";

// Delete
echo "Executing DELETE...\n";
$del = $conn->query("DELETE FROM delivery_records");
echo "  Delete result: " . ($del ? "SUCCESS" : "FAILED") . "\n";
if (!$del) {
    echo "  Error: " . $conn->error . "\n";
}

echo "\nRecords AFTER delete:\n";
$after = $conn->query("SELECT COUNT(*) as t FROM delivery_records");
$a_row = $after->fetch_assoc();
echo "  Total: " . $a_row['t'] . "\n\n";

if ($a_row['t'] == 0) {
    echo "✓ DELETE WORKS! Data was successfully deleted.\n";
} else {
    echo "✗ DELETE FAILED! Data still exists in database.\n";
}

$conn->close();
?>
