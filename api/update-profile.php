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

if (!empty($_FILES['picture'])) {
    // Handle profile picture upload
    $file = $_FILES['picture'];
    $user_id = $_SESSION['user_id'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
        exit;
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
        exit;
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid image format']);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $uploads_dir = __DIR__ . '/../uploads/profile-pictures';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    $filepath = $uploads_dir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }
    
    // Update database
    $picture_url = 'uploads/profile-pictures/' . $filename;
    $stmt = $conn->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
    if (!$stmt) {
        error_log('DB prepare failed: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param('si', $picture_url, $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'picture_url' => $picture_url
    ]);
    exit;
}

// Handle name update
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

