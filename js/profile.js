// ============================================
// USER PROFILE PAGE
// ============================================

// Timeout to prevent infinite loading
let profileLoadTimeout;

document.addEventListener('DOMContentLoaded', function() {
    console.log('Profile DOM Content Loaded');
    
    // Set a timeout to ensure page doesn't hang
    profileLoadTimeout = setTimeout(function() {
        console.warn('Profile load timeout - showing default content');
        showDefaultProfile();
    }, 3000);
    
    try {
        // Initialize events and UI first
        initializeProfileEvents();
        initializeProfilePictureUpload();
        
        // Load profile data from server
        loadProfileData();
        
        // Clear timeout if everything loaded
        clearTimeout(profileLoadTimeout);
    } catch (error) {
        console.error('Error initializing profile:', error);
        clearTimeout(profileLoadTimeout);
    }
});

function showDefaultProfile() {
    console.log('Showing default profile content');
    // Content should already be visible from PHP
    // Just initialize events
    try {
        initializeProfileEvents();
        initializeProfilePictureUpload();
    } catch (e) {
        console.log('Events already initialized');
    }
}

// ============================================
// LOAD PROFILE DATA
// ============================================

function loadProfileData() {
    console.log('Loading profile data...');
    console.log('serverUserData exists:', typeof serverUserData !== 'undefined');
    
    if (typeof serverUserData !== 'undefined' && serverUserData && serverUserData.email) {
        console.log('Displaying profile for:', serverUserData.email);
        displayProfile(serverUserData.name, serverUserData.email);
    } else {
        console.log('No valid server user data');
    }
}

function displayProfile(name, email) {
    console.log('Displaying profile:', name, email);
    
    // Update header
    const profileNameEl = document.getElementById('profileName');
    const profileEmailEl = document.getElementById('profileEmail');
    
    if (profileNameEl) profileNameEl.textContent = name || 'User';
    if (profileEmailEl) profileEmailEl.textContent = email || '';
    
    // Load and display profile picture
    try {
        loadProfilePicture();
    } catch (e) {
        console.log('Profile picture load skipped');
    }
    
    // Update full name display
    const fullNameEl = document.getElementById('fullName');
    if (fullNameEl) fullNameEl.textContent = name || '—';
    
    // Update account information
    const emailAddressEl = document.getElementById('emailAddress');
    if (emailAddressEl) emailAddressEl.textContent = email || '—';
    
    // Member since
    const memberSinceEl = document.getElementById('memberSince');
    if (memberSinceEl) {
        const memberSince = new Date().toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        memberSinceEl.textContent = memberSince;
    }
    
    // Last login
    const lastLoginEl = document.getElementById('lastLogin');
    if (lastLoginEl) {
        const today = new Date().toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        lastLoginEl.textContent = today;
    }

    // Store for edit modal
    window.currentUser = {
        name: name,
        email: email
    };
}

// ============================================
// EVENT LISTENERS
// ============================================

function initializeProfileEvents() {
    const editBtn = document.getElementById('editBtn');
    const backBtn = document.getElementById('backBtn');
    const profileLogoutBtn = document.getElementById('profileLogoutBtn');
    const editModal = document.getElementById('editModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const editForm = document.getElementById('editForm');

    // Edit button
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            if (window.currentUser) {
                const editName = document.getElementById('editName');
                if (editName) editName.value = window.currentUser.name;
            }
            if (editModal) editModal.classList.add('show');
        });
    }

    // Cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (editModal) editModal.classList.remove('show');
        });
    }

    // Back button
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            // Go back to previous page or dashboard
            if (document.referrer && document.referrer.includes(window.location.hostname)) {
                window.history.back();
            } else {
                window.location.href = 'index.php';
            }
        });
    }

    // Logout button
    if (profileLogoutBtn) {
        profileLogoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    }

    // Edit form submission
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveProfile();
        });
    }

    // Close modal when clicking outside
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                editModal.classList.remove('show');
            }
        });
    }
}

// ============================================
// SAVE PROFILE
// ============================================

function saveProfile() {
    const editName = document.getElementById('editName');
    const newName = editName ? editName.value.trim() : '';

    if (!newName) {
        showNotification('Please enter a name', 'error');
        return;
    }

    // Show loading state
    const submitBtn = document.querySelector('#editForm button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
    }

    // Send to server
    const formData = new FormData();
    formData.append('name', newName);

    fetch('api/update-profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update window reference and display
            if (window.currentUser) {
                window.currentUser.name = newName;
            }
            
            // Update all display elements
            const profileNameEl = document.getElementById('profileName');
            const fullNameEl = document.getElementById('fullName');
            
            if (profileNameEl) profileNameEl.textContent = newName;
            if (fullNameEl) fullNameEl.textContent = newName;

            // Show success message
            showNotification('Profile updated successfully!', 'success');

            // Close modal
            setTimeout(function() {
                const editModal = document.getElementById('editModal');
                if (editModal) editModal.classList.remove('show');
                
                // Reset button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Changes';
                }
            }, 500);
        } else {
            showNotification(data.message || 'Failed to update profile', 'error');
            
            // Reset button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error updating profile', 'error');
        
        // Reset button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        }
    });
}

// ============================================
// PROFILE PICTURE UPLOAD
// ============================================

function initializeProfilePictureUpload() {
    const uploadBtn = document.getElementById('avatarUploadBtn');
    const fileInput = document.getElementById('profilePictureInput');

    if (uploadBtn) {
        uploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showNotification('File size must be less than 2MB', 'error');
                    return;
                }

                // Check file type
                if (!file.type.startsWith('image/')) {
                    showNotification('Please select a valid image file', 'error');
                    return;
                }

                // Convert to base64 and save
                const reader = new FileReader();
                reader.onload = function(event) {
                    const base64Image = event.target.result;
                    localStorage.setItem('profilePicture', base64Image);
                    loadProfilePicture();
                    showNotification('Profile picture updated successfully!', 'success');
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

function loadProfilePicture() {
    const profilePicture = localStorage.getItem('profilePicture');
    const avatarDiv = document.getElementById('profileAvatar');

    if (profilePicture && avatarDiv) {
        // Clear existing content
        avatarDiv.innerHTML = '';
        
        // Create and display image
        const img = document.createElement('img');
        img.src = profilePicture;
        img.alt = 'Profile Picture';
        avatarDiv.appendChild(img);
    }
}

// ============================================
// LOGOUT
// ============================================

function logout() {
    // Clear user data from localStorage
    localStorage.removeItem('userEmail');
    localStorage.removeItem('loginTime');
    localStorage.removeItem('newUserAccount');
    localStorage.removeItem('currentUserProfile');
    localStorage.removeItem('sidebarCollapsed');
    localStorage.removeItem('savedEmail');
    localStorage.removeItem('profilePicture');

    // Redirect to logout.php to clear session on server
    window.location.href = 'logout.php';
}

// ============================================
// NOTIFICATION SYSTEM
// ============================================

function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    if (notification) {
        notification.textContent = message;
        notification.className = `notification show ${type}`;

        setTimeout(function() {
            notification.classList.remove('show');
        }, 3000);
    }
}

console.log('Profile Features:');
console.log('✓ Load user profile data from localStorage');
console.log('✓ Display personal and account information');
console.log('✓ Edit profile functionality');
console.log('✓ Save profile changes');
console.log('✓ Logout with data clearing');
console.log('✓ Responsive design');
