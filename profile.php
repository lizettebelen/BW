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
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #2f5fa7;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            border: 4px solid #f4d03f;
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
                    <div class="profile-avatar">👤</div>
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

    <script src="js/app.js" defer></script>
    <script>
        function editName() {
            const newName = prompt('Enter your name:', '<?php echo isset($userName) ? htmlspecialchars($userName) : 'User'; ?>');
            if (newName && newName.trim()) {
                const form = new FormData();
                form.append('name', newName.trim());
                fetch('api/update-profile.php', { method: 'POST', body: form })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            alert('Profile updated!');
                            location.reload();
                        } else {
                            alert('Error: ' + (d.message || 'Unknown error'));
                        }
                    })
                    .catch(e => {
                        alert('Error: ' + e.message);
                        console.error(e);
                    });
            }
        }
    </script>
</body>
</html>

