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
    
    $original_item_code = $data['original_item_code'] ?? null;
    $item_code = $data['item_code'] ?? null;
    $item_name = $data['item_name'] ?? null;
    $current_stock = intval($data['current_stock'] ?? 0);
    
    if (!$original_item_code || !$item_code || !$item_name) {
        throw new Exception('Missing required fields');
    }
    
    // Escape inputs
    $original_item_code_esc = $conn->real_escape_string($original_item_code);
    $item_code_esc = $conn->real_escape_string($item_code);
    $item_name_esc = $conn->real_escape_string($item_name);
    
    // If item code changed, update all records with that code
    if ($original_item_code !== $item_code) {
        $conn->query("UPDATE delivery_records SET item_code = '{$item_code_esc}' WHERE item_code = '{$original_item_code_esc}' AND company_name = 'Stock Addition'");
    }
    
    // Update item_name for all matching items
    $conn->query("UPDATE delivery_records SET item_name = '{$item_name_esc}' WHERE item_code = '{$item_code_esc}' AND company_name = 'Stock Addition'");
    
    // Get current total quantity
    $result = $conn->query("SELECT COALESCE(SUM(quantity), 0) as current_total FROM delivery_records WHERE item_code = '{$item_code_esc}' AND company_name = 'Stock Addition'");
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $current_total = intval($row['current_total']);
    
    // Calculate difference
    $difference = $current_stock - $current_total;
    
    // If there's a difference, we need to add/remove quantity to balance it
    if ($difference !== 0) {
        // Find the most recent record for this item
        $recent = $conn->query("SELECT id, quantity FROM delivery_records WHERE item_code = '{$item_code_esc}' AND company_name = 'Stock Addition' ORDER BY updated_at DESC LIMIT 1");
        if ($recent && $row = $recent->fetch_assoc()) {
            // Update the most recent record's quantity
            $new_qty = intval($row['quantity']) + $difference;
            $update_result = $conn->query("UPDATE delivery_records SET quantity = {$new_qty}, updated_at = CURRENT_TIMESTAMP WHERE id = " . intval($row['id']));
            if (!$update_result) {
                throw new Exception('Update failed: ' . $conn->error);
            }
        } else {
            // No record exists, create one
            $insert_result = $conn->query("INSERT INTO delivery_records (item_code, item_name, company_name, status, quantity, delivery_month, updated_at, notes) VALUES ('{$item_code_esc}', '{$item_name_esc}', 'Stock Addition', 'Stored', {$current_stock}, '" . date('F') . "', CURRENT_TIMESTAMP, 'Manual Edit')");
            if (!$insert_result) {
                throw new Exception('Insert failed: ' . $conn->error);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
