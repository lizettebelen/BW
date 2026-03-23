<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Include database config
require_once '../db_config.php';

// Get POST data
$order_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validate input
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit();
}

try {
    // Delete the order
    $delete_sql = "DELETE FROM delivery_records WHERE id = ? AND company_name = 'Orders'";
    $delete_stmt = $conn->prepare($delete_sql);
    if (!$delete_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $delete_stmt->bind_param("i", $order_id);
    if (!$delete_stmt->execute()) {
        throw new Exception("Execute failed: " . $delete_stmt->error);
    }
    
    if ($delete_stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit();
    }
    
    $delete_stmt->close();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
