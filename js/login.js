// ============================================
// LOGIN PAGE FUNCTIONALITY
// ============================================

document.addEventListener('DOMContentLoaded', function () {
    initializePasswordToggle();
    initializeLoginForm();
    initializeRememberMe();
    initializeSocialHandlers();
    initializeForgotPassword();
});

// ============================================
// PASSWORD TOGGLE
// ============================================

function initializePasswordToggle() {
    const toggleBtn = document.getElementById('passwordToggle');
    const passwordInput = document.getElementById('password');

    if (!toggleBtn || !passwordInput) return;

    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye', !isPassword);
        icon.classList.toggle('fa-eye-slash', isPassword);
        this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
}

// ============================================
// REAL-TIME FIELD VALIDATION
// ============================================

function validateEmail(value) {
    if (!value) return 'Email address is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email address.';
    return '';
}

function validatePassword(value) {
    if (!value) return 'Password is required.';
    if (value.length < 6) return 'Password must be at least 6 characters.';
    return '';
}

function setFieldState(inputId, errorId, errorMsg) {
    const input = document.getElementById(inputId);
    const errorEl = document.getElementById(errorId);
    if (!input || !errorEl) return;

    if (errorMsg) {
        input.classList.add('input-error');
        input.classList.remove('input-valid');
        errorEl.textContent = errorMsg;
    } else {
        input.classList.remove('input-error');
        input.classList.add('input-valid');
        errorEl.textContent = '';
    }
}

function attachRealTimeValidation() {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    if (emailInput) {
        emailInput.addEventListener('blur', function () {
            setFieldState('email', 'email-error', validateEmail(this.value.trim()));
        });
        emailInput.addEventListener('input', function () {
            if (this.classList.contains('input-error')) {
                setFieldState('email', 'email-error', validateEmail(this.value.trim()));
            }
        });
    }

    if (passwordInput) {
        passwordInput.addEventListener('blur', function () {
            setFieldState('password', 'password-error', validatePassword(this.value));
        });
        passwordInput.addEventListener('input', function () {
            if (this.classList.contains('input-error')) {
                setFieldState('password', 'password-error', validatePassword(this.value));
            }
        });
    }
}

// ============================================
// LOGIN FORM HANDLING
// ============================================

function initializeLoginForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    attachRealTimeValidation();

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        const emailErr = validateEmail(email);
        const passErr = validatePassword(password);

        setFieldState('email', 'email-error', emailErr);
        setFieldState('password', 'password-error', passErr);

        if (emailErr || passErr) return;

        handleLogin(email, password);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.altKey && e.key === 'l') document.getElementById('email')?.focus();
        if (e.ctrlKey && e.key === 'Enter') form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });
}

// ============================================
// LOGIN HANDLER
// ============================================

let isAccountLocked = false;
let lockedEmail = '';

function handleLogin(email, password) {
    const loginBtn = document.getElementById('loginBtn');
    const btnText = loginBtn?.querySelector('.btn-text');

    if (loginBtn) {
        loginBtn.disabled = true;
        loginBtn.style.minWidth = loginBtn.offsetWidth + 'px';
    }
    if (btnText) btnText.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Signing in…';

    const formData = new FormData();
    formData.append('email', email);
    formData.append('password', password);

    fetch('api/authenticate.php', {
        method: 'POST',
        body: formData
    })
        .then(response =>
            response.text().then(text => {
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Unexpected server response. Please try again.'); }
            })
        )
        .then(data => {
            if (data.success) {
                const rememberMe = document.getElementById('rememberMe');
                if (rememberMe?.checked) {
                    localStorage.setItem('savedEmail', email);
                } else {
                    localStorage.removeItem('savedEmail');
                }

                if (btnText) btnText.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Success!';
                showNotification(data.message || 'Login successful!', 'success');

                setTimeout(() => { window.location.href = data.redirect || 'index.php'; }, 1200);
            } else {
                // Check if account is locked
                if (data.locked) {
                    isAccountLocked = true;
                    lockedEmail = email;
                    showUnlockCodeInput(email, data.attempts, data.emailSent, data.unlockCode);
                } else if (data.remaining !== undefined && data.remaining <= 2) {
                    // Show warning for remaining attempts
                    showNotification(`⚠️ ${data.message}`, 'warning');
                    setFieldState('password', 'password-error', `${data.remaining} attempt(s) remaining before account lock.`);
                } else {
                    showNotification(data.message || 'Invalid credentials. Please try again.', 'error');
                    setFieldState('password', 'password-error', 'Check your email and password and try again.');
                }
                resetLoginButton(loginBtn, btnText);
            }
        })
        .catch(error => {
            showNotification(error.message, 'error');
            resetLoginButton(loginBtn, btnText);
        });
}

