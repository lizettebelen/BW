<?php
/**
 * Email Configuration and Helper Functions
 * Uses SMTP to send real emails
 */

// ============================================
// EMAIL CONFIGURATION - UPDATE THESE SETTINGS
// ============================================
define('SMTP_HOST', 'smtp.gmail.com');           // Gmail SMTP server
define('SMTP_PORT', 465);                         // SSL port (more reliable)
define('SMTP_USER', 'lizettemacalindol.official@gmail.com');  // Your Gmail address
define('SMTP_PASS', 'wprgsvaawvpvlcup');          // Your Gmail App Password
define('SMTP_FROM_EMAIL', 'lizettemacalindol.official@gmail.com');
define('SMTP_FROM_NAME', 'BW Dashboard Security');

// ============================================
// HOW TO GET GMAIL APP PASSWORD:
// 1. Go to https://myaccount.google.com/security
// 2. Enable 2-Step Verification if not already enabled
// 3. Go to App passwords (https://myaccount.google.com/apppasswords)
// 4. Select "Mail" and "Windows Computer"
// 5. Click Generate - copy the 16-character password
// 6. Paste it in SMTP_PASS above
// ============================================

/**
 * Send email using SMTP
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    // Check if SMTP is configured
    if (empty(SMTP_USER) || empty(SMTP_PASS)) {
        error_log('Email not sent: SMTP credentials not configured in api/email-config.php');
        return false;
    }
    
    return sendWithSMTP($to, $subject, $htmlBody);
}

/**
 * Read SMTP response
 */
function smtpGetResponse($socket) {
    $response = '';
    while ($line = @fgets($socket, 515)) {
        $response .= $line;
        // Check if this is the last line (4th char is space)
        if (isset($line[3]) && $line[3] == ' ') break;
    }
    return $response;
}

/**
 * Send SMTP command and check response
 */
function smtpCommand($socket, $command, $expectedCode) {
    fputs($socket, $command . "\r\n");
    $response = smtpGetResponse($socket);
    $code = substr($response, 0, 3);
    if ($code != $expectedCode) {
        error_log("SMTP Error - Expected $expectedCode, got: $response");
        return false;
    }
    return true;
}

/**
 * Send email using direct SMTP connection with SSL
 */
function sendWithSMTP($to, $subject, $htmlBody) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $from = SMTP_FROM_EMAIL ?: SMTP_USER;
    $fromName = SMTP_FROM_NAME;
    
    // Create SSL context for secure connection
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    // Connect using SSL directly (port 465)
    $socket = @stream_socket_client(
        "ssl://$host:$port",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );
    
    if (!$socket) {
        error_log("SMTP SSL Connection failed: $errstr ($errno)");
        return false;
    }
    
    // Set timeout
    stream_set_timeout($socket, 30);
    
    // Read greeting
    $response = smtpGetResponse($socket);
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        error_log("SMTP Greeting Error: $response");
        return false;
    }
    
    // EHLO
    if (!smtpCommand($socket, "EHLO localhost", '250')) {
        fclose($socket);
        return false;
    }
    
    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = smtpGetResponse($socket);
    if (substr($response, 0, 3) != '334') {
        fclose($socket);
        error_log("SMTP AUTH failed: $response");
        return false;
    }
    
    // Send username (base64 encoded)
    fputs($socket, base64_encode($username) . "\r\n");
    $response = smtpGetResponse($socket);
    if (substr($response, 0, 3) != '334') {
        fclose($socket);
        error_log("SMTP username failed: $response");
        return false;
    }
    
    // Send password (base64 encoded)
    fputs($socket, base64_encode($password) . "\r\n");
    $response = smtpGetResponse($socket);
    if (substr($response, 0, 3) != '235') {
        fclose($socket);
        error_log("SMTP authentication failed: $response");
        return false;
    }
    
    // MAIL FROM
    if (!smtpCommand($socket, "MAIL FROM:<$from>", '250')) {
        fclose($socket);
        return false;
    }
    
    // RCPT TO
    if (!smtpCommand($socket, "RCPT TO:<$to>", '250')) {
        fclose($socket);
        return false;
    }
    
    // DATA
    fputs($socket, "DATA\r\n");
    $response = smtpGetResponse($socket);
    if (substr($response, 0, 3) != '354') {
        fclose($socket);
        error_log("SMTP DATA failed: $response");
        return false;
    }
    
    // Build message with headers
    $message = "From: $fromName <$from>\r\n";
    $message .= "To: $to\r\n";
    $message .= "Subject: $subject\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Date: " . date('r') . "\r\n";
    $message .= "\r\n";
    $message .= $htmlBody;
    
    // Escape dots at start of lines (SMTP transparency)
    $message = str_replace("\r\n.", "\r\n..", $message);
    
    // Send message and end with CRLF.CRLF
    fputs($socket, $message . "\r\n.\r\n");
    
    $response = smtpGetResponse($socket);
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        error_log("SMTP send failed: $response");
        return false;
    }
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    error_log("Email sent successfully to: $to");
    return true;
}

