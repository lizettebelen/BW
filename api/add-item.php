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

// Get POST data with new field names
$box_code = isset($_POST['box_code']) ? trim($_POST['box_code']) : '';
$items = isset($_POST['items']) ? trim($_POST['items']) : '';
$item_description = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
$oum = isset($_POST['oum']) ? trim($_POST['oum']) : '';
$inventory_qty = isset($_POST['inventory_qty']) ? intval($_POST['inventory_qty']) : 0;
// Items added manually go to "Stock Addition" company to appear in inventory
$company_name = 'Stock Addition';
$dataset = $_SESSION['dataset'] ?? 'default';

// Validate inputs
if (empty($box_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Box is required']);
    exit();
}

if (empty($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Items is required']);
    exit();
}

if (empty($oum)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'OUM (Unit of Measure) is required']);
    exit();
}

if ($inventory_qty < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Inventory quantity cannot be negative']);
    exit();
}

try {
    // Check if box_code already exists
    $check_sql = "SELECT box_code FROM delivery_records WHERE box_code = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $box_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Box "' . htmlspecialchars($box_code) . '" already exists']);
        exit();
    }
    
    $check_stmt->close();

    // Insert new item record
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $delivery_month = date('F'); // e.g., January
    $delivery_day = date('j');   // e.g., 13
    $delivery_year = date('Y');  // e.g., 2026
    $status = 'Pending';
    
    $insert_sql = "INSERT INTO delivery_records 
                   (delivery_month, delivery_day, delivery_year, box_code, model_no, item_code, item_name, description, uom, quantity, company_name, status, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters: delivery_month(s), delivery_day(i), delivery_year(i), box_code(s), model_no(s), item_code(s), item_name(s), description(s), uom(s), quantity(i), company_name(s), status(s), created_at(s), updated_at(s)
    $insert_stmt->bind_param("siissssssissss", $delivery_month, $delivery_day, $delivery_year, $box_code, $items, $box_code, $items, $item_description, $oum, $inventory_qty, $company_name, $status, $now, $now);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Execute failed: " . $insert_stmt->error);
    }
    
    $insert_stmt->close();

    // Log the action
    error_log("[" . date('Y-m-d H:i:s') . "] New item created: Box={$box_code}, Items={$items}, OUM={$oum}, Inventory={$inventory_qty} by user {$_SESSION['user_id']}");

    echo json_encode([
        'success' => true,
        'message' => 'Item created successfully',
        'box_code' => $box_code,
        'items' => $items,
        'item_description' => $item_description,
        'oum' => $oum,
        'inventory_qty' => $inventory_qty
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in add-item.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error creating item: ' . $e->getMessage()]);
}

$conn->close();
?>
