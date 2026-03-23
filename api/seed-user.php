<?php
require_once __DIR__ . '/../db_config.php';

// Ensure users table exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$name     = 'Admin';
$email    = 'admin@andison.com';
$password = password_hash('Admin@1234', PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password)');
$stmt->bind_param('sss', $name, $email, $password);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Test user created.', 'email' => $email, 'password' => 'Admin@1234']);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>
