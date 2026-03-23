<?php
echo "=== SIMULATING BROWSER DELETE REQUEST ===\n\n";

// Simulate a POST request to the API
$url = 'http://localhost/BWGD/api/delete-all-records.php';

// Check records before
require 'db_config.php';
echo "Records BEFORE delete: ";
$before = $conn->query("SELECT COUNT(*) as t FROM delivery_records");
$b = $before->fetch_assoc();
echo $b['t'] . "\n\n";
$conn->close();

// Make the POST request
echo "Sending POST request to API...\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'delete_all']));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n";
echo $response . "\n\n";

// Parse response
$data = json_decode($response, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "✓ API returned success\n";
    echo "  Deleted count: " . $data['deleted_count'] . "\n";
} else {
    echo "✗ API returned failure\n";
    if ($data) {
        echo "  Message: " . $data['message'] . "\n";
    }
}

// Check records after
echo "\nRecords AFTER delete: ";
require 'db_config.php';
$after = $conn->query("SELECT COUNT(*) as t FROM delivery_records");
$a = $after->fetch_assoc();
echo $a['t'] . "\n\n";

if ($a['t'] == 0) {
    echo "✓✓✓ SUCCESS! All data was deleted.\n";
} else {
    echo "✗✗✗ FAILED! Data still exists.\n";
}

$conn->close();

// Show log file
echo "\n=== DELETE API LOG ===\n";
if (file_exists('delete_api.log')) {
    echo file_get_contents('delete_api.log');
} else {
    echo "No log file yet\n";
}
?>
