<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit;
}

header('Content-Type: application/json');
require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'success' => false]);
    exit;
}

// Handle both JSON and form-data requests
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    // JSON request
    $data = json_decode(file_get_contents('php://input'), true);
    $itemCode = $data['item_code'] ?? '';
    $itemName = $data['item_name'] ?? '';
    $quantity = intval($data['quantity'] ?? 0);
    $notes = $data['notes'] ?? '';
} else {
    // Form-data request
    $itemCode = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
    $itemName = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
}

// Validate inputs
if (!$itemCode || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item code or quantity', 'success' => false]);
    exit;
}

// Escape inputs
$itemCodeEscaped = $conn->real_escape_string($itemCode);
$itemNameEscaped = $itemName ? $conn->real_escape_string($itemName) : $itemCodeEscaped;
$notesEscaped = $conn->real_escape_string($notes ?: 'Stock addition');

try {
    // Get current date info
    $currentDate = new DateTime();
    $month = $currentDate->format('F'); // January, February, etc.
    $day = $currentDate->format('d'); // 01-31
    $year = $currentDate->format('Y'); // 2026
    
    // Check if this item already has a "Stock Addition" record for today
    $checkSql = "SELECT id, quantity FROM delivery_records 
                 WHERE item_code = '{$itemCodeEscaped}' 
                 AND company_name = 'Stock Addition' 
                 AND delivery_year = {$year}
                 AND delivery_month = '{$month}'
                 AND delivery_day = {$day}
                 LIMIT 1";
    
    $checkResult = $conn->query($checkSql);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        // UPDATE existing record instead of creating duplicate
        $existingRecord = $checkResult->fetch_assoc();
        $existingQty = intval($existingRecord['quantity']);
        $newQuantity = $existingQty + $quantity;
        $recordId = $existingRecord['id'];
        
        $updateSql = "UPDATE delivery_records 
                      SET quantity = {$newQuantity}, notes = '{$notesEscaped}', updated_at = CURRENT_TIMESTAMP
                      WHERE id = {$recordId}";
        
        if ($conn->query($updateSql)) {
            echo json_encode([
                'success' => true,
                'message' => "Stock updated! ({$existingQty} → {$newQuantity} units)",
                'item_code' => $itemCode,
                'item_name' => $itemNameEscaped,
                'quantity' => $newQuantity,
                'action' => 'updated'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update stock: ' . $conn->error]);
        }
    } else {
        // Create new Stock Addition record
        $insertSql = "INSERT INTO delivery_records (
            delivery_month, 
            delivery_day, 
            delivery_year, 
            item_code, 
            item_name, 
            company_name, 
            quantity, 
            status, 
            notes
        ) VALUES (
            '{$month}',
            {$day},
            {$year},
            '{$itemCodeEscaped}',
            '{$itemNameEscaped}',
            'Stock Addition',
            {$quantity},
            'Delivered',
            '{$notesEscaped}'
        )";
        
        if ($conn->query($insertSql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Stock added successfully',
                'item_code' => $itemCode,
                'item_name' => $itemNameEscaped,
                'quantity' => $quantity,
                'action' => 'created'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to add stock: ' . $conn->error]);
        }
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

