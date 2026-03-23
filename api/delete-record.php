<?php
/**
 * API Endpoint: Delete Delivery Record
 * Deletes a delivery record by ID
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST or DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Include database configuration
require_once __DIR__ . '/../db_config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required field
if (!isset($input['id']) || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Record ID is required']);
    exit();
}

$id = intval($input['id']);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit();
}

try {
    // Check if record exists first
    $check_stmt = $conn->prepare("SELECT id FROM delivery_records WHERE id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit();
    }
    $check_stmt->close();
    
    // Delete the record
    $delete_stmt = $conn->prepare("DELETE FROM delivery_records WHERE id = ?");
    $delete_stmt->bind_param("i", $id);
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Record deleted successfully',
                'deleted_id' => $id
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Record not found or already deleted']);
        }
    } else {
        throw new Exception("Failed to delete record: " . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
