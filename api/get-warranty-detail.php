<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../db_config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid warranty ID']);
    exit;
}

try {
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare('SELECT * FROM warranty_replacements WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('Failed to prepare query');
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    } else {
        $stmt = $conn->prepare('SELECT * FROM warranty_replacements WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Warranty record not found']);
        exit;
    }

    echo json_encode(['success' => true, 'warranty' => $row]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
