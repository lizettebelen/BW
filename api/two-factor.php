<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/totp.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? '';

// Ensure 2FA columns exist in users table
if ($conn instanceof mysqli) {
    @$conn->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(32) DEFAULT NULL");
    @$conn->query("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
} else {
    @$conn->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(32) DEFAULT NULL");
    @$conn->query("ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER DEFAULT 0");
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'setup':
        // Generate new secret for setup
        $secret = TOTP::generateSecret();
        $qrCodeUrl = TOTP::getQRCodeUrl($secret, $userEmail, 'BW Dashboard');
        
        // Store secret temporarily in session until verified
        $_SESSION['pending_2fa_secret'] = $secret;
        
        echo json_encode([
            'success' => true,
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
            'manualEntry' => chunk_split($secret, 4, ' ')
        ]);
        break;
        
    case 'enable':
        // Verify code and enable 2FA
        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['pending_2fa_secret'] ?? '';
        
        if (!$secret) {
            echo json_encode(['success' => false, 'message' => 'Please start 2FA setup first']);
            exit;
        }
        
        if (!$code || strlen($code) !== 6) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit code']);
            exit;
        }
        
        if (!TOTP::verifyCode($secret, $code)) {
            echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
            exit;
        }
        
        // Save secret to database and enable 2FA
        $stmt = $conn->prepare('UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?');
        $stmt->bind_param('si', $secret, $userId);
        
        if ($stmt->execute()) {
            unset($_SESSION['pending_2fa_secret']);
            echo json_encode(['success' => true, 'message' => 'Two-Factor Authentication enabled successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to enable 2FA']);
        }
        $stmt->close();
        break;
        
    case 'disable':
        // Verify password before disabling
        $password = $_POST['password'] ?? '';
        
        if (!$password) {
            echo json_encode(['success' => false, 'message' => 'Password is required to disable 2FA']);
            exit;
        }
        
        // Verify password
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        $verified = password_verify($password, $user['password']) || $password === $user['password'];
        
        if (!$verified) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
            exit;
        }
        
        // Disable 2FA
        $stmt = $conn->prepare('UPDATE users SET two_factor_secret = NULL, two_factor_enabled = 0 WHERE id = ?');
        $stmt->bind_param('i', $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Two-Factor Authentication disabled']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to disable 2FA']);
        }
        $stmt->close();
        break;
        
    case 'status':
        // Check if 2FA is enabled for current user
        $stmt = $conn->prepare('SELECT two_factor_enabled FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'enabled' => (bool)($row['two_factor_enabled'] ?? false)
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
