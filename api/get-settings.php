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

// Get settings for this user
$stmt = $conn->prepare('SELECT settings FROM user_settings WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

// Default settings
$defaultSettings = [
    'dataAnalytics' => true,
    'sessionTimeout' => '15 minutes',
    'emailAlerts' => true,
    'sidebarBehavior' => 'remember',
    'accentColor' => 'gold',
    'fontSize' => 'medium',
    'compactMode' => false,
    'animations' => true
];

if ($row = $result->fetch_assoc()) {
    $savedSettings = json_decode($row['settings'], true);
    if ($savedSettings) {
        // Merge saved settings with defaults to ensure all keys exist
        $settings = array_merge($defaultSettings, $savedSettings);
    } else {
        $settings = $defaultSettings;
    }
} else {
    $settings = $defaultSettings;
}

echo json_encode([
    'success' => true,
    'settings' => $settings
]);

$stmt->close();
