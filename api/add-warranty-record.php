<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

require_once __DIR__ . '/../db_config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload'
    ]);
    exit;
}

$item_code = trim((string)($data['item_code'] ?? ''));
$item_name = trim((string)($data['item_name'] ?? ''));
$serial_no = trim((string)($data['serial_no'] ?? ''));
$company_name = trim((string)($data['company_name'] ?? ''));
$quantity = intval($data['quantity'] ?? 0);
$uom = trim((string)($data['uom'] ?? ''));
$status = trim((string)($data['status'] ?? 'Warranty Pending'));
$notes = trim((string)($data['notes'] ?? ''));
$red_text_detected = intval($data['red_text_detected'] ?? 1) === 1 ? 1 : 0;
$warranty_date = trim((string)($data['warranty_date'] ?? ''));

$allowed_statuses = ['Warranty Pending', 'Approved', 'Replaced', 'Cancelled'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'Warranty Pending';
}

if ($item_code === '' || $item_name === '' || $company_name === '' || $quantity <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

$warranty_date_sql = null;
if ($warranty_date !== '') {
    $ts = strtotime($warranty_date);
    if ($ts === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid warranty date'
        ]);
        exit;
    }
    $warranty_date_sql = date('Y-m-d', $ts);
}

try {
    $sql = "INSERT INTO warranty_replacements
            (item_code, item_name, serial_no, company_name, quantity, uom, status, warranty_date, notes, warranty_flag, red_text_detected, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare insert statement');
    }

    $stmt->bind_param(
        'ssssissssi',
        $item_code,
        $item_name,
        $serial_no,
        $company_name,
        $quantity,
        $uom,
        $status,
        $warranty_date_sql,
        $notes,
        $red_text_detected
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to insert warranty record: ' . ($stmt->error ?? 'Unknown error'));
    }

    $new_id = intval($conn->insert_id ?? 0);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Warranty record added successfully',
        'id' => $new_id
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
