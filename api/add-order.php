<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../db_config.php';

// Function to identify grouping
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

// Get POST data
$item_code = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$order_date = isset($_POST['order_date']) ? trim($_POST['order_date']) : '';
$expected_delivery = isset($_POST['expected_delivery']) ? trim($_POST['expected_delivery']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate inputs
if (empty($item_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Item code is required']);
    exit();
}

if ($quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Quantity must be greater than 0']);
    exit();
}

if (empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Status is required']);
    exit();
}

if (empty($order_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order date is required']);
    exit();
}

try {
    // Try to get item details from inventory
    $item_sql = "SELECT item_code, item_name FROM delivery_records 
                 WHERE item_code = ? AND company_name = 'Stock Addition' LIMIT 1";
    $item_stmt = $conn->prepare($item_sql);
    $item_stmt->bind_param("s", $item_code);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();
    
    // If item exists in inventory, get its name; otherwise use item_code as item_name
    if ($item_result->num_rows > 0) {
        $item_row = $item_result->fetch_assoc();
        $item_name = $item_row['item_name'];
    } else {
        // For new items (not in inventory yet), use the item_code as item_name
        $item_name = $item_code;
    }
    $item_stmt->close();

    // Parse order date
    $order_date_obj = DateTime::createFromFormat('Y-m-d', $order_date);
    if (!$order_date_obj) {
        throw new Exception("Invalid order date format");
    }
    
    $order_month = $order_date_obj->format('F');
    $order_day = intval($order_date_obj->format('j'));
    $order_year = intval($order_date_obj->format('Y'));
    $now = date('Y-m-d H:i:s');

    // If status is Delivered, add directly to Inventory instead of Orders
    if ($status === 'Delivered') {
        $company_name = 'Stock Addition';
        $grouping = identifyGrouping($item_name);
        $uom = 'UNITS';
        $insert_status = 'Received';
        
        // Check if item already exists in inventory
        $check_sql = "SELECT id FROM delivery_records WHERE item_code = ? AND company_name = 'Stock Addition' LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $item_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $item_exists = $check_result->num_rows > 0;
        $check_stmt->close();
        
        if ($item_exists) {
            // Update existing inventory
            $update_sql = "UPDATE delivery_records SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE item_code = ? AND company_name = 'Stock Addition'";
            $upd_stmt = $conn->prepare($update_sql);
            $upd_stmt->bind_param("is", $quantity, $item_code);
            $upd_stmt->execute();
            $upd_stmt->close();
        } else {
            // Create new inventory item
            $insert_sql = "INSERT INTO delivery_records (delivery_month, delivery_day, delivery_year, item_code, item_name, quantity, company_name, status, groupings, uom, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("siississsss", $order_month, $order_day, $order_year, $item_code, $item_name, $quantity, $company_name, $insert_status, $grouping, $uom, $now, $now);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Order added directly to inventory!', 'placed_in' => 'inventory']);
    } else {
        // Status is not Delivered, so add to Orders table
        $company_name = 'Orders';
        $insert_sql = "INSERT INTO delivery_records 
                       (delivery_month, delivery_day, delivery_year, item_code, item_name, quantity, 
                        company_name, status, notes, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("siisssissss", 
            $order_month,
            $order_day,
            $order_year,
            $item_code,
            $item_name,
            $quantity,
            $company_name,
            $status,
            $notes,
            $now,
            $now
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Execute failed: " . $insert_stmt->error);
        }
        
        $order_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        // If expected delivery date is provided, store it in notes
        if (!empty($expected_delivery)) {
            $exp_date_obj = DateTime::createFromFormat('Y-m-d', $expected_delivery);
            if ($exp_date_obj) {
                $notesSuffix = '\n[Expected Delivery: ' . $expected_delivery . ']';
                $update_sql = "UPDATE delivery_records SET notes = notes || ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $notesSuffix, $order_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Order added successfully', 'order_id' => $order_id, 'placed_in' => 'orders']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
