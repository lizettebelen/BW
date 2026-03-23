<?php
// Test delete API endpoint directly
header('Content-Type: application/json');

echo "Testing DELETE endpoint:\n";

// Try accessing the delete API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/BWGD/api/delete-all-records.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";
?>
