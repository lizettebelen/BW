<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/email-config.php';

/**
 * Initialize login_attempts table
 */
function initLoginAttemptsTable($conn) {
    if ($conn instanceof mysqli) {
        $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_attempt_time (attempt_time)
        )");
    } else {
        $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

/**
 * Record a failed login attempt
 */
function recordFailedAttempt($conn, $email) {
    initLoginAttemptsTable($conn);
    
    $ipAddress = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $conn->prepare('INSERT INTO login_attempts (email, ip_address, user_agent) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $email, $ipAddress, $userAgent);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get failed attempt count in last 15 minutes
 */
function getFailedAttemptCount($conn, $email) {
    initLoginAttemptsTable($conn);
    
    $fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM login_attempts WHERE email = ? AND attempt_time > ?');
    $stmt->bind_param('ss', $email, $fifteenMinutesAgo);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)$row['count'];
}

/**
 * Clear old attempts (older than 15 minutes)
 */
function clearOldAttempts($conn, $email) {
    $fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE email = ? AND attempt_time < ?');
    $stmt->bind_param('ss', $email, $fifteenMinutesAgo);
    $stmt->execute();
    $stmt->close();
}

/**
 * Check if account is locked (5+ failed attempts)
 */
function isAccountLocked($conn, $email) {
    return getFailedAttemptCount($conn, $email) >= 5;
}

/**
 * Check if a security alert with unlock code was already sent for this email
 */
function wasUnlockCodeSent($conn, $email) {
    // Get user ID from email
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) return false;
    
    // Check for recent pending unlock code (within 15 minutes)
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('SELECT id FROM security_alerts WHERE user_id = ? AND status = "pending" AND expires_at > ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('is', $user['id'], $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $alert = $result->fetch_assoc();
    $stmt->close();
    
    return $alert ? true : false;
}

/**
 * Get active unlock alert info including the code (for testing when SMTP not configured)
 */
function getActiveUnlockAlert($conn, $email) {
    // Get user ID from email
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) return ['exists' => false, 'code' => null];
    
    // Check for recent pending unlock code (within 15 minutes)
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('SELECT id, unlock_code FROM security_alerts WHERE user_id = ? AND status = "pending" AND expires_at > ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('is', $user['id'], $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $alert = $result->fetch_assoc();
    $stmt->close();
    
    if ($alert) {
        return ['exists' => true, 'code' => $alert['unlock_code']];
    }
    
    return ['exists' => false, 'code' => null];
}

/**
 * Send security alert for failed login attempt
 */
function sendSecurityAlert($conn, $userId, $userEmail, $userName, $attemptCount = 0) {
    // Ensure security_alerts table exists with unlock_code
    if ($conn instanceof mysqli) {
        $conn->query("CREATE TABLE IF NOT EXISTS security_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            unlock_code VARCHAR(6),
            ip_address VARCHAR(45),
            user_agent TEXT,
            status ENUM('pending', 'confirmed', 'denied', 'unlocked') DEFAULT 'pending',
            attempt_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            responded_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_unlock_code (unlock_code)
        )");
        
        // Add unlock_code column if it doesn't exist
        $conn->query("ALTER TABLE security_alerts ADD COLUMN unlock_code VARCHAR(6) AFTER token");
        $conn->query("ALTER TABLE security_alerts ADD COLUMN attempt_count INT DEFAULT 0 AFTER status");
        $conn->query("ALTER TABLE security_alerts ADD COLUMN expires_at TIMESTAMP NULL AFTER responded_at");
    } else {
        $conn->query("CREATE TABLE IF NOT EXISTS security_alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            unlock_code VARCHAR(6),
            ip_address VARCHAR(45),
            user_agent TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            attempt_count INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            responded_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL
        )");
    }
    
    // Generate unique token and 6-digit unlock code
    $token = generateToken();
    $unlockCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $ipAddress = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Save alert to database with unlock code
    $stmt = $conn->prepare('INSERT INTO security_alerts (user_id, token, unlock_code, ip_address, user_agent, attempt_count, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssss', $userId, $token, $unlockCode, $ipAddress, $userAgent, $attemptCount, $expiresAt);
    $stmt->execute();
    $stmt->close();
    
    // Generate email content with unlock code
    $baseUrl = getBaseUrl();
    $emailHtml = generateSecurityAlertEmailWithCode($userName, $ipAddress, $userAgent, $timestamp, $token, $unlockCode, $attemptCount, $baseUrl);
    
    // Send email
    $subject = "🔐 Security Alert: Your unlock code is {$unlockCode} - BW Dashboard";
    $sent = sendEmail($userEmail, $subject, $emailHtml);
    
    return [
        'sent' => $sent,
        'token' => $token,
        'code' => $unlockCode // For testing/debugging only, remove in production
    ];
}

/**
 * Verify unlock code and clear failed attempts
 */
function verifyUnlockCode($conn, $email, $code) {
    // Get user ID from email
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Check for valid unlock code
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('SELECT id, token FROM security_alerts WHERE user_id = ? AND unlock_code = ? AND status = "pending" AND expires_at > ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('iss', $user['id'], $code, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $alert = $result->fetch_assoc();
    $stmt->close();
    
    if (!$alert) {
        return ['success' => false, 'message' => 'Invalid or expired code'];
    }
    
    // Mark alert as unlocked
    $stmt = $conn->prepare('UPDATE security_alerts SET status = "unlocked", responded_at = ? WHERE id = ?');
    $stmt->bind_param('si', $now, $alert['id']);
    $stmt->execute();
    $stmt->close();
    
    // Clear failed login attempts for this email
    clearOldAttempts($conn, $email);
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
    
    return ['success' => true, 'message' => 'Account unlocked successfully'];
}

/**
 * Generate security alert email with unlock code
 */
function generateSecurityAlertEmailWithCode($userName, $ipAddress, $userAgent, $timestamp, $token, $unlockCode, $attemptCount, $baseUrl) {
    $browser = getBrowserName($userAgent);
    $dateTime = date('F j, Y \a\t g:i A', strtotime($timestamp));
    
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
                                <div style="font-size: 48px; margin-bottom: 10px;">🔐</div>
                                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Account Locked</h1>
                                <p style="color: rgba(255,255,255,0.8); margin: 10px 0 0 0; font-size: 14px;">' . $attemptCount . ' failed login attempts detected</p>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 30px;">
                                <p style="color: #e0e0e0; font-size: 16px; margin: 0 0 20px 0;">
                                    Hi <strong style="color: #f4d03f;">' . htmlspecialchars($userName) . '</strong>,
                                </p>
                                
                                <p style="color: #a0a0a0; font-size: 14px; margin: 0 0 25px 0;">
                                    Your account has been temporarily locked after ' . $attemptCount . ' failed login attempts. Use the code below to unlock your account:
                                </p>
                                
                                <!-- Unlock Code Box -->
                                <div style="background: linear-gradient(135deg, #f4d03f, #e6a700); border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 25px;">
                                    <p style="color: #1e2a38; margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">YOUR UNLOCK CODE</p>
                                    <p style="color: #1e2a38; margin: 0; font-size: 36px; font-weight: 700; letter-spacing: 8px; font-family: monospace;">' . $unlockCode . '</p>
                                    <p style="color: rgba(0,0,0,0.6); margin: 10px 0 0 0; font-size: 12px;">Valid for 15 minutes</p>
                                </div>
                                
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
                                                <tr>
                                                    <td style="color: #a0a0a0; font-size: 13px; padding-right: 15px;">❌ Failed Attempts:</td>
                                                    <td style="color: #ff6b6b; font-size: 13px; font-weight: 600;">' . $attemptCount . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p style="color: #ff6b6b; font-size: 13px; margin: 0 0 20px 0; text-align: center;">
                                    ⚠️ If this wasn\'t you, please change your password immediately!
                                </p>
                                
                                <!-- Change Password Button -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td align="center">
                                            <a href="' . $baseUrl . '/profile.php?change_password=1" style="display: inline-block; background: linear-gradient(135deg, #3498db, #2980b9); color: #fff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">Change Password</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: rgba(0,0,0,0.2); padding: 20px; text-align: center;">
                                <p style="color: #666; font-size: 12px; margin: 0;">
                                    This is an automated security notification from BW Dashboard.<br>
                                    Please do not reply to this email.
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
 * Check if user has email alerts enabled
 */
function isEmailAlertEnabled($conn, $userId) {
    // Check user_settings for email alert preference
    $stmt = $conn->prepare('SELECT settings FROM user_settings WHERE user_id = ?');
    if (!$stmt) return true; // Default to enabled if can't check
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($row = $result->fetch_assoc()) {
        $settings = json_decode($row['settings'], true);
        return $settings['emailAlerts'] ?? true;
    }
    
    return true; // Default to enabled
}

// Handle direct API calls
if (basename($_SERVER['SCRIPT_NAME']) === 'security-alert.php') {
    // Check if user is logged in for status checks
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get-alerts':
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }
            
            // Ensure security_alerts table exists
            if ($conn instanceof mysqli) {
                $conn->query("CREATE TABLE IF NOT EXISTS security_alerts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    status ENUM('pending', 'confirmed', 'denied') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    responded_at TIMESTAMP NULL,
                    INDEX idx_token (token),
                    INDEX idx_user_id (user_id)
                )");
            } else {
                $conn->query("CREATE TABLE IF NOT EXISTS security_alerts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    status VARCHAR(20) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    responded_at TIMESTAMP NULL
                )");
            }
            
            // Get recent alerts for the logged-in user
            $stmt = $conn->prepare('SELECT id, ip_address, user_agent, status, created_at, responded_at FROM security_alerts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $alerts = [];
            while ($row = $result->fetch_assoc()) {
                $alerts[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['success' => true, 'alerts' => $alerts]);
            break;
        
        case 'verify-unlock-code':
            $email = trim($_POST['email'] ?? '');
            $code = trim($_POST['code'] ?? '');
            
            if (empty($email) || empty($code)) {
                echo json_encode(['success' => false, 'message' => 'Email and code are required']);
                exit;
            }
            
            $result = verifyUnlockCode($conn, $email, $code);
            echo json_encode($result);
            break;
        
        case 'check-lock-status':
            $email = trim($_POST['email'] ?? $_GET['email'] ?? '');
            
            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Email is required']);
                exit;
            }
            
            initLoginAttemptsTable($conn);
            $attempts = getFailedAttemptCount($conn, $email);
            $isLocked = $attempts >= 5;
            
            echo json_encode([
                'success' => true,
                'locked' => $isLocked,
                'attempts' => $attempts,
                'remaining' => max(0, 5 - $attempts)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
