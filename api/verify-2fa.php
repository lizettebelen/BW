<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/totp.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if pending 2FA verification exists
if (empty($_SESSION['pending_2fa_user_id']) || empty($_SESSION['pending_2fa_secret'])) {
    echo json_encode(['success' => false, 'message' => 'No pending 2FA verification. Please login again.']);
    exit;
}

$code = trim($_POST['code'] ?? '');

if (!$code || strlen($code) !== 6 || !ctype_digit($code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit code']);
    exit;
}

$secret = $_SESSION['pending_2fa_secret'];

if (!TOTP::verifyCode($secret, $code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
    exit;
}

// ---- Success: create session ----
session_regenerate_id(true);

$_SESSION['user_id'] = $_SESSION['pending_2fa_user_id'];
$_SESSION['user_email'] = $_SESSION['pending_2fa_user_email'];
$_SESSION['user_name'] = $_SESSION['pending_2fa_user_name'];

// Clean up pending 2FA data
unset(
    $_SESSION['pending_2fa_user_id'],
    $_SESSION['pending_2fa_user_email'],
    $_SESSION['pending_2fa_user_name'],
    $_SESSION['pending_2fa_secret'],
    $_SESSION['login_attempts'],
    $_SESSION['login_first_attempt']
);

echo json_encode([
    'success' => true,
    'message' => 'Verification successful',
    'redirect' => 'profile.php'
]);
