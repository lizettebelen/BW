<?php
/**
 * Test Email Sending
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/api/email-config.php';

echo "<h2>SMTP Configuration Test</h2>";
echo "SMTP Host: " . SMTP_HOST . "<br>";
echo "SMTP Port: " . SMTP_PORT . "<br>";
echo "SMTP User: " . SMTP_USER . "<br>";
echo "SMTP Pass: " . (SMTP_PASS ? str_repeat('*', strlen(SMTP_PASS)) : 'NOT SET') . "<br>";
echo "<hr>";

// Test email with detailed debugging
$to = SMTP_USER;
$subject = "🔐 Test Security Alert - BW Dashboard";
$body = "
<html>
<body style='font-family: Arial, sans-serif; background: #1a1a2e; color: #fff; padding: 20px;'>
<div style='max-width: 500px; margin: 0 auto; background: #16213e; padding: 30px; border-radius: 10px;'>
<h2 style='color: #f4d03f;'>🔐 Security Alert Test</h2>
<p>This is a test email from BW Dashboard.</p>
<p>If you received this, email sending is working!</p>
<p style='color: #888; font-size: 12px;'>Sent at: " . date('Y-m-d H:i:s') . "</p>
</div>
</body>
</html>";

echo "<h3>Testing SMTP Connection...</h3>";
echo "<pre style='background: #222; color: #0f0; padding: 15px; font-family: monospace;'>";

// Detailed SMTP test
$host = SMTP_HOST;
$port = SMTP_PORT;
$username = SMTP_USER;
$password = SMTP_PASS;

echo "Connecting to ssl://$host:$port...\n";

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

$socket = @stream_socket_client(
    "ssl://$host:$port",
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT,
    $context
);

if (!$socket) {
    echo "❌ Connection FAILED: $errstr ($errno)\n";
    echo "</pre>";
    exit;
}

echo "✅ Connected!\n\n";

// Read greeting
$response = '';
while ($line = @fgets($socket, 515)) {
    $response .= $line;
    echo "S: $line";
    if (isset($line[3]) && $line[3] == ' ') break;
}

// EHLO
echo "\nC: EHLO localhost\n";
fputs($socket, "EHLO localhost\r\n");
$response = '';
while ($line = @fgets($socket, 515)) {
    echo "S: $line";
    if (isset($line[3]) && $line[3] == ' ') break;
}

// AUTH LOGIN
echo "\nC: AUTH LOGIN\n";
fputs($socket, "AUTH LOGIN\r\n");
$response = @fgets($socket, 515);
echo "S: $response";

// Username
echo "C: [username base64]\n";
fputs($socket, base64_encode($username) . "\r\n");
$response = @fgets($socket, 515);
echo "S: $response";

// Password
echo "C: [password base64]\n";
fputs($socket, base64_encode($password) . "\r\n");
$response = @fgets($socket, 515);
echo "S: $response";

if (substr($response, 0, 3) == '235') {
    echo "\n✅ Authentication SUCCESS!\n";
    
    // MAIL FROM
    echo "\nC: MAIL FROM:<$username>\n";
    fputs($socket, "MAIL FROM:<$username>\r\n");
    $response = @fgets($socket, 515);
    echo "S: $response";
    
    // RCPT TO
    echo "C: RCPT TO:<$to>\n";
    fputs($socket, "RCPT TO:<$to>\r\n");
    $response = @fgets($socket, 515);
    echo "S: $response";
    
    // DATA
    echo "C: DATA\n";
    fputs($socket, "DATA\r\n");
    $response = @fgets($socket, 515);
    echo "S: $response";
    
    // Send message
    $message = "From: BW Dashboard <$username>\r\n";
    $message .= "To: $to\r\n";
    $message .= "Subject: $subject\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "\r\n";
    $message .= $body;
    
    echo "C: [sending message...]\n";
    fputs($socket, $message . "\r\n.\r\n");
    $response = @fgets($socket, 515);
    echo "S: $response";
    
    if (substr($response, 0, 3) == '250') {
        echo "\n✅ EMAIL SENT SUCCESSFULLY!\n";
        echo "\n📧 Check your inbox at: $to\n";
    } else {
        echo "\n❌ Send failed!\n";
    }
} else {
    echo "\n❌ Authentication FAILED!\n";
    echo "Check your App Password - it should be 16 characters without spaces.\n";
}

fputs($socket, "QUIT\r\n");
fclose($socket);

echo "</pre>";
?>
