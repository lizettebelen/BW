<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$userEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

if (empty($userEmail)) {
    session_destroy();
    header('Location: login.php', true, 302);
    exit;
}
?><!DOCTYPE html>   
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-wrapper {
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .profile-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 132px;
            height: 132px;
            border-radius: 50%;
            margin: 0 auto 20px;
            position: relative;
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .profile-avatar:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 22px rgba(0, 0, 0, 0.25);
        }
        .profile-avatar-media {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 20%, #3a6ab3 0%, #264d8f 70%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            border: 4px solid #f4d03f;
            overflow: hidden;
            box-sizing: border-box;
        }
        .profile-avatar img {
            display: none;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-avatar-text {
            line-height: 1;
        }
        .profile-avatar-upload {
            position: absolute;
            right: -2px;
            bottom: -2px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid #1e2a38;
            background: linear-gradient(135deg, #f4d03f 0%, #e2b914 100%);
            color: #1e2a38;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.28);
        }
        .profile-upload-hint {
            margin: -8px 0 0;
            font-size: 12px;
            color: #b7c4d6;
        }
        .profile-dialog-backdrop {
            position: fixed;
            inset: 0;
            z-index: 100100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(8, 14, 24, 0.62);
            backdrop-filter: blur(6px);
        }
        .profile-dialog-backdrop.active {
            display: flex;
        }
        .profile-dialog {
            width: min(92vw, 460px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: linear-gradient(145deg, #192a43 0%, #21395c 55%, #1a2f4e 100%);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.42);
            color: #f2f7ff;
            overflow: hidden;
            animation: profileDialogIn 0.18s ease-out;
        }
        @keyframes profileDialogIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .profile-dialog-head {
            padding: 16px 18px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-dialog-head i {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(244, 208, 63, 0.18);
            color: #f4d03f;
            font-size: 14px;
        }
        .profile-dialog-title {
            margin: 0;
            font-size: 18px;
            color: #ffffff;
            font-weight: 600;
            letter-spacing: 0.2px;
        }
        .profile-dialog-body {
            padding: 14px 18px 12px;
            color: #e6eefc;
            font-size: 14px;
            line-height: 1.45;
        }
        .profile-dialog-input {
            width: 100%;
            margin-top: 10px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            background: rgba(8, 14, 24, 0.62);
            color: #ffffff;
            padding: 11px 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
        }
        .profile-dialog-input:focus {
            border-color: #f4d03f;
            box-shadow: 0 0 0 3px rgba(244, 208, 63, 0.15);
        }
        .profile-dialog-actions {
            padding: 12px 18px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .profile-dialog-btn {
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: 0.2s ease;
        }
        .profile-dialog-btn.cancel {
            background: rgba(255, 255, 255, 0.16);
            color: #f2f7ff;
        }
        .profile-dialog-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.24);
        }
        .profile-dialog-btn.ok {
            background: linear-gradient(135deg, #f4d03f, #e2b914);
            color: #1d2638;
        }
        .profile-dialog-btn.ok:hover {
            filter: brightness(1.04);
        }
        .light-mode .profile-dialog {
            border: 1px solid rgba(18, 40, 74, 0.18);
            background: linear-gradient(145deg, #f7fbff 0%, #eaf2fb 52%, #dce8f6 100%);
            color: #132849;
            box-shadow: 0 18px 42px rgba(8, 24, 49, 0.2);
        }
        .light-mode .profile-dialog-head {
            border-bottom: 1px solid rgba(18, 40, 74, 0.12);
        }
        .light-mode .profile-dialog-head i {
            background: rgba(47, 95, 167, 0.16);
            color: #2f5fa7;
        }
        .light-mode .profile-dialog-title {
            color: #16345d;
        }
        .light-mode .profile-dialog-body {
            color: #233f66;
        }
        .light-mode .profile-dialog-input {
            border: 1px solid rgba(26, 54, 92, 0.26);
            background: #ffffff;
            color: #102848;
        }
        .light-mode .profile-dialog-input:focus {
            border-color: #2f5fa7;
            box-shadow: 0 0 0 3px rgba(47, 95, 167, 0.18);
        }
        .light-mode .profile-dialog-btn.cancel {
            background: #e4edf8;
            color: #1d3a62;
        }
        .light-mode .profile-dialog-btn.cancel:hover {
            background: #d6e4f4;
        }
        .profile-header h1 {
            font-size: 28px;
            color: #fff;
            margin: 15px 0 5px;
        }
        .profile-header p {
            color: #a0a0a0;
            font-size: 14px;
        }
        .profile-section {
            margin-bottom: 30px;
        }
        .profile-section h3 {
            color: #f4d03f;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .info-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .info-label {
            font-size: 11px;
            color: #a0a0a0;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .info-value {
            font-size: 16px;
            color: #e0e0e0;
            font-weight: 500;
        }
        .profile-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-action {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        .btn-edit {
            background: linear-gradient(135deg, #2f5fa7, #1e3c72);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(47, 95, 167, 0.3);
        }
        .btn-back {
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        .btn-logout {
            grid-column: 1 / -1;
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
        }
        .btn-logout:hover {
            background: rgba(255, 107, 107, 0.2);
        }
        @media (max-width: 480px) {
            .profile-card { padding: 25px; }
            .profile-header h1 { font-size: 22px; }
            .profile-actions { grid-template-columns: 1fr; }
            .btn-logout { grid-column: 1; }
            .profile-avatar {
                width: 112px;
                height: 112px;
            }
            .profile-avatar-upload {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- TOP NAVBAR -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-start">
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle sidebar">
                    <span></span><span></span><span></span>
                </button>
                <div class="logo">
                    <a href="index.php" style="display:flex;align-items:center;">
                        <img src="assets/logo.png" alt="Andison" style="height:48px;width:auto;object-fit:contain;">
                    </a>
                </div>
            </div>
            <div class="navbar-center">
                <h1 class="dashboard-title">My Profile</h1>
            </div>
            <div class="navbar-end">
                <div class="profile-dropdown">
                    <button type="button" class="profile-btn" id="profileBtn" aria-label="Profile menu">
                        <span class="profile-name"><?php echo htmlspecialchars($userName); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileMenu">
                        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <a href="help.php"><i class="fas fa-question-circle"></i> Help</a>
                        <hr>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- SIDEBAR -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <div class="profile-wrapper">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar" id="profileAvatarContainer" aria-label="Upload profile picture" title="Change profile picture">
                        <div class="profile-avatar-media">
                            <img id="profilePicImg" src="" alt="Profile picture">
                            <span id="profileAvatarText" class="profile-avatar-text">👤</span>
                        </div>
                        <div class="profile-avatar-upload" aria-hidden="true"><i class="fas fa-camera"></i></div>
                    </div>
                    <input type="file" id="profilePicInput" accept="image/*" style="display: none;">
                    <p class="profile-upload-hint">Tap avatar to upload photo</p>
                    <h1><?php echo htmlspecialchars($userName); ?></h1>
                    <p><?php echo htmlspecialchars($userEmail); ?></p>
                </div>

                <div class="profile-section">
                    <h3>Personal Information</h3>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($userName); ?></div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3>Account Information</h3>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($userEmail); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value">March 5, 2026</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value">Today</div>
                    </div>
                </div>

                <div class="profile-actions">
                    <button class="btn-action btn-edit" onclick="editName()">✎ Edit Profile</button>
                    <button class="btn-action btn-back" onclick="history.back()">← Back</button>
                    <button class="btn-action btn-logout" onclick="window.location.href='logout.php'">🚪 Logout</button>
                </div>
            </div>
        </div>
    </main>

    <div class="profile-dialog-backdrop" id="profileDialogBackdrop" aria-hidden="true">
        <div class="profile-dialog" role="dialog" aria-modal="true" aria-labelledby="profileDialogTitle">
            <div class="profile-dialog-head">
                <i class="fas fa-pen"></i>
                <h3 class="profile-dialog-title" id="profileDialogTitle">Message</h3>
            </div>
            <div class="profile-dialog-body">
                <div id="profileDialogMessage"></div>
                <input type="text" id="profileDialogInput" class="profile-dialog-input" autocomplete="off">
            </div>
            <div class="profile-dialog-actions">
                <button type="button" class="profile-dialog-btn cancel" id="profileDialogCancel">Cancel</button>
                <button type="button" class="profile-dialog-btn ok" id="profileDialogOk">OK</button>
            </div>
        </div>
    </div>

    <script src="js/app.js" defer></script>
    <script>
        // Load profile picture on page load
        document.addEventListener('DOMContentLoaded', function() {
            const img = document.getElementById('profilePicImg');
            const text = document.getElementById('profileAvatarText');
            fetch('api/get-profile-pic.php')
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.picture_url) {
                        img.src = d.picture_url + '?t=' + Date.now();
                        img.style.display = 'block';
                        text.style.display = 'none';
                    }
                })
                .catch(e => console.error('Error loading profile picture:', e));
        });

        // Profile picture upload
        const avatarContainer = document.getElementById('profileAvatarContainer');
        const picInput = document.getElementById('profilePicInput');
        const profileDialogBackdrop = document.getElementById('profileDialogBackdrop');
        const profileDialogTitle = document.getElementById('profileDialogTitle');
        const profileDialogMessage = document.getElementById('profileDialogMessage');
        const profileDialogInput = document.getElementById('profileDialogInput');
        const profileDialogCancel = document.getElementById('profileDialogCancel');
        const profileDialogOk = document.getElementById('profileDialogOk');

        let profileDialogResolver = null;

        function openProfileDialog(options) {
            const mode = options.mode || 'alert';
            profileDialogTitle.textContent = options.title || 'Notice';
            profileDialogMessage.textContent = options.message || '';
            profileDialogInput.value = options.value || '';
            profileDialogInput.style.display = mode === 'prompt' ? 'block' : 'none';
            profileDialogCancel.style.display = mode === 'alert' ? 'none' : 'inline-flex';
            profileDialogOk.textContent = options.okText || 'OK';

            profileDialogBackdrop.classList.add('active');
            profileDialogBackdrop.setAttribute('aria-hidden', 'false');

            if (mode === 'prompt') {
                setTimeout(function() {
                    profileDialogInput.focus();
                    profileDialogInput.select();
                }, 20);
            } else {
                setTimeout(function() {
                    profileDialogOk.focus();
                }, 20);
            }

            return new Promise(function(resolve) {
                profileDialogResolver = function(result) {
                    profileDialogBackdrop.classList.remove('active');
                    profileDialogBackdrop.setAttribute('aria-hidden', 'true');
                    profileDialogResolver = null;
                    resolve(result);
                };
            });
        }

        function closeProfileDialog(result) {
            if (typeof profileDialogResolver === 'function') {
                profileDialogResolver(result);
            }
        }

        function showMessage(msg, title) {
            return openProfileDialog({
                mode: 'alert',
                title: title || 'Notice',
                message: msg,
                okText: 'OK'
            });
        }

        function askText(message, initialValue, title) {
            return openProfileDialog({
                mode: 'prompt',
                title: title || 'Input Required',
                message: message,
                value: initialValue || '',
                okText: 'Save'
            });
        }

        profileDialogOk.addEventListener('click', function() {
            closeProfileDialog({
                confirmed: true,
                value: profileDialogInput.value || ''
            });
        });

        profileDialogCancel.addEventListener('click', function() {
            closeProfileDialog({ confirmed: false, value: '' });
        });

        profileDialogBackdrop.addEventListener('click', function(e) {
            if (e.target === profileDialogBackdrop && profileDialogCancel.style.display !== 'none') {
                closeProfileDialog({ confirmed: false, value: '' });
            }
        });

        document.addEventListener('keydown', function(e) {
            if (!profileDialogBackdrop.classList.contains('active')) {
                return;
            }

            if (e.key === 'Escape') {
                if (profileDialogCancel.style.display !== 'none') {
                    closeProfileDialog({ confirmed: false, value: '' });
                } else {
                    closeProfileDialog({ confirmed: true, value: '' });
                }
            }

            if (e.key === 'Enter' && profileDialogInput.style.display !== 'none') {
                e.preventDefault();
                closeProfileDialog({
                    confirmed: true,
                    value: profileDialogInput.value || ''
                });
            }
        });
        
        avatarContainer.addEventListener('click', () => picInput.click());
        
        picInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showMessage('File size must be less than 5MB', 'Upload Error');
                return;
            }
            
            // Validate file type
            if (!file.type.startsWith('image/')) {
                showMessage('Please select an image file', 'Upload Error');
                return;
            }
            
            const form = new FormData();
            form.append('picture', file);
            
            const uploadBtn = document.querySelector('.btn-edit');
            uploadBtn.disabled = true;
            uploadBtn.textContent = '⏳ Uploading...';
            
            fetch('api/update-profile.php', { method: 'POST', body: form })
                .then(r => r.json())
                .then(d => {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = '✎ Edit Profile';
                    if (d.success) {
                        const img = document.getElementById('profilePicImg');
                        const text = document.getElementById('profileAvatarText');
                        img.src = d.picture_url + '?t=' + Date.now();
                        img.style.display = 'block';
                        text.style.display = 'none';
                        showMessage('Profile picture updated!', 'Success');
                    } else {
                        showMessage('Error: ' + (d.message || 'Unknown error'), 'Update Failed');
                    }
                    picInput.value = '';
                })
                .catch(e => {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = '✎ Edit Profile';
                    showMessage('Error: ' + e.message, 'Update Failed');
                    console.error(e);
                    picInput.value = '';
                });
        });
        
        async function editName() {
            const result = await askText(
                'Enter your name:',
                '<?php echo isset($userName) ? htmlspecialchars($userName) : 'User'; ?>',
                'Edit Profile Name'
            );

            if (result.confirmed && result.value && result.value.trim()) {
                const form = new FormData();
                form.append('name', result.value.trim());
                fetch('api/update-profile.php', { method: 'POST', body: form })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            showMessage('Profile updated!', 'Success').then(() => location.reload());
                        } else {
                            showMessage('Error: ' + (d.message || 'Unknown error'), 'Update Failed');
                        }
                    })
                    .catch(e => {
                        showMessage('Error: ' + e.message, 'Update Failed');
                        console.error(e);
                    });
            }
        }
    </script>
</body>
</html>