/**
 * Generate security alert email HTML
 */
function generateSecurityAlertEmail($userName, $ipAddress, $userAgent, $timestamp, $verifyToken, $baseUrl) {
    $browser = getBrowserName($userAgent);
    $dateTime = date('F j, Y \a\t g:i A', strtotime($timestamp));
    
    $yesUrl = $baseUrl . '/verify-login.php?action=confirm&token=' . $verifyToken;
    $noUrl = $baseUrl . '/verify-login.php?action=deny&token=' . $verifyToken;
    $changePasswordUrl = $baseUrl . '/profile.php?change_password=1';
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f4; padding: 20px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #1e2a38; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #e74c3c, #c0392b); padding: 30px; text-align: center;">
                                <div style="font-size: 48px; margin-bottom: 10px;">⚠️</div>
                                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Security Alert</h1>
                                <p style="color: rgba(255,255,255,0.8); margin: 10px 0 0 0; font-size: 14px;">Failed login attempt detected</p>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 30px;">
                                <p style="color: #e0e0e0; font-size: 16px; margin: 0 0 20px 0;">
                                    Hi <strong style="color: #f4d03f;">' . htmlspecialchars($userName) . '</strong>,
                                </p>
                                
                                <p style="color: #a0a0a0; font-size: 14px; margin: 0 0 25px 0;">
                                    Someone tried to access your BW Dashboard account with an incorrect password. If this was you, you can ignore this message. If not, we recommend changing your password immediately.
                                </p>
                                
                                <!-- Details Box -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 25px;">
                                    <tr>
                                        <td style="padding: 20px;">
                                            <h3 style="color: #fff; margin: 0 0 15px 0; font-size: 14px;">Login Attempt Details:</h3>
                                            <table role="presentation" cellspacing="0" cellpadding="5">
                                                <tr>
                                                    <td style="color: #a0a0a0; font-size: 13px; padding-right: 15px;">📅 Date & Time:</td>
                                                    <td style="color: #e0e0e0; font-size: 13px;">' . $dateTime . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #a0a0a0; font-size: 13px; padding-right: 15px;">🌐 IP Address:</td>
                                                    <td style="color: #e0e0e0; font-size: 13px;">' . htmlspecialchars($ipAddress) . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #a0a0a0; font-size: 13px; padding-right: 15px;">💻 Browser:</td>
                                                    <td style="color: #e0e0e0; font-size: 13px;">' . htmlspecialchars($browser) . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p style="color: #e0e0e0; font-size: 14px; margin: 0 0 20px 0; text-align: center;">
                                    <strong>Was this you?</strong>
                                </p>
                                
                                <!-- Action Buttons -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td align="center" style="padding-bottom: 15px;">
                                            <a href="' . $yesUrl . '" style="display: inline-block; background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; text-decoration: none; padding: 14px 40px; border-radius: 8px; font-weight: bold; font-size: 14px;">✓ Yes, it was me</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="center" style="padding-bottom: 15px;">
                                            <a href="' . $noUrl . '" style="display: inline-block; background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; text-decoration: none; padding: 14px 40px; border-radius: 8px; font-weight: bold; font-size: 14px;">✗ No, secure my account</a>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Password Change Suggestion -->
                                <div style="background-color: rgba(244, 208, 63, 0.1); border: 1px solid rgba(244, 208, 63, 0.3); border-radius: 8px; padding: 15px; margin-top: 20px;">
                                    <p style="color: #f4d03f; font-size: 13px; margin: 0;">
                                        💡 <strong>Tip:</strong> If you suspect unauthorized access, we recommend <a href="' . $changePasswordUrl . '" style="color: #5bbcff;">changing your password</a> immediately.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: rgba(0,0,0,0.2); padding: 20px; text-align: center;">
                                <p style="color: #a0a0a0; font-size: 12px; margin: 0;">
                                    This is an automated security alert from BW Dashboard.<br>
                                    © ' . date('Y') . ' Andison Industrial
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

/**
 * Get browser name from user agent
 */
function getBrowserName($userAgent) {
    if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
    if (strpos($userAgent, 'Edg') !== false) return 'Microsoft Edge';
    if (strpos($userAgent, 'Chrome') !== false) return 'Google Chrome';
    if (strpos($userAgent, 'Safari') !== false) return 'Safari';
    if (strpos($userAgent, 'Opera') !== false || strpos($userAgent, 'OPR') !== false) return 'Opera';
    if (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) return 'Internet Explorer';
    return 'Unknown Browser';
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (X-Forwarded-For)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return $ip;
        }
    }
    
    return 'Unknown';
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Get base URL of the application
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = rtrim($path, '/api');
    return $protocol . '://' . $host . $path;
}