function showUnlockCodeInput(email, attempts, emailSent, unlockCode = null) {
    // Hide password field and show unlock code input
    const passwordGroup = document.querySelector('.form-group:has(#password)') || document.getElementById('password')?.closest('.form-group');
    const loginBtn = document.getElementById('loginBtn');
    const form = document.getElementById('loginForm');
    
    // Determine message based on email status and unlock code
    let statusMessage = '';
    if (emailSent) {
        statusMessage = '<p class="email-sent"><i class="fas fa-envelope"></i> A 6-digit unlock code has been sent to your email.</p>';
    } else if (unlockCode) {
        statusMessage = `<p class="email-not-sent"><i class="fas fa-exclamation-triangle"></i> SMTP not configured. Use this code: <strong style="font-size: 24px; letter-spacing: 3px; color: #f4d03f;">${unlockCode}</strong></p>`;
    } else {
        statusMessage = '<p class="email-not-sent"><i class="fas fa-exclamation-triangle"></i> Email notification could not be sent. Wait 15 minutes or contact admin.</p>';
    }
    
    // Create unlock code section if it doesn't exist
    let unlockSection = document.getElementById('unlockCodeSection');
    if (!unlockSection) {
        unlockSection = document.createElement('div');
        unlockSection.id = 'unlockCodeSection';
        unlockSection.className = 'unlock-code-section';
        unlockSection.innerHTML = `
            <div class="lock-alert">
                <div class="lock-icon">🔐</div>
                <h3>Account Locked</h3>
                <p>Your account has been temporarily locked after ${attempts} failed login attempts.</p>
                ${statusMessage}
            </div>
            <div class="form-group">
                <label for="unlockCode">UNLOCK CODE</label>
                <div class="input-wrapper unlock-input">
                    <i class="fas fa-key"></i>
                    <input type="text" id="unlockCode" name="unlockCode" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code">
                </div>
                <span class="field-error" id="unlock-error"></span>
            </div>
            <button type="button" id="verifyUnlockBtn" class="btn-login">
                <span class="btn-text">Verify Code</span>
            </button>
            <button type="button" id="backToLoginBtn" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Login
            </button>
        `;
        
        if (passwordGroup) {
            passwordGroup.style.display = 'none';
        }
        if (loginBtn) {
            loginBtn.style.display = 'none';
        }
        
        // Insert after email field
        const emailGroup = document.querySelector('.form-group:has(#email)') || document.getElementById('email')?.closest('.form-group');
        if (emailGroup) {
            emailGroup.after(unlockSection);
        } else if (form) {
            form.appendChild(unlockSection);
        }
        
        // Add event listeners
        document.getElementById('verifyUnlockBtn').addEventListener('click', verifyUnlockCode);
        document.getElementById('backToLoginBtn').addEventListener('click', hideUnlockCodeInput);
        document.getElementById('unlockCode').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        document.getElementById('unlockCode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyUnlockCode();
            }
        });
        
        // Focus on unlock code input
        setTimeout(() => document.getElementById('unlockCode')?.focus(), 100);
    } else {
        unlockSection.style.display = 'block';
        if (passwordGroup) passwordGroup.style.display = 'none';
        if (loginBtn) loginBtn.style.display = 'none';
    }
    
    // Disable email field
    const emailInput = document.getElementById('email');
    if (emailInput) emailInput.readOnly = true;
}

function hideUnlockCodeInput() {
    const unlockSection = document.getElementById('unlockCodeSection');
    const passwordGroup = document.querySelector('.form-group:has(#password)') || document.getElementById('password')?.closest('.form-group');
    const loginBtn = document.getElementById('loginBtn');
    const emailInput = document.getElementById('email');
    
    if (unlockSection) unlockSection.style.display = 'none';
    if (passwordGroup) passwordGroup.style.display = '';
    if (loginBtn) loginBtn.style.display = '';
    if (emailInput) emailInput.readOnly = false;
    
    isAccountLocked = false;
    lockedEmail = '';
    
    // Clear any errors
    setFieldState('password', 'password-error', '');
}

function verifyUnlockCode() {
    const code = document.getElementById('unlockCode')?.value.trim();
    const email = lockedEmail || document.getElementById('email')?.value.trim();
    const verifyBtn = document.getElementById('verifyUnlockBtn');
    const btnText = verifyBtn?.querySelector('.btn-text');
    
    if (!code || code.length !== 6) {
        setFieldState('unlockCode', 'unlock-error', 'Please enter the 6-digit code from your email.');
        return;
    }
    
    if (verifyBtn) verifyBtn.disabled = true;
    if (btnText) btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    
    const formData = new FormData();
    formData.append('action', 'verify-unlock-code');
    formData.append('email', email);
    formData.append('code', code);
    
    fetch('api/security-alert.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('✅ ' + data.message + ' You can now login.', 'success');
                hideUnlockCodeInput();
                // Clear the password field so user can re-enter
                const passwordInput = document.getElementById('password');
                if (passwordInput) {
                    passwordInput.value = '';
                    passwordInput.focus();
                }
            } else {
                setFieldState('unlockCode', 'unlock-error', data.message || 'Invalid code. Please try again.');
                showNotification('❌ ' + (data.message || 'Invalid code'), 'error');
            }
            
            if (verifyBtn) verifyBtn.disabled = false;
            if (btnText) btnText.innerHTML = 'Verify Code';
        })
        .catch(error => {
            showNotification('Error verifying code: ' + error.message, 'error');
            if (verifyBtn) verifyBtn.disabled = false;
            if (btnText) btnText.innerHTML = 'Verify Code';
        });
}

