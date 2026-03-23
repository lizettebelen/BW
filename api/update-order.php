<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../db_config.php';

function identifyGrouping($itemName) {
    $lowerName = strtolower($itemName);
    
    if (strpos($lowerName, 'multi') !== false || 
        strpos($lowerName, 'quattro') !== false || 
        strpos($lowerName, 'quad') !== false ||
        preg_match('/o2.*lel|lel.*o2/', $lowerName)) {
        return 'Group B - Multi Gas';
    }
    
    return 'Group A - Single Gas';
}

$order_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit();
}

if (empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Status is required']);
    exit();
}

try {
    // Get order details
    $order_sql = "SELECT id, item_code, item_name, quantity, delivery_month, delivery_day, delivery_year FROM delivery_records WHERE id = ? AND company_name = 'Orders'";
    $order_stmt = $conn->prepare($order_sql);
    if (!$order_stmt) {
        throw new Exception("Prepare order select failed: " . $conn->error);
    }
    $order_stmt->bind_param("i", $order_id);
    if (!$order_stmt->execute()) {
        throw new Exception("Execute order select failed: " . $order_stmt->error);
    }
    $order_result = $order_stmt->get_result();
    
    if ($order_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit();
    }
    
    $order = $order_result->fetch_assoc();
    $order_stmt->close();

    // Convert quantity to integer to fix corrupted values from previous bug
    $quantity_int = intval($order['quantity']); 
    // If quantity looks corrupted (suspiciously large number), default to 1
    if ($quantity_int > 999999999) {
        $quantity_int = 1;
    }
    $day_int = intval($order['delivery_day']);
    $year_int = intval($order['delivery_year']);
    $now = date('Y-m-d H:i:s');

    if ($status === 'Delivered') {
        $grouping = identifyGrouping($order['item_name']);
        $uom = 'UNITS';
        
        // Check if item exists in inventory
        $check_sql = "SELECT id FROM delivery_records WHERE item_code = ? AND company_name = 'Stock Addition' LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $order['item_code']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $item_exists = $check_result->num_rows > 0;
        $check_stmt->close();
        
        if ($item_exists) {
            // Update existing inventory item
            $update_sql = "UPDATE delivery_records SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE item_code = ? AND company_name = 'Stock Addition'";
            $upd_stmt = $conn->prepare($update_sql);
            $upd_stmt->bind_param("is", $quantity_int, $order['item_code']);
            $upd_stmt->execute();
            $upd_stmt->close();
        } else {
            // Create new inventory item
            $company = 'Stock Addition';
            $received_status = 'Received';
            $insert_sql = "INSERT INTO delivery_records (delivery_month, delivery_day, delivery_year, item_code, item_name, quantity, company_name, status, groupings, uom, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $ins_stmt = $conn->prepare($insert_sql);
            if (!$ins_stmt) {
                throw new Exception("Prepare insert failed: " . $conn->error);
            }
            $ins_stmt->bind_param("siississsss", $order['delivery_month'], $day_int, $year_int, $order['item_code'], $order['item_name'], $quantity_int, $company, $received_status, $grouping, $uom, $now, $now);
            if (!$ins_stmt->execute()) {
                throw new Exception("Insert failed: " . $ins_stmt->error);
            }
            $ins_stmt->close();
        }
        
        // Delete order from Orders table
        $del_sql = "DELETE FROM delivery_records WHERE id = ? AND company_name = 'Orders'";
        $del_stmt = $conn->prepare($del_sql);
        $del_stmt->bind_param("i", $order_id);
        $del_stmt->execute();
        $del_stmt->close();
    } else {
        // Update status only
        $upd_sql = "UPDATE delivery_records SET status = ?, updated_at = ? WHERE id = ? AND company_name = 'Orders'";
        $upd_stmt = $conn->prepare($upd_sql);
        $upd_stmt->bind_param("ssi", $status, $now, $order_id);
        if (!$upd_stmt->execute()) {
            throw new Exception("Update status failed: " . $upd_stmt->error);
        }
        $upd_stmt->close();
    }

    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => ($status === 'Delivered' ? 'Order moved to inventory!' : 'Order updated!'),
        'moved_to_inventory' => ($status === 'Delivered')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
