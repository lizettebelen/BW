<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../db_config.php';

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'MySQL connection required.',
    ]);
    exit;
}

$dbResult = $conn->query('SELECT DATABASE() AS db_name');
$dbRow = $dbResult ? $dbResult->fetch_assoc() : null;
$activeDb = isset($dbRow['db_name']) ? (string) $dbRow['db_name'] : '';
$requiredDb = defined('APP_REQUIRED_DB_NAME') ? APP_REQUIRED_DB_NAME : 'bw_gas_detector';

echo json_encode([
    'success' => true,
    'engine' => 'mysql',
    'active_database' => $activeDb,
    'required_database' => $requiredDb,
    'matches_required' => ($activeDb === $requiredDb),
]);