function resetLoginButton(loginBtn, btnText) {
    if (loginBtn) { loginBtn.disabled = false; loginBtn.style.minWidth = ''; }
    if (btnText) btnText.innerHTML = 'Login';
}

// ============================================
// REMEMBER ME
// ============================================

function initializeRememberMe() {
    const rememberCheckbox = document.getElementById('rememberMe');
    const emailInput = document.getElementById('email');
    if (!rememberCheckbox || !emailInput) return;

    const savedEmail = localStorage.getItem('savedEmail');
    if (savedEmail) {
        emailInput.value = savedEmail;
        rememberCheckbox.checked = true;
    }
}

// ============================================
// SOCIAL HANDLERS
// ============================================

function initializeSocialHandlers() {
    const socialBtns = document.querySelectorAll('.social-btn');
    socialBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const provider = this.classList[1] || 'social';
            showNotification(`${capitalize(provider)} sign-in is not configured yet.`, 'info');
        });
    });
}

// ============================================
// FORGOT PASSWORD
// ============================================

function initializeForgotPassword() {
    const forgotLink = document.getElementById('forgotPasswordLink');
    if (!forgotLink) return;

    forgotLink.addEventListener('click', function (e) {
        e.preventDefault();
        const email = document.getElementById('email').value.trim();
        if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showNotification(`A password reset link has been sent to ${email}.`, 'success');
        } else {
            document.getElementById('email')?.focus();
            setFieldState('email', 'email-error', 'Enter your email address above first.');
        }
    });
}

// ============================================
// NOTIFICATION SYSTEM
// ============================================

function showNotification(message, type = 'success') {
    removeNotification();

    const icons   = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
    const colors  = { success: '#51cf66', error: '#ff6b6b', info: '#00d9ff' };
    const bgs     = { success: '#102a18', error: '#2a1020', info: '#0d1a30' };

    const notification = document.createElement('div');
    notification.className = `toast-notification toast-${type}`;
    notification.setAttribute('role', 'alert');
    notification.setAttribute('aria-live', 'assertive');
    notification.innerHTML = `
        <i class="fas ${icons[type] || icons.info}" aria-hidden="true"></i>
        <span class="toast-message">${escapeHtml(message)}</span>
        <button class="toast-close" aria-label="Dismiss" onclick="removeNotification()">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    `;

    Object.assign(notification.style, {
        position: 'fixed', top: '20px', right: '20px',
        background: bgs[type] || bgs.info,
        borderLeft: `4px solid ${colors[type] || colors.info}`,
        color: colors[type] || colors.info,
        padding: '13px 16px', borderRadius: '10px',
        display: 'flex', alignItems: 'center', gap: '10px',
        zIndex: '10000', maxWidth: '340px',
        boxShadow: '0 8px 24px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05)',
        animation: 'toastSlideIn 0.3s cubic-bezier(0.4,0,0.2,1)',
        fontSize: '13px', fontFamily: 'Poppins, sans-serif'
    });

    document.body.appendChild(notification);
    notification._dismissTimer = setTimeout(removeNotification, 4000);
}

function removeNotification() {
    const n = document.querySelector('.toast-notification');
    if (!n) return;
    clearTimeout(n._dismissTimer);
    n.style.animation = 'toastSlideOut 0.25s cubic-bezier(0.4,0,0.2,1) forwards';
    setTimeout(() => n?.remove(), 260);
}

// ============================================
// HELPERS
// ============================================

function capitalize(str) { return str.charAt(0).toUpperCase() + str.slice(1); }

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

(function injectToastStyles() {
    if (document.querySelector('style[data-toast]')) return;
    const style = document.createElement('style');
    style.setAttribute('data-toast', 'true');
    style.textContent = `
        @keyframes toastSlideIn  { from { transform:translateX(380px); opacity:0 } to { transform:translateX(0); opacity:1 } }
        @keyframes toastSlideOut { from { transform:translateX(0);     opacity:1 } to { transform:translateX(380px); opacity:0 } }
        .toast-close { background:none; border:none; color:inherit; cursor:pointer; padding:2px 4px; margin-left:auto; opacity:.7; font-size:12px; flex-shrink:0; }
        .toast-close:hover { opacity:1; }
        .toast-message { flex:1; line-height:1.4; }
    `;
    document.head.appendChild(style);
}());
