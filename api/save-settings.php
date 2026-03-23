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

// Get settings from request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['settings'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid settings data']);
    exit;
}

$settings = json_encode($data['settings']);
$userId = $_SESSION['user_id'];

// Ensure user_settings table exists (compatible with both MySQL and SQLite)
if ($conn instanceof mysqli) {
    $conn->query("CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        settings TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} else {
    $conn->query("CREATE TABLE IF NOT EXISTS user_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        settings TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Check if settings exist for this user
$checkStmt = $conn->prepare('SELECT id FROM user_settings WHERE user_id = ?');
$checkStmt->bind_param('i', $userId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Update existing settings
    $stmt = $conn->prepare('UPDATE user_settings SET settings = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
    $stmt->bind_param('si', $settings, $userId);
} else {
    // Insert new settings
    $stmt = $conn->prepare('INSERT INTO user_settings (user_id, settings) VALUES (?, ?)');
    $stmt->bind_param('is', $userId, $settings);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
}

$stmt->close();
$checkStmt->close();
