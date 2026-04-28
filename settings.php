<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .settings-section {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .settings-title {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .settings-title i {
            color: #f4d03f;
        }
        
        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .settings-item:last-child {
            border-bottom: none;
        }
        
        .settings-label {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .settings-label-text {
            font-size: 14px;
            font-weight: 600;
            color: #e0e0e0;
        }
        
        .settings-label-desc {
            font-size: 12px;
            color: #a0a0a0;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
            background: #d8dadc;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            user-select: none;
            flex-shrink: 0;
        }

        .toggle-switch:hover {
            opacity: 0.9;
        }
        
        .toggle-switch.active {
            background: linear-gradient(135deg, #5bbcff, #2f5fa7);
            border-color: #5bbcff;
        }
        
        .toggle-slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .toggle-switch.active .toggle-slider {
            left: 24px;
        }
        
        .select-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px 14px;
            color: #e0e0e0;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            cursor: pointer;
        }
        
        .select-input option {
            background: #1e2a38;
            color: #e0e0e0;
        }
        
        .select-input:focus {
            outline: none;
            border-color: #2f5fa7;
            background: rgba(255, 255, 255, 0.08);
        }
        
        /* Light mode styles for settings */
        html.light-mode .select-input,
        body.light-mode .select-input {
            background: #ffffff !important;
            border: 1px solid #b8d4e8 !important;
            color: #1a3a5c !important;
        }
        
        html.light-mode .select-input option,
        body.light-mode .select-input option {
            background: #ffffff !important;
            color: #1a3a5c !important;
        }
        
        html.light-mode .select-input:focus,
        body.light-mode .select-input:focus {
            border-color: #2f5fa7 !important;
            background: #f8fbfd !important;
        }
        
        html.light-mode .settings-section,
        body.light-mode .settings-section {
            background: linear-gradient(135deg, #ffffff 0%, #e8f4fc 100%) !important;
            border-color: #b8d4e8 !important;
        }
        
        html.light-mode .settings-title,
        body.light-mode .settings-title {
            color: #1a3a5c !important;
            border-bottom-color: rgba(26, 58, 92, 0.1) !important;
        }
        
        html.light-mode .settings-title i,
        body.light-mode .settings-title i {
            color: #2f5fa7 !important;
        }
        
        html.light-mode .settings-label-text,
        body.light-mode .settings-label-text {
            color: #1a3a5c !important;
        }
        
        html.light-mode .settings-label-desc,
        body.light-mode .settings-label-desc {
            color: #5a7a9a !important;
        }
        
        html.light-mode .settings-item,
        body.light-mode .settings-item {
            border-bottom-color: rgba(26, 58, 92, 0.08) !important;
        }
        
        html.light-mode .page-title,
        body.light-mode .page-title {
            color: #1a3a5c !important;
        }
        
        html.light-mode .page-title i,
        body.light-mode .page-title i {
            color: #2f5fa7 !important;
        }
        
        html.light-mode .btn-save,
        body.light-mode .btn-save {
            background: linear-gradient(135deg, #2f5fa7 0%, #1e88e5 100%) !important;
        }
        
        html.light-mode .toggle-switch,
        body.light-mode .toggle-switch {
            background: #b8d4e8 !important;
        }
        
        html.light-mode .toggle-switch.active,
        body.light-mode .toggle-switch.active {
            background: linear-gradient(135deg, #2f5fa7, #1e88e5) !important;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #f4d03f, #ffd60a);
            color: #1e2a38;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(244, 208, 63, 0.3);
        }
        
        .btn-danger {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
        }
        
        .btn-danger:hover {
            background: rgba(255, 107, 107, 0.2);
        }
        
        .settings-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        @media (max-width: 768px) {
            .settings-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 90%;
            max-width: 450px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .modal-header h3 {
            font-size: 18px;
            color: #fff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h3 i {
            color: #5bbcff;
        }
        .modal-close {
            background: none;
            border: none;
            color: #a0a0a0;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .modal-close:hover {
            color: #fff;
        }
        .modal-body {
            padding: 25px;
        }
        .qr-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .qr-container img {
            background: #fff;
            padding: 10px;
            border-radius: 12px;
        }
        .secret-key {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .secret-key p {
            font-size: 12px;
            color: #a0a0a0;
            margin: 0 0 8px 0;
        }
        .secret-key code {
            font-size: 16px;
            font-weight: 600;
            color: #f4d03f;
            letter-spacing: 3px;
        }
        .verify-input {
            width: 100%;
            padding: 15px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-family: 'Poppins', monospace;
            margin-bottom: 20px;
        }
        .verify-input:focus {
            outline: none;
            border-color: #5bbcff;
        }
        .verify-input::placeholder {
            letter-spacing: normal;
            font-size: 14px;
        }
        .modal-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.3);
        }
        .modal-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .modal-btn.btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        .modal-btn.btn-danger:hover {
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }
        .setup-steps {
            margin-bottom: 20px;
        }
        .setup-steps p {
            color: #a0a0a0;
            font-size: 13px;
            margin: 0 0 10px 0;
        }
        .setup-steps ol {
            color: #e0e0e0;
            font-size: 13px;
            padding-left: 20px;
            margin: 0;
        }
        .setup-steps li {
            margin-bottom: 8px;
        }
        .error-text {
            color: #e74c3c;
            font-size: 13px;
            text-align: center;
            margin-top: -10px;
            margin-bottom: 15px;
        }
        .success-text {
            color: #27ae60;
            font-size: 13px;
            text-align: center;
            margin-bottom: 15px;
        }
        .password-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .password-input:focus {
            outline: none;
            border-color: #5bbcff;
        }

        /* Font Size Settings */
        body.font-small {
            font-size: 13px;
        }
        body.font-small .settings-label-text {
            font-size: 13px;
        }
        body.font-small .settings-label-desc {
            font-size: 11px;
        }
        body.font-medium {
            font-size: 14px;
        }
        body.font-large {
            font-size: 16px;
        }
        body.font-large .settings-label-text {
            font-size: 16px;
        }
        body.font-large .settings-label-desc {
            font-size: 13px;
        }

        /* Compact Mode */
        body.compact-mode .settings-section {
            padding: 15px;
            margin-bottom: 15px;
        }
        body.compact-mode .settings-item {
            padding: 10px 0;
        }
        body.compact-mode .settings-title {
            margin-bottom: 12px;
            padding-bottom: 10px;
        }

        /* No Animations Mode */
        body.no-animations,
        body.no-animations * {
            transition: none !important;
            animation: none !important;
        }
    </style>
</head>
<body>
    <!-- TOP NAVBAR -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Hamburger Toggle & Logo -->
            <div class="navbar-start">
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle sidebar">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="logo">
                    <a href="index.php" style="display:flex;align-items:center;">
                        <img src="assets/logo.png" alt="Andison" style="height:48px;width:auto;object-fit:contain;">
                    </a>
                </div>
            </div>

            <!-- Right Profile Section -->
            <div class="navbar-end">

                <div class="profile-dropdown">
                    <button type="button" class="profile-btn" id="profileBtn" aria-label="Profile menu">
                        <span class="profile-name"><?php echo htmlspecialchars(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileMenu">
                        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <a href="help.php"><i class="fas fa-question-circle"></i> Help</a>
                        <hr>
                        <a href="logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <div class="page-title">
            <i class="fas fa-cog"></i> Settings
        </div>

        <div class="settings-container">
            <!-- Display Settings -->
            <div class="settings-section">
                <div class="settings-title">
                    <i class="fas fa-palette"></i> Display & Appearance
                </div>
                
                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Dark Theme</div>
                        <div class="settings-label-desc">Use dark mode interface</div>
                    </div>
                    <div class="toggle-switch active" id="darkModeToggle">
                        <div class="toggle-slider"></div>
                    </div>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Compact Mode</div>
                        <div class="settings-label-desc">Reduce spacing for more content visibility</div>
                    </div>
                    <div class="toggle-switch" id="compactModeToggle" onclick="toggleAndSave(this, 'compactMode')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Animation Effects</div>
                        <div class="settings-label-desc">Enable smooth transitions and animations</div>
                    </div>
                    <div class="toggle-switch active" id="animationsToggle" onclick="toggleAndSave(this, 'animations')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Sidebar Behavior</div>
                        <div class="settings-label-desc">Choose default sidebar state</div>
                    </div>
                    <select class="select-input" id="sidebarBehaviorSelect" onchange="changeSidebarBehavior(this.value)">
                        <option value="expanded">Always Expanded</option>
                        <option value="collapsed">Always Collapsed</option>
                        <option value="remember" selected>Remember Last State</option>
                    </select>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Accent Color</div>
                        <div class="settings-label-desc">Customize highlight color</div>
                    </div>
                    <select class="select-input" id="accentColorSelect" onchange="changeAccentColor(this.value)">
                        <option value="gold" selected>Gold (Default)</option>
                        <option value="blue">Blue</option>
                        <option value="green">Green</option>
                        <option value="purple">Purple</option>
                        <option value="red">Red</option>
                        <option value="orange">Orange</option>
                    </select>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Font Size</div>
                        <div class="settings-label-desc">Adjust text size across the app</div>
                    </div>
                    <select class="select-input" id="fontSizeSelect" onchange="changeFontSize(this.value)">
                        <option value="small">Small</option>
                        <option value="medium" selected>Medium (Default)</option>
                        <option value="large">Large</option>
                    </select>
                </div>
            </div>

            <!-- Data & Privacy Settings -->
            <div class="settings-section">
                <div class="settings-title">
                    <i class="fas fa-lock"></i> Data & Privacy
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Data Analytics</div>
                        <div class="settings-label-desc">Allow anonymous usage analytics</div>
                    </div>
                    <div class="toggle-switch active" id="dataAnalyticsToggle" onclick="toggleAndSave(this, 'dataAnalytics')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Session Timeout</div>
                        <div class="settings-label-desc">Auto-logout after inactivity (minutes)</div>
                    </div>
                    <select class="select-input" id="sessionTimeoutSelect" onchange="saveSettings()">
                        <option value="5 minutes">5 minutes</option>
                        <option value="15 minutes" selected>15 minutes</option>
                        <option value="30 minutes">30 minutes</option>
                        <option value="1 hour">1 hour</option>
                        <option value="Never">Never</option>
                    </select>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Security Email Alerts</div>
                        <div class="settings-label-desc">Get notified when someone tries to login with wrong password</div>
                    </div>
                    <div class="toggle-switch active" id="emailAlertsToggle" onclick="toggleAndSave(this, 'emailAlerts')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Recent Security Alerts</div>
                        <div class="settings-label-desc">View recent login attempts on your account</div>
                    </div>
                    <button class="btn-save" style="background: rgba(91, 188, 255, 0.2); color: #5bbcff; border: 1px solid #5bbcff;" onclick="viewSecurityAlerts()">View Alerts</button>
                </div>
            </div>

            <!-- Security Alerts Modal -->
            <div id="alertsModal" class="modal-overlay" style="display: none;">
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-shield-alt"></i> Recent Security Alerts</h3>
                        <button class="modal-close" onclick="closeAlertsModal()">&times;</button>
                    </div>
                    <div class="modal-body" id="alertsModalBody">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>

            <!-- Account Management -->
            <div class="settings-section">
                <div class="settings-title">
                    <i class="fas fa-user-circle"></i> Account Management
                </div>

                <!-- Change Username Section -->
                <div style="margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <h4 style="color: #40538f; margin-bottom: 15px; font-size: 14px; font-weight: 600;">Change Username</h4>
                    <form id="changeNameForm" style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <label for="newName" style="color: #40538f; font-size: 12px; font-weight: 600;">New Username</label>
                            <input type="text" id="newName" name="name" placeholder="Enter new username" 
                                style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 10px 12px; color: #6e6161; font-family: 'Poppins', sans-serif; font-size: 13px;"
                                required>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <label for="namePassword" style="color: #40538f; font-size: 12px; font-weight: 600;">Current Password (for verification)</label>
                            <input type="password" id="namePassword" name="password" placeholder="Enter your current password" 
                                style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 10px 12px; color: #6e6161; font-family: 'Poppins', sans-serif; font-size: 13px;"
                                required>
                        </div>
                        <button type="submit" class="btn-save" style="width: fit-content;">Update Username</button>
                    </form>
                    <div id="nameStatus" style="margin-top: 10px; font-size: 12px; display: none;"></div>
                </div>

                <!-- Change Password Section -->
                <div>
                    <h4 style="color: #40538f; margin-bottom: 15px; font-size: 14px; font-weight: 600;">Change Password</h4>
                    <form id="changePasswordForm" style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <label for="currentPassword" style="color: #40538f; font-size: 12px; font-weight: 600;">Current Password</label>
                            <input type="password" id="currentPassword" name="current_password" placeholder="Enter your current password" 
                                style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 10px 12px; color: #6e6161; font-family: 'Poppins', sans-serif; font-size: 13px;"
                                required>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <label for="newPassword" style="color: #40538f; font-size: 12px; font-weight: 600;">New Password</label>
                            <input type="password" id="newPassword" name="new_password" placeholder="Enter new password (min 6 characters)" 
                                style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 10px 12px; color: #6e6161; font-family: 'Poppins', sans-serif; font-size: 13px;"
                                required>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <label for="confirmPassword" style="color: #40538f; font-size: 12px; font-weight: 600;">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm new password" 
                                style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 10px 12px; color: #6e6161; font-family: 'Poppins', sans-serif; font-size: 13px;"
                                required>
                        </div>
                        <button type="submit" class="btn-save" style="width: fit-content;">Change Password</button>
                    </form>
                    <div id="passwordStatus" style="margin-top: 10px; font-size: 12px; display: none;"></div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="settings-section">
                <div class="settings-title" style="color: #ff6b6b;">
                    <i class="fas fa-exclamation-triangle"></i> Danger Zone
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Reset All Settings</div>
                        <div class="settings-label-desc">Reset all settings to factory defaults</div>
                    </div>
                    <button class="btn-save btn-danger">Reset Settings</button>
                </div>

                <div class="settings-item">
                    <div class="settings-label">
                        <div class="settings-label-text">Delete Account</div>
                        <div class="settings-label-desc">Permanently delete your account and all data</div>
                    </div>
                    <button class="btn-save btn-danger">Delete Account</button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="settings-actions">
                <button class="btn-save">Save Changes</button>
                <button class="btn-save" style="background: rgba(255, 255, 255, 0.1); color: #e0e0e0; border: 1px solid rgba(255, 255, 255, 0.2);">Cancel</button>
            </div>
        </div>
    </div>

    <script src="js/app.js" defer></script>
    <script>
        // Current settings state
        let currentSettings = {
            dataAnalytics: true,
            sessionTimeout: '15 minutes',
            emailAlerts: true
        };

        // Load settings on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSettings();
        });

        // Load settings from server
        async function loadSettings() {
            try {
                const response = await fetch('api/get-settings.php');
                const data = await response.json();
                
                if (data.success) {
                    currentSettings = data.settings;
                    applySettings();
                }
            } catch (error) {
                console.error('Failed to load settings:', error);
            }
        }

        // Apply settings to UI
        function applySettings() {
            // Data Analytics toggle
            const dataAnalyticsToggle = document.getElementById('dataAnalyticsToggle');
            if (dataAnalyticsToggle) {
                dataAnalyticsToggle.classList.toggle('active', currentSettings.dataAnalytics);
            }

            // Session Timeout select
            const sessionTimeoutSelect = document.getElementById('sessionTimeoutSelect');
            if (sessionTimeoutSelect) {
                sessionTimeoutSelect.value = currentSettings.sessionTimeout;
            }

            // Email Alerts toggle
            const emailAlertsToggle = document.getElementById('emailAlertsToggle');
            if (emailAlertsToggle) {
                emailAlertsToggle.classList.toggle('active', currentSettings.emailAlerts !== false);
            }

            // Compact Mode toggle
            const compactModeToggle = document.getElementById('compactModeToggle');
            if (compactModeToggle) {
                compactModeToggle.classList.toggle('active', currentSettings.compactMode);
                if (currentSettings.compactMode) {
                    document.body.classList.add('compact-mode');
                    localStorage.setItem('compactMode', 'true');
                }
            }

            // Animations toggle
            const animationsToggle = document.getElementById('animationsToggle');
            if (animationsToggle) {
                animationsToggle.classList.toggle('active', currentSettings.animations !== false);
                if (currentSettings.animations === false) {
                    document.body.classList.add('no-animations');
                    localStorage.setItem('animations', 'false');
                }
            }

            // Sidebar Behavior select
            const sidebarBehaviorSelect = document.getElementById('sidebarBehaviorSelect');
            if (sidebarBehaviorSelect) {
                // Try to get value from server settings, then localStorage, then default
                const savedBehavior = currentSettings.sidebarBehavior || localStorage.getItem('sidebarBehavior') || 'remember';
                sidebarBehaviorSelect.value = savedBehavior;
                localStorage.setItem('sidebarBehavior', savedBehavior);
                
                // Apply sidebar behavior immediately
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                if (sidebar) {
                    if (savedBehavior === 'expanded') {
                        sidebar.classList.remove('collapsed');
                        if (mainContent) mainContent.classList.remove('sidebar-collapsed');
                    } else if (savedBehavior === 'collapsed') {
                        sidebar.classList.add('collapsed');
                        if (mainContent) mainContent.classList.add('sidebar-collapsed');
                    }
                }
            }

            // Accent Color select
            const accentColorSelect = document.getElementById('accentColorSelect');
            if (accentColorSelect) {
                // Try to get value from server settings, then localStorage, then default
                const savedColor = currentSettings.accentColor || localStorage.getItem('accentColor') || 'gold';
                accentColorSelect.value = savedColor;
                applyAccentColor(savedColor);
            }

            // Font Size select
            const fontSizeSelect = document.getElementById('fontSizeSelect');
            if (fontSizeSelect) {
                // Try to get value from server settings, then localStorage, then default
                const savedSize = currentSettings.fontSize || localStorage.getItem('fontSize') || 'medium';
                fontSizeSelect.value = savedSize;
                applyFontSize(savedSize);
            }
        }

        // Toggle and save setting
        function toggleAndSave(element, settingKey) {
            element.classList.toggle('active');
            currentSettings[settingKey] = element.classList.contains('active');
            
            // Apply special settings immediately and save to localStorage
            if (settingKey === 'compactMode') {
                document.body.classList.toggle('compact-mode', element.classList.contains('active'));
                localStorage.setItem('compactMode', element.classList.contains('active'));
            }
            if (settingKey === 'animations') {
                document.body.classList.toggle('no-animations', !element.classList.contains('active'));
                localStorage.setItem('animations', element.classList.contains('active'));
            }
            
            saveSettings();
        }

        // Save settings to server
        async function saveSettings() {
            // Update session timeout from select
            const sessionTimeoutSelect = document.getElementById('sessionTimeoutSelect');
            if (sessionTimeoutSelect) {
                currentSettings.sessionTimeout = sessionTimeoutSelect.value;
            }

            // Update sidebar behavior from select
            const sidebarBehaviorSelect = document.getElementById('sidebarBehaviorSelect');
            if (sidebarBehaviorSelect) {
                currentSettings.sidebarBehavior = sidebarBehaviorSelect.value;
            }

            // Update accent color from select
            const accentColorSelect = document.getElementById('accentColorSelect');
            if (accentColorSelect) {
                currentSettings.accentColor = accentColorSelect.value;
            }

            // Update font size from select
            const fontSizeSelect = document.getElementById('fontSizeSelect');
            if (fontSizeSelect) {
                currentSettings.fontSize = fontSizeSelect.value;
            }

            try {
                const response = await fetch('api/save-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ settings: currentSettings })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Settings saved!', 'success');
                } else {
                    showToast('Failed to save settings', 'error');
                }
            } catch (error) {
                console.error('Failed to save settings:', error);
                showToast('Failed to save settings', 'error');
            }
        }

        // Change Accent Color
        function changeAccentColor(color) {
            console.log('Changing accent color to:', color);
            applyAccentColor(color);
            currentSettings.accentColor = color;
            saveSettings();
            showToast('Accent color set to: ' + color, 'success');
        }

        function applyAccentColor(color) {
            const colors = {
                gold: '#f4d03f',
                blue: '#3498db',
                green: '#27ae60',
                purple: '#9b59b6',
                red: '#e74c3c',
                orange: '#e67e22'
            };
            
            const selectedColor = colors[color] || colors.gold;
            document.documentElement.style.setProperty('--color-accent', selectedColor);
            localStorage.setItem('accentColor', color);
        }

        // Change Sidebar Behavior
        function changeSidebarBehavior(behavior) {
            console.log('Changing sidebar behavior to:', behavior);
            localStorage.setItem('sidebarBehavior', behavior);
            currentSettings.sidebarBehavior = behavior;
            
            // Apply immediately
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            console.log('Sidebar element:', sidebar);
            console.log('MainContent element:', mainContent);
            
            if (sidebar) {
                if (behavior === 'expanded') {
                    sidebar.classList.remove('collapsed');
                    if (mainContent) mainContent.classList.remove('sidebar-collapsed');
                    console.log('Sidebar expanded');
                } else if (behavior === 'collapsed') {
                    sidebar.classList.add('collapsed');
                    if (mainContent) mainContent.classList.add('sidebar-collapsed');
                    console.log('Sidebar collapsed');
                }
                // 'remember' doesn't change current state, just saves preference
            } else {
                console.error('Sidebar element not found!');
            }
            
            saveSettings();
            showToast('Sidebar behavior set to: ' + behavior, 'success');
        }

        // Change Font Size
        function changeFontSize(size) {
            applyFontSize(size);
            currentSettings.fontSize = size;
            saveSettings();
        }

        function applyFontSize(size) {
            document.body.classList.remove('font-small', 'font-medium', 'font-large');
            document.body.classList.add('font-' + size);
            localStorage.setItem('fontSize', size);
        }

        // Show toast notification
        function showToast(message, type) {
            // Remove existing toast
            const existingToast = document.querySelector('.settings-toast');
            if (existingToast) existingToast.remove();

            const toast = document.createElement('div');
            toast.className = 'settings-toast';
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 12px 24px;
                border-radius: 8px;
                color: white;
                font-size: 14px;
                font-weight: 500;
                z-index: 9999;
                animation: slideIn 0.3s ease;
                background: ${type === 'success' ? 'linear-gradient(135deg, #27ae60, #2ecc71)' : 'linear-gradient(135deg, #e74c3c, #c0392b)'};
            `;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 2000);
        }

        function toggleSetting(element) {
            element.classList.toggle('active');
        }

        // ============================================
        // SECURITY EMAIL ALERTS FUNCTIONS
        // ============================================

        // View security alerts modal
        async function viewSecurityAlerts() {
            const modal = document.getElementById('alertsModal');
            const modalBody = document.getElementById('alertsModalBody');
            
            modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #5bbcff;"></i><p style="color: #a0a0a0; margin-top: 15px;">Loading alerts...</p></div>';
            modal.style.display = 'flex';

            try {
                const response = await fetch('api/security-alert.php?action=get-alerts');
                const data = await response.json();

                if (data.success && data.alerts.length > 0) {
                    let alertsHtml = '<div class="alerts-list">';
                    
                    data.alerts.forEach(alert => {
                        const date = new Date(alert.created_at).toLocaleString();
                        const statusColor = alert.status === 'confirmed' ? '#27ae60' : 
                                           alert.status === 'denied' ? '#e74c3c' : '#f39c12';
                        const statusText = alert.status === 'confirmed' ? 'Confirmed by you' :
                                          alert.status === 'denied' ? 'Marked as suspicious' : 'Pending';
                        const statusIcon = alert.status === 'confirmed' ? 'fa-check-circle' :
                                          alert.status === 'denied' ? 'fa-times-circle' : 'fa-clock';
                        
                        alertsHtml += `
                            <div class="alert-item" style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 15px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                    <div style="color: #e0e0e0; font-size: 14px; font-weight: 600;">
                                        <i class="fas fa-exclamation-triangle" style="color: #f39c12; margin-right: 8px;"></i>
                                        Failed Login Attempt
                                    </div>
                                    <span style="color: ${statusColor}; font-size: 12px;">
                                        <i class="fas ${statusIcon}"></i> ${statusText}
                                    </span>
                                </div>
                                <div style="color: #a0a0a0; font-size: 12px;">
                                    <div style="margin-bottom: 5px;"><i class="fas fa-calendar" style="width: 16px;"></i> ${date}</div>
                                    <div style="margin-bottom: 5px;"><i class="fas fa-globe" style="width: 16px;"></i> IP: ${alert.ip_address || 'Unknown'}</div>
                                </div>
                            </div>
                        `;
                    });
                    
                    alertsHtml += '</div>';
                    modalBody.innerHTML = alertsHtml;
                } else {
                    modalBody.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-shield-alt" style="font-size: 48px; color: #27ae60; margin-bottom: 15px;"></i>
                            <h3 style="color: #e0e0e0; margin: 0 0 10px 0;">All Clear!</h3>
                            <p style="color: #a0a0a0; margin: 0;">No suspicious login attempts detected on your account.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Failed to load alerts:', error);
                modalBody.innerHTML = '<p style="color: #e74c3c; text-align: center;">Failed to load security alerts. Please try again.</p>';
            }
        }

        function closeAlertsModal() {
            document.getElementById('alertsModal').style.display = 'none';
        }

        // Close modal on outside click
        document.getElementById('alertsModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeAlertsModal();
        });

        // Load current username on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentName = '<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>';
            const newNameInput = document.getElementById('newName');
            if (newNameInput) {
                newNameInput.value = currentName;
            }
        });
        document.getElementById('changePasswordForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const statusDiv = document.getElementById('passwordStatus');
            
            // Basic validation
            if (newPassword !== confirmPassword) {
                statusDiv.style.color = '#e74c3c';
                statusDiv.textContent = 'New passwords do not match';
                statusDiv.style.display = 'block';
                return;
            }
            
            if (newPassword.length < 6) {
                statusDiv.style.color = '#e74c3c';
                statusDiv.textContent = 'Password must be at least 6 characters long';
                statusDiv.style.display = 'block';
                return;
            }
            
            try {
                const response = await fetch('api/change-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        current_password: currentPassword,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    statusDiv.style.color = '#27ae60';
                    statusDiv.textContent = '✓ Password changed successfully!';
                    statusDiv.style.display = 'block';
                    document.getElementById('changePasswordForm').reset();
                    setTimeout(() => {
                        statusDiv.style.display = 'none';
                    }, 3000);
                } else {
                    statusDiv.style.color = '#e74c3c';
                    statusDiv.textContent = '✗ ' + data.message;
                    statusDiv.style.display = 'block';
                }
            } catch (error) {
                console.error('Error changing password:', error);
                statusDiv.style.color = '#e74c3c';
                statusDiv.textContent = '✗ An error occurred. Please try again.';
                statusDiv.style.display = 'block';
            }
        });

        // Handle Name Change Form
        document.getElementById('changeNameForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const newName = document.getElementById('newName').value;
            const password = document.getElementById('namePassword').value;
            const statusDiv = document.getElementById('nameStatus');
            
            try {
                const response = await fetch('api/change-username.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        name: newName,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    statusDiv.style.color = '#27ae60';
                    statusDiv.textContent = '✓ Username updated successfully!';
                    statusDiv.style.display = 'block';
                    document.getElementById('changeNameForm').reset();
                    setTimeout(() => {
                        statusDiv.style.display = 'none';
                    }, 3000);
                } else {
                    statusDiv.style.color = '#e74c3c';
                    statusDiv.textContent = '✗ ' + data.message;
                    statusDiv.style.display = 'block';
                }
            } catch (error) {
                console.error('Error changing username:', error);
                statusDiv.style.color = '#e74c3c';
                statusDiv.textContent = '✗ An error occurred. Please try again.';
                statusDiv.style.display = 'block';
            }
        });

        // Dark mode toggle is handled by app.js initializeDarkModeToggle()
    </script>
    <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    </style>
</body>
</html>

