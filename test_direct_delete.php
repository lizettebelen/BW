<?php
// Direct test of delete API
require 'db_config.php';

echo "Testing direct API call:\n";
echo "Records before delete: ";

$count = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
$row = $count->fetch_assoc();
echo $row['total'] . "\n";

echo "Calling delete...\n";
$result = $conn->query("DELETE FROM delivery_records");

echo "Delete result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

echo "Records after delete: ";
$count2 = $conn->query("SELECT COUNT(*) as total FROM delivery_records");
$row2 = $count2->fetch_assoc();
echo $row2['total'] . "\n";

$conn->close();
?>
