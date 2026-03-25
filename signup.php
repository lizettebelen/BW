<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Sign Up - BW Gas Detector Sales Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <script>
        // Remove any stray '/label>' text that may appear from stale/cached/injected markup.
        document.addEventListener('DOMContentLoaded', function () {
            function cleanupStrayLabelText() {
                const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
                const nodes = [];
                while (walker.nextNode()) {
                    const n = walker.currentNode;
                    if (n.nodeValue && n.nodeValue.indexOf('/label>') !== -1) {
                        const cleaned = n.nodeValue.replace('/label>', '').trim();
                        if (cleaned.length === 0) {
                            nodes.push({ node: n, remove: true });
                        } else {
                            nodes.push({ node: n, remove: false, text: cleaned });
                        }
                    }
                }

                nodes.forEach(function (item) {
                    if (item.remove) {
                        item.node.remove();
                    } else {
                        item.node.nodeValue = item.text;
                    }
                });
            }

            cleanupStrayLabelText();

            const observer = new MutationObserver(function () {
                cleanupStrayLabelText();
            });

            observer.observe(document.body, { childList: true, subtree: true, characterData: true });

            setTimeout(function () {
                observer.disconnect();
            }, 5000);
        });
    </script>

    <!-- Gradient Background -->
    <div class="bg-gradient"></div>

    <!-- Main Container -->
    <div class="login-container">
        <!-- Login Card -->
        <div class="login-card">
            <!-- Gradient Top Border -->
            <div class="card-border"></div>

            <!-- Signup Form Content -->
            <div class="login-content">
                <h1 class="login-title">Create Account</h1>
                <p class="login-subtitle">Join us and start managing your sales today</p>

                <!-- Signup Form -->
                <form class="login-form" id="signupForm">
                    <!-- First Name & Last Name Row -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <!-- First Name -->
                        <div class="form-group">
                            <label for="firstName" class="form-label">First Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="firstName" 
                                    name="firstName" 
                                    class="form-input" 
                                    placeholder="John" 
                                    required
                                >
                            </div>
                        </div>

                        <!-- Last Name -->
                        <div class="form-group">
                            <label for="lastName" class="form-label">Last Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="lastName" 
                                    name="lastName" 
                                    class="form-input" 
                                    placeholder="Doe" 
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Email Field -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input" 
                                placeholder="john.doe@example.com" 
                                required
                            >
                        </div>
                        <small class="form-hint" id="emailHint"></small>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="Create a strong password" 
                                required
                            >
                            <button type="button" class="password-toggle" id="passwordToggle1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-hint" id="passwordHint">Minimum 8 characters with uppercase, number & symbol</small>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="form-group confirm-password-group">
                        <label for="confirmPassword" class="form-label">Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="confirmPassword" 
                                name="confirmPassword" 
                                class="form-input" 
                                placeholder="Confirm your password" 
                                required
                            >
                            <button type="button" class="password-toggle" id="passwordToggle2">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" id="acceptTerms" name="acceptTerms" required>
                            <span>I agree to the <a href="#">Terms & Conditions</a></span>
                        </label>
                    </div>

                    <!-- Signup Button -->
                    <button type="submit" class="btn-login">Create Account</button>
                </form>

                <!-- Login Link -->
                <div class="signup-link">
                    Already have an account? <a href="login.php">Sign in here</a>
                </div>

            </div>
        </div>

        <!-- Floating Elements for Background Design -->
        <div class="floating-element float-1"></div>
        <div class="floating-element float-2"></div>
        <div class="floating-element float-3"></div>
    </div>

    <script src="js/signup.js?v=20260325a"></script>
</body>
</html>
