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
$warranty_id = intval($data['id'] ?? 0);

if ($warranty_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid warranty record ID'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare('DELETE FROM warranty_replacements WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Failed to prepare delete query');
    }

    $stmt->bind_param('i', $warranty_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete warranty record');
    }

    if ($stmt->affected_rows <= 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Warranty record not found'
        ]);
        exit;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Warranty record deleted successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete warranty record right now. Please try again.'
    ]);
}
