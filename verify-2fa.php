<?php
session_start();

// If already fully authenticated, redirect to profile
if (!empty($_SESSION['user_id'])) {
    header('Location: profile.php', true, 302);
    exit;
}

// If no pending 2FA verification, redirect to login
if (empty($_SESSION['pending_2fa_user_id'])) {
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
    <title>Two-Factor Authentication - BW Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <style>
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 25px 0;
        }
        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        .code-input:focus {
            outline: none;
            border-color: #5bbcff;
            background: rgba(91, 188, 255, 0.1);
        }
        .code-input.filled {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.1);
        }
        .verification-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #5bbcff, #2f5fa7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #fff;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #a0a0a0;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: #5bbcff;
        }
        .timer-text {
            text-align: center;
            color: #a0a0a0;
            font-size: 12px;
            margin-top: 15px;
        }
        .timer-text span {
            color: #f4d03f;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="bg-gradient" aria-hidden="true"></div>

    <div class="login-container">
        <div class="login-card" role="main">
            <div class="card-border" aria-hidden="true"></div>

            <div class="login-content">
                <div class="verification-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>

                <h1 class="login-title">Two-Factor Authentication</h1>
                <p class="login-subtitle">Enter the 6-digit code from your authenticator app</p>

                <form id="verifyForm" novalidate>
                    <div class="code-inputs">
                        <input type="text" class="code-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                        <input type="text" class="code-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                        <input type="text" class="code-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                        <input type="text" class="code-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                        <input type="text" class="code-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                        <input type="text" class="code-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                    </div>

                    <div id="errorMessage" class="error-toast" style="display: none;"></div>

                    <button type="submit" class="login-btn" id="verifyBtn">
                        <span class="btn-text">Verify</span>
                        <span class="btn-loader" aria-hidden="true"></span>
                    </button>

                    <div class="timer-text">
                        Code refreshes every <span>30 seconds</span>
                    </div>
                </form>

                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        const inputs = document.querySelectorAll('.code-input');
        const form = document.getElementById('verifyForm');
        const verifyBtn = document.getElementById('verifyBtn');
        const errorMessage = document.getElementById('errorMessage');

        // Auto-focus first input
        inputs[0].focus();

        // Handle input
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                
                // Only allow digits
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }

                if (value) {
                    input.classList.add('filled');
                    // Move to next input
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    } else {
                        // Auto-submit when all filled
                        const code = getCode();
                        if (code.length === 6) {
                            form.dispatchEvent(new Event('submit'));
                        }
                    }
                } else {
                    input.classList.remove('filled');
                }
            });

            input.addEventListener('keydown', (e) => {
                // Handle backspace
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                    inputs[index - 1].classList.remove('filled');
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g, '').slice(0, 6);
                
                digits.split('').forEach((digit, i) => {
                    if (inputs[i]) {
                        inputs[i].value = digit;
                        inputs[i].classList.add('filled');
                    }
                });

                if (digits.length === 6) {
                    inputs[5].focus();
                    form.dispatchEvent(new Event('submit'));
                } else if (digits.length > 0) {
                    inputs[Math.min(digits.length, 5)].focus();
                }
            });
        });

        function getCode() {
            return Array.from(inputs).map(i => i.value).join('');
        }

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            errorMessage.style.background = 'rgba(231, 76, 60, 0.1)';
            errorMessage.style.color = '#e74c3c';
            errorMessage.style.padding = '12px';
            errorMessage.style.borderRadius = '8px';
            errorMessage.style.marginBottom = '15px';
            errorMessage.style.textAlign = 'center';
            
            // Shake inputs
            inputs.forEach(input => {
                input.style.borderColor = '#e74c3c';
                input.classList.remove('filled');
                input.value = '';
            });
            inputs[0].focus();
            
            setTimeout(() => {
                inputs.forEach(input => {
                    input.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                });
            }, 500);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const code = getCode();
            if (code.length !== 6) {
                showError('Please enter all 6 digits');
                return;
            }

            verifyBtn.classList.add('loading');
            verifyBtn.disabled = true;
            errorMessage.style.display = 'none';

            try {
                const formData = new FormData();
                formData.append('code', code);

                const response = await fetch('api/verify-2fa.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = data.redirect || 'profile.php';
                } else {
                    showError(data.message || 'Invalid code');
                    verifyBtn.classList.remove('loading');
                    verifyBtn.disabled = false;
                }
            } catch (error) {
                showError('Connection error. Please try again.');
                verifyBtn.classList.remove('loading');
                verifyBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
