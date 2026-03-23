<?php
// Buffer all output so PHP warnings/notices never corrupt the JSON response
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');

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

// ---- Rate limiting (max 10 attempts per 15 min per session) ----
$_SESSION['signup_attempts']      = $_SESSION['signup_attempts']      ?? 0;
$_SESSION['signup_first_attempt'] = $_SESSION['signup_first_attempt'] ?? time();

$windowSeconds = 15 * 60;
$maxAttempts   = 10;

if (time() - $_SESSION['signup_first_attempt'] > $windowSeconds) {
    $_SESSION['signup_attempts']      = 0;
    $_SESSION['signup_first_attempt'] = time();
}

if ($_SESSION['signup_attempts'] >= $maxAttempts) {
    $waitMins = max(1, (int) ceil(($windowSeconds - (time() - $_SESSION['signup_first_attempt'])) / 60));
    respond(['success' => false, 'message' => "Too many requests. Please wait {$waitMins} minute(s)."], 429);
}

// ---- Read & sanitise inputs ----
$firstName       = trim($_POST['firstName']       ?? '');
$lastName        = trim($_POST['lastName']        ?? '');
$email           = trim($_POST['email']           ?? '');
$password        =      $_POST['password']        ?? '';  // Do NOT trim password
$confirmPassword =      $_POST['confirmPassword'] ?? '';

// ---- Server-side validation ----
$errors = [];

if (strlen($firstName) < 2) $errors[] = 'First name must be at least 2 characters.';
if (strlen($lastName)  < 2) $errors[] = 'Last name must be at least 2 characters.';

if (!$email) {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
} else {
    if (!preg_match('/[A-Z]/', $password))      $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password))      $errors[] = 'Password must contain at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Password must contain at least one symbol.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    $_SESSION['signup_attempts']++;
    respond(['success' => false, 'message' => $errors[0]]);
}

// ---- Check for duplicate email ----
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    error_log('DB prepare failed: ' . $conn->error);
    respond(['success' => false, 'message' => 'Internal server error. Please try again.'], 500);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result && $result->num_rows > 0) {
    $_SESSION['signup_attempts']++;
    respond(['success' => false, 'message' => 'An account with that email already exists.']);
}

// ---- Hash password & insert user ----
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
$fullName       = $firstName . ' ' . $lastName;

$insert = $conn->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
if (!$insert) {
    error_log('DB prepare failed: ' . $conn->error);
    respond(['success' => false, 'message' => 'Internal server error. Please try again.'], 500);
}

$insert->bind_param('sss', $fullName, $email, $hashedPassword);

if (!$insert->execute()) {
    error_log('DB insert failed: ' . $insert->error);
    respond(['success' => false, 'message' => 'Could not create account. Please try again.'], 500);
}

$newUserId = (int) $insert->insert_id;
$insert->close();

// ---- Auto-login: create session ----
session_regenerate_id(true);
$_SESSION['user_id']    = $newUserId;
$_SESSION['user_email'] = $email;
$_SESSION['user_name']  = $fullName;

unset($_SESSION['signup_attempts'], $_SESSION['signup_first_attempt']);

respond([
    'success'  => true,
    'message'  => 'Account created successfully! Welcome aboard.',
    'redirect' => 'index.php'
]);
