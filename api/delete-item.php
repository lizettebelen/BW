<?php
header('Content-Type: application/json');
session_start();

// Check authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }
    
    $item_code = $data['item_code'] ?? null;
    
    if (!$item_code) {
        throw new Exception('Missing item code');
    }
    
    // Escape input
    $item_code_esc = $conn->real_escape_string($item_code);
    
    // Delete all inventory items with this code
    $delete_result = $conn->query("DELETE FROM delivery_records WHERE item_code = '{$item_code_esc}' AND company_name = 'Stock Addition'");
    
    if (!$delete_result) {
        throw new Exception('Delete failed: ' . $conn->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Item deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
