<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../db_config.php';

$item_code = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';

if (empty($item_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Item code is required']);
    exit();
}

try {
    $orders = [];
    
    // 1. Get pending/processing orders from Orders table
    $sql_orders = "SELECT 
                    id,
                    item_code,
                    item_name,
                    quantity,
                    status,
                    delivery_month,
                    delivery_day,
                    delivery_year,
                    notes,
                    created_at,
                    updated_at,
                    'pending' as order_type
                FROM delivery_records
                WHERE item_code = ? AND company_name = 'Orders'
                ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql_orders);
    if (!$stmt) {
        throw new Exception("Prepare orders failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $item_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Extract expected delivery date from notes if available
        $expected_delivery = null;
        if ($row['notes'] && strpos($row['notes'], '[Expected Delivery:') !== false) {
            preg_match('/\[Expected Delivery: (\d{4}-\d{2}-\d{2})\]/', $row['notes'], $matches);
            if (!empty($matches[1])) {
                $expected_delivery = $matches[1];
            }
        }
        
        // Create order date from delivery_month, delivery_day, delivery_year
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
        $month_num = array_search($row['delivery_month'], $months);
        if ($month_num !== false) {
            $order_date = sprintf('%04d-%02d-%02d', $row['delivery_year'], $month_num + 1, $row['delivery_day']);
        } else {
            $order_date = null;
        }
        
        $orders[] = [
            'id' => $row['id'],
            'item_code' => $row['item_code'],
            'item_name' => $row['item_name'],
            'quantity' => $row['quantity'],
            'status' => $row['status'],
            'order_date' => $order_date,
            'expected_delivery_date' => $expected_delivery,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'order_type' => 'pending'
        ];
    }
    
    $stmt->close();
    
    // 2. Get inventory/delivered info from Stock Addition table
    $sql_inventory = "SELECT 
                    id,
                    item_code,
                    item_name,
                    quantity,
                    status,
                    delivery_month,
                    delivery_day,
                    delivery_year,
                    created_at,
                    updated_at
                FROM delivery_records
                WHERE item_code = ? AND company_name = 'Stock Addition'
                ORDER BY updated_at DESC";
    
    $stmt_inv = $conn->prepare($sql_inventory);
    if (!$stmt_inv) {
        throw new Exception("Prepare inventory failed: " . $conn->error);
    }
    
    $stmt_inv->bind_param("s", $item_code);
    $stmt_inv->execute();
    $result_inv = $stmt_inv->get_result();
    
    if ($result_inv->num_rows > 0) {
        // Loop through ALL inventory records
        while ($inv_row = $result_inv->fetch_assoc()) {
            // Original order date from delivery_month, delivery_day, delivery_year
            $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
            $month_num = array_search($inv_row['delivery_month'], $months);
            if ($month_num !== false) {
                $order_date = sprintf('%04d-%02d-%02d', $inv_row['delivery_year'], $month_num + 1, $inv_row['delivery_day']);
            } else {
                $order_date = null;
            }
            
            // Delivery date from created_at (when it was moved to inventory)
            $delivery_date = null;
            if ($inv_row['created_at']) {
                $delivery_date = substr($inv_row['created_at'], 0, 10); // Extract date part from timestamp
            }
            
            // Add as inventory record
            $orders[] = [
                'id' => $inv_row['id'],
                'item_code' => $inv_row['item_code'],
                'item_name' => $inv_row['item_name'],
                'quantity' => $inv_row['quantity'],
                'status' => 'In Inventory',
                'order_date' => $order_date,
                'expected_delivery_date' => $delivery_date,
                'created_at' => $inv_row['created_at'],
                'updated_at' => $inv_row['updated_at'],
                'order_type' => 'delivered'
            ];
        }
    }
    
    $stmt_inv->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
