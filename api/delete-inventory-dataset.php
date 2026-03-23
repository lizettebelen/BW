<?php
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../db_config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['source_file'])) {
    echo json_encode(['success' => false, 'message' => 'Missing source_file']);
    exit;
}

$sourceFile = $conn->real_escape_string($input['source_file']);

// Delete all items from this dataset
$sql = "DELETE FROM delivery_records WHERE company_name='Stock Addition' AND notes='$sourceFile'";
$result = @$conn->query($sql);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Dataset deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete dataset']);
}
?>
