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

$user_id = $_SESSION['user_id'];

// Get profile picture from database
$stmt = $conn->prepare('SELECT profile_picture FROM users WHERE id = ?');
if (!$stmt) {
    error_log('DB prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (!empty($row['profile_picture'])) {
        echo json_encode([
            'success' => true,
            'picture_url' => $row['profile_picture']
        ]);
        exit;
    }
}

echo json_encode([
    'success' => false,
    'message' => 'No profile picture set'
]);
