// ============================================
// SIGNUP PAGE FUNCTIONALITY
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Signup Page Loaded');
    
    initializePasswordToggles();
    initializePasswordValidation();
    initializeEmailValidation();
    initializeSignupForm();
    initializeSocialSignup();
    initializeTermsCheckbox();
});

// ============================================
// PASSWORD TOGGLE
// ============================================

function initializePasswordToggles() {
    const toggles = [
        { btn: document.getElementById('passwordToggle1'), input: document.getElementById('password') },
        { btn: document.getElementById('passwordToggle2'), input: document.getElementById('confirmPassword') }
    ];

    toggles.forEach(toggle => {
        if (toggle.btn && toggle.input) {
            toggle.btn.addEventListener('click', function(e) {
                e.preventDefault();
                togglePasswordVisibility(this, toggle.input);
            });
        }
    });
}

function togglePasswordVisibility(btn, input) {
    const type = input.type === 'password' ? 'text' : 'password';
    input.type = type;
    btn.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}

// ============================================
// PASSWORD STRENGTH VALIDATOR
// ============================================

function calculatePasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength++;
    return strength; // 0-6 scale
}

// ============================================
// PASSWORD STRENGTH FEEDBACK
// ============================================

function initializePasswordValidation() {
    const passwordInput = document.getElementById('password');
    const passwordHint = document.getElementById('passwordHint');

    if (!passwordInput || !passwordHint) return;

    passwordInput.addEventListener('input', function() {
        updatePasswordHint(this.value, passwordHint);
    });

    passwordInput.addEventListener('blur', function() {
        updatePasswordHint(this.value, passwordHint);
    });
}

function updatePasswordHint(password, hintElement) {
    const strength = calculatePasswordStrength(password);
    let hints = [];

    if (password.length < 8) hints.push('8+ characters');
    if (!/[A-Z]/.test(password)) hints.push('uppercase letter');
    if (!/[a-z]/.test(password)) hints.push('lowercase letter');
    if (!/\d/.test(password)) hints.push('number');
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) hints.push('symbol');

    if (hints.length === 0 && password.length > 0) {
        hintElement.textContent = '✓ Strong password';
        hintElement.className = 'form-hint success';
    } else if (hints.length > 0) {
        hintElement.textContent = `Missing: ${hints.join(', ')}`;
        hintElement.className = 'form-hint error';
    } else {
        hintElement.textContent = 'Minimum 8 characters with uppercase, number & symbol';
        hintElement.className = 'form-hint';
    }
}

// ============================================
// EMAIL VALIDATION & AVAILABILITY CHECK
// ============================================

function initializeEmailValidation() {
    const emailInput = document.getElementById('email');
    if (!emailInput) return;

    emailInput.addEventListener('blur', function() {
        checkEmailAvailability(this.value);
    });
}

function checkEmailAvailability(email) {
    const emailHint = document.getElementById('emailHint');
    if (!emailHint) return;

    if (!email) {
        emailHint.textContent = '';
        emailHint.className = 'form-hint';
        return;
    }

    if (!isValidEmail(email)) {
        emailHint.textContent = 'Please enter a valid email';
        emailHint.className = 'form-hint error';
        return;
    }

    emailHint.textContent = '✓ Looks good';
    emailHint.className = 'form-hint success';
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// ============================================
// SIGNUP FORM HANDLING
// ============================================

function initializeSignupForm() {
    const form = document.getElementById('signupForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const firstName = document.getElementById('firstName').value.trim();
        const lastName = document.getElementById('lastName').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const acceptTerms = document.getElementById('acceptTerms').checked;

        // Validation
        if (!firstName || !lastName) {
            showError('First and last name are required (min 2 characters each)');
            return;
        }

        if (firstName.length < 2 || lastName.length < 2) {
            showError('Name must be at least 2 characters');
            return;
        }

        if (!isValidEmail(email)) {
            showError('Please enter a valid email address');
            return;
        }

        if (password.length < 8) {
            showError('Password must be at least 8 characters');
            return;
        }

        const strength = calculatePasswordStrength(password);
        if (strength < 4) {
            showError('Password must contain uppercase letter, number, and symbol');
            return;
        }

        if (password !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }

        if (!acceptTerms) {
            showError('You must accept the Terms & Conditions');
            return;
        }

        handleSignup(firstName, lastName, email, password);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            form.dispatchEvent(new Event('submit'));
        }
    });
}

// ============================================
// SIGNUP HANDLER
// ============================================

function handleSignup(firstName, lastName, email, password) {
    const signupBtn = document.querySelector('.btn-login');

    // Disable button and show loading state
    signupBtn.disabled = true;
    signupBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';

    // Send signup request to backend
    const formData = new FormData();
    formData.append('firstName', firstName);
    formData.append('lastName', lastName);
    formData.append('email', email);
    formData.append('password', password);
    formData.append('confirmPassword', password);

    fetch('api/signup.php', {
        method: 'POST',
        body: formData
    })
    .then(response =>
        response.text().then(text => {
            try { return JSON.parse(text); }
            catch (e) { throw new Error('Server error: ' + text.substring(0, 150)); }
        })
    )
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => {
                window.location.href = data.redirect || 'index.php';
            }, 1500);
        } else {
            showError(data.message || 'Signup failed. Please try again.');
            signupBtn.disabled = false;
            signupBtn.innerHTML = 'Create Account';
        }
    })
    .catch(error => {
        showError(error.message || 'An error occurred. Please try again.');
        signupBtn.disabled = false;
        signupBtn.innerHTML = 'Create Account';
    });
}

// ============================================
// SOCIAL SIGNUP HANDLERS
// ============================================

function initializeSocialSignup() {
    const socialBtns = document.querySelectorAll('.social-btn');

    socialBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const provider = this.classList[1] || 'Social';
            showSuccess(`Signing up with ${provider.charAt(0).toUpperCase() + provider.slice(1)}...`);
        });
    });
}

// ============================================
// TERMS & CONDITIONS
// ============================================

function initializeTermsCheckbox() {
    const termsLinks = document.querySelectorAll('a[href="#terms"]');

    termsLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            showSuccess('Terms & Conditions viewed');
        });
    });
}

// ============================================
// NOTIFICATION SYSTEM
// ============================================

function showError(message) {
    showNotification(message, 'error');
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showNotification(message, type = 'success') {
    // Remove existing notification
    removeNotification();

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(notification);
    
    // Add inline styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'error' ? '#ff6b6b' : '#51cf66'};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 10000;
        animation: slideInRight 0.3s ease;
    `;

    setTimeout(() => removeNotification(), 3000);
}

function removeNotification() {
    const notification = document.querySelector('.notification');
    if (notification) {
        notification.remove();
    }
}

// Add CSS animation
if (!document.querySelector('style[data-notification]')) {
    const style = document.createElement('style');
    style.setAttribute('data-notification', 'true');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}


