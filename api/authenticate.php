<?php
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');

// Load DB config
require_once __DIR__ . '/../db_config.php';

/** Send clean JSON and exit — discards any buffered PHP warnings first. */
function respond(array $data, int $status = 200): never {
    http_response_code($status);
    ob_clean();
    echo json_encode($data);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

// ---- Brute-force rate limiting (max 10 attempts per 15 min per session) ----
$_SESSION['login_attempts']      = $_SESSION['login_attempts']      ?? 0;
$_SESSION['login_first_attempt'] = $_SESSION['login_first_attempt'] ?? time();

$windowSeconds = 15 * 60;
$maxAttempts   = 10;

if (time() - $_SESSION['login_first_attempt'] > $windowSeconds) {
    $_SESSION['login_attempts']      = 0;
    $_SESSION['login_first_attempt'] = time();
}

if ($_SESSION['login_attempts'] >= $maxAttempts) {
    $waitSec  = $windowSeconds - (time() - $_SESSION['login_first_attempt']);
    $waitMins = max(1, (int) ceil($waitSec / 60));
    respond(['success' => false, 'message' => "Too many login attempts. Please wait {$waitMins} minute(s) and try again."], 429);
}

$email    = trim($_POST['email']    ?? '');
$password =      $_POST['password'] ?? '';  // Do NOT trim — leading/trailing space may be intentional

if (!$email || !$password) {
    respond(['success' => false, 'message' => 'Email and password are required']);
}

// ---- Fetch user ----
$stmt = $conn->prepare('SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    error_log('DB prepare failed: ' . $conn->error);
    respond(['success' => false, 'message' => 'Internal server error'], 500);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if (!$result || $result->num_rows === 0) {
    $_SESSION['login_attempts']++;
    respond(['success' => false, 'message' => 'Invalid email or password']);
}

$user = $result->fetch_assoc();

// Support both bcrypt-hashed and plain-text passwords (legacy)
$verified = password_verify($password, $user['password'])
         || $password === $user['password'];

if (!$verified) {
    $_SESSION['login_attempts']++;
    respond(['success' => false, 'message' => 'Invalid email or password']);
}

// ---- Success: create session ----
session_regenerate_id(true);

unset($_SESSION['login_attempts'], $_SESSION['login_first_attempt']);

$_SESSION['user_id']    = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name']  = $user['name'] ?? $user['email'];

respond([
    'success'  => true,
    'message'  => 'Login successful',
    'redirect' => 'index.php'
]);
