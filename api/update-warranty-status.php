<?php
/**
 * API Endpoint: Update Warranty Status
 * Updates the status of warranty records
 */

ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$json = file_get_contents('php://input');
$request = json_decode($json, true);

if (!$request || !isset($request['id']) || !isset($request['status'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$warranty_id = intval($request['id']);
$new_status = trim($request['status']);

if (empty($new_status)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Status cannot be empty']);
    exit;
}

try {
    // Allowed statuses
    $allowed_statuses = ['Warranty Pending', 'Approved', 'Replaced', 'Cancelled'];
    
    if (!in_array($new_status, $allowed_statuses)) {
        throw new Exception('Invalid status: ' . htmlspecialchars($new_status));
    }

    // Update warranty record
    $update_sql = "UPDATE warranty_replacements SET status = ?, updated_at = NOW() WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('si', $new_status, $warranty_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update warranty status');
    }

    // Check if update was successful
    if ($stmt->affected_rows > 0) {
        ob_clean();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Warranty status updated successfully',
            'id' => $warranty_id,
            'new_status' => $new_status
        ]);
    } else {
        ob_clean();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Warranty record not found'
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit;
?>
