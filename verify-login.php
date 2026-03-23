<?php
session_start();
require_once __DIR__ . '/db_config.php';

$action = $_GET['action'] ?? '';
$token = $_GET['token'] ?? '';

$message = '';
$messageType = 'info';
$showPasswordChange = false;

if ($token) {
    // Look up the security alert
    $stmt = $conn->prepare('SELECT sa.*, u.email, u.name FROM security_alerts sa JOIN users u ON sa.user_id = u.id WHERE sa.token = ? LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $alert = $result->fetch_assoc();
    $stmt->close();
    
    if (!$alert) {
        $message = 'Invalid or expired security token.';
        $messageType = 'error';
    } elseif ($alert['status'] !== 'pending') {
        $message = 'This security alert has already been responded to.';
        $messageType = 'info';
    } else {
        // Process the response
        if ($action === 'confirm') {
            // User confirmed it was them
            $stmt = $conn->prepare('UPDATE security_alerts SET status = ?, responded_at = NOW() WHERE token = ?');
            $status = 'confirmed';
            $stmt->bind_param('ss', $status, $token);
            $stmt->execute();
            $stmt->close();
            
            $message = 'Thank you for confirming! We\'ve noted that this login attempt was from you.';
            $messageType = 'success';
        } elseif ($action === 'deny') {
            // User denied - this is suspicious activity
            $stmt = $conn->prepare('UPDATE security_alerts SET status = ?, responded_at = NOW() WHERE token = ?');
            $status = 'denied';
            $stmt->bind_param('ss', $status, $token);
            $stmt->execute();
            $stmt->close();
            
            $message = 'We\'ve secured your account. We strongly recommend changing your password immediately.';
            $messageType = 'warning';
            $showPasswordChange = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification - BW Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <style>
        .result-card {
            text-align: center;
            padding: 40px;
        }
        .result-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        .result-icon.success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: #fff;
        }
        .result-icon.warning {
            background: linear-gradient(135deg, #f39c12, #f1c40f);
            color: #fff;
        }
        .result-icon.error {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
        }
        .result-icon.info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
        }
        .result-message {
            color: #e0e0e0;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn-action {
            display: inline-block;
            padding: 14px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #f4d03f, #ffd60a);
            color: #1e2a38;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(244, 208, 63, 0.3);
        }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .security-tip {
            background: rgba(244, 208, 63, 0.1);
            border: 1px solid rgba(244, 208, 63, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
            text-align: left;
        }
        .security-tip h4 {
            color: #f4d03f;
            font-size: 14px;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .security-tip ul {
            color: #a0a0a0;
            font-size: 13px;
            margin: 0;
            padding-left: 20px;
        }
        .security-tip li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="bg-gradient" aria-hidden="true"></div>

    <div class="login-container">
        <div class="login-card" role="main">
            <div class="card-border" aria-hidden="true"></div>

            <div class="result-card">
                <?php
                $iconClass = $messageType;
                $icon = match($messageType) {
                    'success' => 'fa-check',
                    'warning' => 'fa-exclamation-triangle',
                    'error' => 'fa-times',
                    default => 'fa-info'
                };
                ?>
                <div class="result-icon <?php echo $iconClass; ?>">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>

                <h1 class="login-title">
                    <?php
                    echo match($messageType) {
                        'success' => 'Confirmed!',
                        'warning' => 'Account Secured',
                        'error' => 'Error',
                        default => 'Security Notice'
                    };
                    ?>
                </h1>

                <p class="result-message">
                    <?php echo htmlspecialchars($message ?: 'Please use the links from your security alert email.'); ?>
                </p>

                <div class="action-buttons">
                    <?php if ($showPasswordChange): ?>
                        <a href="profile.php?change_password=1" class="btn-action btn-danger">
                            <i class="fas fa-key"></i> Change Password Now
                        </a>
                    <?php endif; ?>
                    
                    <a href="login.php" class="btn-action btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                    
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="settings.php" class="btn-action btn-secondary">
                            <i class="fas fa-cog"></i> Security Settings
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($showPasswordChange): ?>
                <div class="security-tip">
                    <h4><i class="fas fa-shield-alt"></i> Security Tips</h4>
                    <ul>
                        <li>Use a strong, unique password with letters, numbers, and symbols</li>
                        <li>Never share your password with anyone</li>
                        <li>Enable email alerts in settings to stay notified</li>
                        <li>Check your recent login activity regularly</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
