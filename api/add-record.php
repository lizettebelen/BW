<?php
header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/../db_config.php';

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

// No required fields - just need at least some data

try {
    // Extract and sanitize data
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 0;
    $unit_price = isset($data['unit_price']) ? floatval($data['unit_price']) : 0;
    $uom = trim($data['uom'] ?? '');
    $serial_no = trim($data['serial_no'] ?? '');
    $company_name = trim($data['company_name'] ?? '');
    $delivery_date = !empty($data['delivery_date']) ? $data['delivery_date'] : null;
    $sold_to_month = trim($data['sold_to_month'] ?? '');
    $sold_to_day = !empty($data['sold_to_day']) ? intval($data['sold_to_day']) : null;
    $notes = trim($data['notes'] ?? '');
    $groupings = trim($data['groupings'] ?? '');
    $dataset_name = trim($data['dataset_name'] ?? '');
    
    // Main fields from form
    $invoice_no = trim($data['invoice_no'] ?? '');
    $item_code = trim($data['item_code'] ?? '');
    $item_name = trim($data['item_name'] ?? '');
    $status = trim($data['status'] ?? 'Delivered');
    
    // Direct input for delivery month, day, year from form
    $delivery_month = trim($data['delivery_month'] ?? '');
    $delivery_day = !empty($data['delivery_day']) ? intval($data['delivery_day']) : 0;
    $delivery_year = !empty($data['year']) ? intval($data['year']) : 0;
    
    // If date field is provided, parse it to get delivery_date
    if (!empty($data['date'])) {
        $delivery_date = $data['date'];
    }
    
    // If delivery_date is provided but month/day not set, extract from date
    if ($delivery_date && (empty($delivery_month) || $delivery_day == 0)) {
        $timestamp = strtotime($delivery_date);
        if (empty($delivery_month)) $delivery_month = date('F', $timestamp);
        if ($delivery_day == 0) $delivery_day = intval(date('j', $timestamp));
        if ($delivery_year == intval(date('Y'))) $delivery_year = intval(date('Y', $timestamp));
    }
    
    // Build delivery_date if we have month, day, year but no date
    if (empty($delivery_date) && !empty($delivery_month) && $delivery_day > 0 && $delivery_year > 0) {
        $month_num = date('n', strtotime($delivery_month . ' 1'));
        if ($month_num) {
            $delivery_date = sprintf('%04d-%02d-%02d', $delivery_year, $month_num, $delivery_day);
        }
    }
    
    // Insert into database
    $sql = "INSERT INTO delivery_records 
            (invoice_no, serial_no, delivery_month, delivery_day, delivery_year, delivery_date, item_code, item_name, company_name, quantity, unit_price, status, notes, uom, sold_to_month, sold_to_day, groupings, dataset_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . ($conn->error ?? 'Unknown error'));
    }

    $stmt->bind_param(
        'sssiissssidssssiss',
        $invoice_no,
        $serial_no,
        $delivery_month,
        $delivery_day,
        $delivery_year,
        $delivery_date,
        $item_code,
        $item_name,
        $company_name,
        $quantity,
        $unit_price,
        $status,
        $notes,
        $uom,
        $sold_to_month,
        $sold_to_day,
        $groupings,
        $dataset_name
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $new_id = $conn->insert_id ?? $stmt->insert_id ?? 0;
    $stmt->close();

    // Fetch the newly created record
    $newRecord = null;
    if ($new_id) {
        $fetchSql = "SELECT * FROM delivery_records WHERE id = ?";
        $fetchStmt = $conn->prepare($fetchSql);
        if ($fetchStmt) {
            $fetchStmt->bind_param('i', $new_id);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            if ($result && $result->num_rows > 0) {
                $newRecord = $result->fetch_assoc();
            }
            $fetchStmt->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Record added successfully',
        'id' => $new_id,
        'record' => $newRecord
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
