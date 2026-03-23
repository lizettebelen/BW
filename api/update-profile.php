<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_config.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');

if (!$name) {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

// Update user name in database
$stmt = $conn->prepare('UPDATE users SET name = ? WHERE id = ?');
if (!$stmt) {
    error_log('DB prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('si', $name, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

// Update session
$_SESSION['user_name'] = $name;

echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully',
    'name' => $name
]);

