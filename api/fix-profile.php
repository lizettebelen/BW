<?php
require_once __DIR__ . '/../db_config.php';

$email = 'lizuu131@gmail.com';
$newName = 'Lizette Macalindol';

// Update user name
$stmt = $conn->prepare('UPDATE users SET name = ? WHERE email = ?');
if (!$stmt) {
    die('Error: ' . $conn->error);
}

$stmt->bind_param('ss', $newName, $email);
$result = $stmt->execute();
$stmt->close();

if ($result) {
    echo "✓ Profile updated successfully!<br>";
    echo "Name: Lizette Macalindol<br>";
    echo "Email: lizuu131@gmail.com<br>";
    echo "<br>Please log out and log back in to see the changes.";
} else {
    echo "Error updating profile: " . $conn->error;
}
?>
