<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$itemsJson = isset($_POST['items']) ? $_POST['items'] : '[]';
$items = json_decode($itemsJson, true);

if (!is_array($items) || empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid items data']);
    exit;
}

// Get current date info
$currentDate = new DateTime();
$month = $currentDate->format('F');
$day = $currentDate->format('d');
$year = $currentDate->format('Y');

$addedCount = 0;
$errors = [];

foreach ($items as $item) {
    $itemCode = isset($item['item_code']) ? trim($item['item_code']) : '';
    $itemName = isset($item['item_name']) ? trim($item['item_name']) : '';
    $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;

    if (!$itemCode || !$itemName || $quantity <= 0) {
        $errors[] = "Invalid data for {$itemCode}";
        continue;
    }

    $itemCodeEscaped = $conn->real_escape_string($itemCode);
    $itemNameEscaped = $conn->real_escape_string($itemName);

    // Check if stock addition for this item already exists today
    $checkSql = "SELECT id, quantity FROM delivery_records 
                 WHERE item_code = '{$itemCodeEscaped}' 
                 AND company_name = 'Stock Addition' 
                 AND delivery_year = {$year}
                 AND delivery_month = '{$month}'
                 AND delivery_day = {$day}
                 LIMIT 1";

    $checkResult = $conn->query($checkSql);

    if ($checkResult && $checkResult->num_rows > 0) {
        // UPDATE existing record
        $existingRecord = $checkResult->fetch_assoc();
        $existingQty = intval($existingRecord['quantity']);
        $newQuantity = $existingQty + $quantity;
        $recordId = $existingRecord['id'];
        
        $updateSql = "UPDATE delivery_records 
                      SET quantity = {$newQuantity}, notes = 'Initial stock from inventory system'
                      WHERE id = {$recordId}";
        
        if ($conn->query($updateSql)) {
            $addedCount++;
        } else {
            $errors[] = "Failed to update {$itemCode}: " . $conn->error;
        }
    } else {
        // Create new record
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
            'Initial stock from inventory system'
        )";

        if ($conn->query($insertSql)) {
            $addedCount++;
        } else {
            $errors[] = "Failed to add {$itemCode}: " . $conn->error;
        }
    }
}

if ($addedCount > 0) {
    echo json_encode([
        'success' => true,
        'count' => $addedCount,
        'errors' => $errors
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'No items were added',
        'details' => $errors
    ]);
}
?>
