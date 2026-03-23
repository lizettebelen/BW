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
    $quantity = intval($data['quantity'] ?? 0);
    
    if (!$item_code || $quantity <= 0) {
        throw new Exception('Invalid item code or quantity');
    }
    
    // Escape input
    $item_code_esc = $conn->real_escape_string($item_code);
    
    // Get current total quantity
    $result = $conn->query("SELECT COALESCE(SUM(quantity), 0) as current_total FROM delivery_records WHERE item_code = '{$item_code_esc}' AND company_name = 'Stock Addition'");
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $current_total = intval($row['current_total']);
    
    // Check if we have enough stock to reduce
    if ($quantity > $current_total) {
        throw new Exception('Insufficient stock. Current: ' . $current_total);
    }
    
    // Find the most recent record for this item
    $recent = $conn->query("SELECT id, quantity FROM delivery_records WHERE item_code = '{$item_code_esc}' AND company_name = 'Stock Addition' ORDER BY updated_at DESC LIMIT 1");
    if (!$recent) {
        throw new Exception('Failed to fetch stock record: ' . $conn->error);
    }
    
    if ($row = $recent->fetch_assoc()) {
        // Reduce the quantity
        $new_qty = intval($row['quantity']) - $quantity;
        $update_result = $conn->query("UPDATE delivery_records SET quantity = {$new_qty}, updated_at = CURRENT_TIMESTAMP WHERE id = " . intval($row['id']));
        if (!$update_result) {
            throw new Exception('Update failed: ' . $conn->error);
        }
    } else {
        throw new Exception('No stock record found for this item');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock reduced successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
