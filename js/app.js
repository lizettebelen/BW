// ============================================
// BW GAS DETECTOR SALES DASHBOARD
// JavaScript for Interactivity & Charts
// ============================================

// Apply saved theme immediately (before DOMContentLoaded to avoid flash)
(function () {
    var theme = localStorage.getItem('theme');
    var isLight = theme !== 'dark'; // Default to light mode
    if (isLight) {
        document.documentElement.classList.add('light-mode');
        // Apply to body as soon as it exists
        if (document.body) {
            document.body.classList.add('light-mode');
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('light-mode');
            });
        }
    }
    
    // Apply saved accent color
    var accentColor = localStorage.getItem('accentColor');
    if (accentColor) {
        var colors = {
            gold: '#f4d03f',
            blue: '#3498db',
            green: '#27ae60',
            purple: '#9b59b6',
            red: '#e74c3c',
            orange: '#e67e22'
        };
        if (colors[accentColor]) {
            document.documentElement.style.setProperty('--color-accent', colors[accentColor]);
        }
    }
})();

// Set Chart.js global color defaults based on current theme
// (runs when deferred app.js executes, before DOMContentLoaded fires,
//  so all charts — including those in inline PHP scripts — inherit these defaults)
(function () {
    if (typeof Chart === 'undefined') return;
    var theme = localStorage.getItem('theme');
    var isLight = theme !== 'dark'; // Default to light mode
    Chart.defaults.color       = isLight ? '#3a3a5c' : '#e0e0e0';
    Chart.defaults.borderColor = isLight ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.05)';
})();

// Initialize when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard Initialized');
    
    // Ensure theme is applied to body
    var theme = localStorage.getItem('theme');
    var isLight = theme !== 'dark'; // Default to light mode
    if (isLight) {
        document.body.classList.add('light-mode');
    } else {
        document.body.classList.remove('light-mode');
    }
    
    // Apply display settings
    initializeDisplaySettings();
    
    // Initialize UI Elements
    initializeDarkModeToggle();
    initializeSidebarToggle();
    initializeSubmenuToggle();
    initializeProfileDropdown();
    initializeNotificationDropdown();
    initializeLogoutLoader();
    initializeResponsive();
    loadUserProfile();
    
    // Initialize All Charts
    initializeCharts();
    
    // Initialize Dataset Synchronization
    initializeDatasetSync();
});

// ============================================
// DISPLAY SETTINGS
// ============================================
function initializeDisplaySettings() {
    // Apply accent color
    var accentColor = localStorage.getItem('accentColor');
    if (accentColor) {
        var colors = {
            gold: '#f4d03f',
            blue: '#3498db',
            green: '#27ae60',
            purple: '#9b59b6',
            red: '#e74c3c',
            orange: '#e67e22'
        };
        if (colors[accentColor]) {
            document.documentElement.style.setProperty('--color-accent', colors[accentColor]);
        }
    }
    
    // Apply font size
    var fontSize = localStorage.getItem('fontSize');
    if (fontSize) {
        document.body.classList.remove('font-small', 'font-medium', 'font-large');
        document.body.classList.add('font-' + fontSize);
    }
    
    // Apply compact mode - check localStorage first, then fall back to fetching settings
    var compactMode = localStorage.getItem('compactMode');
    if (compactMode === 'true') {
        document.body.classList.add('compact-mode');
    }
    
    // Apply animations setting
    var animations = localStorage.getItem('animations');
    if (animations === 'false') {
        document.body.classList.add('no-animations');
    }
}

// ============================================
// SIDEBAR TOGGLE FUNCTIONALITY
// ============================================

// ============================================
// DARK MODE
// ============================================

function initializeDarkModeToggle() {
    const toggle = document.getElementById('darkModeToggle');
    if (!toggle) return;

    // Sync toggle state with current theme
    const theme = localStorage.getItem('theme');
    const isLight = theme !== 'dark'; // Default to light mode
    if (isLight) {
        toggle.classList.remove('active');
    } else {
        toggle.classList.add('active');
    }

    toggle.addEventListener('click', function () {
        const currentlyDark = !document.body.classList.contains('light-mode');
        if (currentlyDark) {
            // Switch to light
            document.documentElement.classList.add('light-mode');
            document.body.classList.add('light-mode');
            localStorage.setItem('theme', 'light');
            toggle.classList.remove('active');
            console.log('Switched to Light Mode');
        } else {
            // Switch to dark
            document.documentElement.classList.remove('light-mode');
            document.body.classList.remove('light-mode');
            localStorage.setItem('theme', 'dark');
            toggle.classList.add('active');
            console.log('Switched to Dark Mode');
        }
    });
}

function initializeSidebarToggle() {
    const hamburgerBtn = document.getElementById('hamburger') || document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (!hamburgerBtn) return;

    hamburgerBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        if (mainContent) mainContent.classList.toggle('sidebar-collapsed');

        // Store preference in localStorage
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    });

    // Check sidebar behavior setting
    const sidebarBehavior = localStorage.getItem('sidebarBehavior') || 'remember';
    
    if (sidebarBehavior === 'expanded') {
        // Always expanded
        sidebar.classList.remove('collapsed');
        if (mainContent) mainContent.classList.remove('sidebar-collapsed');
    } else if (sidebarBehavior === 'collapsed') {
        // Always collapsed
        sidebar.classList.add('collapsed');
        if (mainContent) mainContent.classList.add('sidebar-collapsed');
    } else {
        // Remember last state (default)
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('sidebar-collapsed');
        }
    }
}

// ============================================
// SUBMENU TOGGLE FUNCTIONALITY
// ============================================

function initializeSubmenuToggle() {
    const submenuToggles = document.querySelectorAll('.submenu-toggle');

    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenuId = this.getAttribute('data-submenu');
            const submenu = document.getElementById(submenuId);

            if (submenu) {
                submenu.classList.toggle('active');
                this.classList.toggle('active');
            }
        });
    });
}

// ============================================
// PROFILE DROPDOWN FUNCTIONALITY
// ============================================

// ============================================
// PROFILE DROPDOWN FUNCTIONALITY
// ============================================

function initializeProfileDropdown() {
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');

    if (!profileBtn || !profileMenu) {
        console.warn('Profile button or menu not found in DOM');
        return;
    }

    // Click handler for button
    profileBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close notification dropdown if open
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationDropdown) {
            notificationDropdown.classList.remove('show');
        }
        
        console.log('Profile button clicked, toggling dropdown');
        profileMenu.classList.toggle('active');
    });

    // Click handler for menu items to close dropdown
    profileMenu.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' || e.target.closest('a')) {
            profileMenu.classList.remove('active');
        }
    });

    // Click anywhere else to close dropdown
    document.addEventListener('click', function(e) {
        if (profileBtn && profileMenu && !profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove('active');
        }
    });

    // Keyboard support (Escape key)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && profileMenu.classList.contains('active')) {
            profileMenu.classList.remove('active');
            profileBtn.focus();
        }
    });
}

// ============================================
// NOTIFICATION DROPDOWN FUNCTIONALITY
// ============================================

function initializeNotificationDropdown() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileMenu = document.getElementById('profileMenu');

    if (!notificationBtn || !notificationDropdown) {
        console.warn('Notification button or dropdown not found in DOM');
        return;
    }

    // Click handler for notification bell
    notificationBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close profile dropdown if open
        if (profileMenu) {
            profileMenu.classList.remove('active');
        }
        
        notificationDropdown.classList.toggle('show');
    });

    // Click anywhere else to close dropdown
    document.addEventListener('click', function(e) {
        if (!notificationBtn.contains(e.target)) {
            notificationDropdown.classList.remove('show');
        }
    });

    // Keyboard support (Escape key)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && notificationDropdown.classList.contains('show')) {
            notificationDropdown.classList.remove('show');
        }
    });
}

// ============================================
// LOGOUT LOADER FUNCTIONALITY
// ============================================

function initializeLogoutLoader() {
    // Find all logout buttons
    const logoutLinks = document.querySelectorAll('a[href="logout.php"]');
    
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Create and show logout loader
            showLogoutLoader();
            
            // Navigate to logout after a short delay
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 500);
        });
    });
}

function showLogoutLoader() {
    // Remove existing loader if any
    const existingLoader = document.getElementById('logoutLoader');
    if (existingLoader) {
        existingLoader.remove();
    }
    
    // Add animation style if not already present
    if (!document.getElementById('logoutLoaderStyles')) {
        const styleTag = document.createElement('style');
        styleTag.id = 'logoutLoaderStyles';
        styleTag.textContent = `
            @keyframes logoutSpin {
                to { transform: rotate(360deg); }
            }
            .logout-spinner {
                animation: logoutSpin 1s linear infinite;
            }
        `;
        document.head.appendChild(styleTag);
    }
    
    // Create loader HTML
    const loader = document.createElement('div');
    loader.id = 'logoutLoader';
    loader.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999999;
        backdrop-filter: blur(5px);
    `;
    
    loader.innerHTML = `
        <div style="
            text-align: center;
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 300px;
        ">
            <div class="logout-spinner" style="
                width: 50px;
                height: 50px;
                border: 4px solid rgba(244, 208, 63, 0.2);
                border-top: 4px solid #f4d03f;
                border-radius: 50%;
                margin: 0 auto 20px;
            "></div>
            <h3 style="color: #fff; margin: 0 0 10px; font-size: 20px; font-family: 'Poppins', sans-serif;">Logging out...</h3>
            <p style="color: #a0a0a0; margin: 0; font-size: 14px; font-family: 'Poppins', sans-serif;">Please wait while we secure your session</p>
        </div>
    `;
    
    document.body.appendChild(loader);
    console.log('Logout loader displayed');
}

// Mark all notifications as read
function markAllRead(e) {
    e.stopPropagation();
    
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    unreadItems.forEach(item => {
        item.classList.remove('unread');
    });
    
    // Update badge count
    const badge = document.getElementById('notificationCount');
    if (badge) {
        badge.textContent = '0';
        badge.style.display = 'none';
    }
}

// ============================================
// RESPONSIVE INITIALIZATION
// ============================================

function initializeResponsive() {
    // Handle window resize
    window.addEventListener('resize', function() {
        // Add any responsive adjustments here
    });
}

// ============================================
// USER PROFILE LOADING
// ============================================

function loadUserProfile() {
    const userEmail = localStorage.getItem('userEmail');
    const userData = localStorage.getItem('newUserAccount');
    
    if (!userData && userEmail) {
        const name = userEmail.split('@')[0].charAt(0).toUpperCase() + userEmail.split('@')[0].slice(1);
        updateProfileDisplay(name, null, userEmail);
    } else if (userData) {
        const user = JSON.parse(userData);
        updateProfileDisplay(user.firstName, user.lastName, user.email);
    }
}

function updateProfileDisplay(firstName, lastName, email) {
    const profileNameEl = document.querySelector('.profile-name');
    const welcomeNameEl = document.querySelector('.welcome-name');
    
    if (firstName && lastName) {
        const displayName = `${firstName} ${lastName}`;
        if (profileNameEl) profileNameEl.textContent = displayName;
        if (welcomeNameEl) welcomeNameEl.textContent = `Congratulations ${firstName}!`;
        // Store profile info for profile page
        localStorage.setItem('currentUserProfile', JSON.stringify({
            firstName: firstName,
            lastName: lastName,
            email: email
        }));
    } else if (firstName) {
        if (profileNameEl) profileNameEl.textContent = firstName;
        if (welcomeNameEl) welcomeNameEl.textContent = `Congratulations ${firstName}!`;
        localStorage.setItem('currentUserProfile', JSON.stringify({
            firstName: firstName,
            lastName: '',
            email: email
        }));
    }
}

// ============================================
// CHART INITIALIZATION
// ============================================

function initializeCharts() {
    // Sparklines are above the fold — render immediately
    initializeSparklineCharts();

    // Charts that are immediately visible
    initializeDeliveredChart();
    initializeSoldChart();
    initializeMonthlyComparisonChart();

    // Below-fold charts — use IntersectionObserver to defer until visible
    const lazyCharts = [
        { id: 'clientsChart',           init: initializeClientsChart },
        { id: 'trendChart',             init: initializeTrendChart },
        { id: 'groupAChart',            init: initializeGroupAChart },
        { id: 'groupBChart',            init: initializeGroupBChart },
    ];

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    const match = lazyCharts.find(c => c.id === entry.target.id);
                    if (match) {
                        match.init();
                        observer.unobserve(entry.target);
                    }
                }
            });
        }, { rootMargin: '100px' });

        lazyCharts.forEach(function (c) {
            const el = document.getElementById(c.id);
            if (el) observer.observe(el);
        });
    } else {
        // Fallback: init all after a short delay
        setTimeout(function () {
            lazyCharts.forEach(c => c.init());
        }, 300);
    }
}

// ============================================
// SPARKLINE CHARTS
// ============================================

function initializeSparklineCharts() {
    // Get monthly data for sparklines
    const fullMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    let monthlyData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    
    if (typeof dashboardData !== 'undefined' && dashboardData.monthly_sales) {
        fullMonths.forEach((month, index) => {
            monthlyData[index] = dashboardData.monthly_sales[month] || 0;
        });
    }
    
    // Create different sparkline data variations
    const sparklineIds = ['sparkline1', 'sparkline2', 'sparkline3', 'sparkline4'];
    const sparklineData = [
        monthlyData,  // Total delivered trend
        monthlyData.map(v => Math.round(v * 0.7)),  // Sold trend (approx 70% of delivered)
        monthlyData.filter((_, i) => i % 3 === 0).concat(monthlyData.filter((_, i) => i % 3 === 0)),  // Companies trend
        monthlyData.map(v => Math.round(v * 0.3))   // Models trend
    ];

    sparklineIds.forEach((id, index) => {
        const ctx = document.getElementById(id);
        if (ctx) {
            const data = sparklineData[index].length > 0 ? sparklineData[index] : [0, 0, 0, 0, 0, 0, 0, 0];
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: fullMonths.slice(0, data.length),
                    datasets: [{
                        data: data,
                        borderColor: '#f4d03f',
                        backgroundColor: 'rgba(244, 208, 63, 0.1)',
                        borderWidth: 1.5,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            });
        }
    });
}

// ============================================
// DELIVERED CHART (Donut)
// ============================================

function initializeDeliveredChart() {
    const ctx = document.getElementById('deliveredChart');
    if (!ctx) return;

    // Use real data from dashboardData if available
    let delivered = 696;
    let pending = 104;
    
    if (typeof dashboardData !== 'undefined') {
        delivered = dashboardData.total_delivered || 0;
        pending = dashboardData.pending_count || 0;
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Delivered', 'Pending'],
            datasets: [{
                data: [delivered, pending],
                backgroundColor: ['#f4d03f', '#2f5fa7'],
                borderColor: '#13172c',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

// ============================================
// SOLD CHART (Donut)
// ============================================

function initializeSoldChart() {
    const ctx = document.getElementById('soldChart');
    if (!ctx) return;

    // Use real data from dashboardData if available
    let sold = 311;
    let available = 489;
    
    if (typeof dashboardData !== 'undefined') {
        sold = dashboardData.total_sold || 0;
        available = Math.max(0, dashboardData.total_delivered - sold);
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Sold', 'Available'],
            datasets: [{
                data: [sold, available],
                backgroundColor: ['#51cf66', '#2f5fa7'],
                borderColor: '#13172c',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

// ============================================
// MONTHLY COMPARISON CHART (Bar)
// ============================================

function initializeMonthlyComparisonChart() {
    const ctx = document.getElementById('monthlyComparisonChart');
    if (!ctx) return;

    // Use real data from dashboardData if available
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const fullMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    let deliveredData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    
    if (typeof dashboardData !== 'undefined' && dashboardData.monthly_sales) {
        fullMonths.forEach((month, index) => {
            deliveredData[index] = dashboardData.monthly_sales[month] || 0;
        });
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Delivered',
                    data: deliveredData,
                    backgroundColor: '#f4d03f',
                    borderColor: '#d4b000',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: { font: { size: 12 } }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {},
                    grid: {}
                },
                x: {
                    ticks: {},
                    grid: { display: false }
                }
            }
        }
    });
}

// ============================================
// CLIENTS CHART (Horizontal Bar)
// ============================================

function initializeClientsChart() {
    const ctx = document.getElementById('clientsChart');
    if (!ctx) return;

    let labels = [];
    let data = [];
    
    if (typeof dashboardData !== 'undefined' && dashboardData.top_clients && dashboardData.top_clients.length > 0) {
        labels = dashboardData.top_clients.map(c => {
            const name = c.company_name || 'Unknown';
            return name.length > 20 ? name.substring(0, 20) + '...' : name;
        });
        data = dashboardData.top_clients.map(c => parseInt(c.total_quantity) || 0);
    }

    if (labels.length === 0) {
        const parent = ctx.parentElement;
        ctx.style.display = 'none';
        parent.insertAdjacentHTML('beforeend', '<p style="color:#a0a0a0;text-align:center;padding:30px 0;font-size:13px;">No client data yet. Import data to see results.</p>');
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Units Delivered',
                data: data,
                backgroundColor: '#2f5fa7',
                borderColor: '#1e3c72',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: {
                    labels: {}
                }
            },
            scales: {
                x: {
                    ticks: {},
                    grid: {}
                },
                y: {
                    ticks: {},
                    grid: { display: false }
                }
            }
        }
    });
}

// ============================================
// TREND CHART (Line)
// ============================================

function initializeTrendChart() {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;

    // Use real data from dashboardData if available
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const fullMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    let deliveredData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    
    if (typeof dashboardData !== 'undefined' && dashboardData.monthly_sales) {
        fullMonths.forEach((month, index) => {
            deliveredData[index] = dashboardData.monthly_sales[month] || 0;
        });
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Delivered',
                    data: deliveredData,
                    borderColor: '#f4d03f',
                    backgroundColor: 'rgba(244, 208, 63, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {}
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {},
                    grid: {}
                },
                x: {
                    ticks: {},
                    grid: { display: false }
                }
            }
        }
    });
}

// ============================================
// GROUP A CHART (Bar) - Top Products
// ============================================

function initializeGroupAChart() {
    const ctx = document.getElementById('groupAChart');
    if (!ctx) return;

    let labels = [];
    let data = [];

    if (typeof dashboardData !== 'undefined' && dashboardData.top_products && dashboardData.top_products.length > 0) {
        const products = dashboardData.top_products.slice(0, 5);
        labels = products.map(p => {
            const code = p.item_code || 'Unknown';
            return code.length > 15 ? code.substring(0, 15) + '...' : code;
        });
        data = products.map(p => parseInt(p.total) || 0);
    }

    if (labels.length === 0) {
        const parent = ctx.parentElement;
        ctx.style.display = 'none';
        parent.insertAdjacentHTML('beforeend', '<p style="color:#a0a0a0;text-align:center;padding:30px 0;font-size:13px;">No product data yet. Import data to see results.</p>');
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Quantity',
                    data: data,
                    backgroundColor: '#f4d03f'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {}
                },
                title: {
                    display: true,
                    text: 'Top Products (1-5)'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {},
                    grid: {}
                },
                x: {
                    ticks: {},
                    grid: { display: false }
                }
            }
        }
    });
}

// ============================================
// GROUP B CHART (Bar) - More Products
// ============================================

function initializeGroupBChart() {
    const ctx = document.getElementById('groupBChart');
    if (!ctx) return;

    let labels = [];
    let data = [];

    if (typeof dashboardData !== 'undefined' && dashboardData.top_products && dashboardData.top_products.length > 5) {
        const products = dashboardData.top_products.slice(5, 10);
        if (products.length > 0) {
            labels = products.map(p => {
                const code = p.item_code || 'Unknown';
                return code.length > 15 ? code.substring(0, 15) + '...' : code;
            });
            data = products.map(p => parseInt(p.total) || 0);
        }
    }

    if (labels.length === 0) {
        const parent = ctx.parentElement;
        ctx.style.display = 'none';
        parent.insertAdjacentHTML('beforeend', '<p style="color:#a0a0a0;text-align:center;padding:30px 0;font-size:13px;">No additional product data yet.</p>');
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Quantity',
                    data: data,
                    backgroundColor: '#51cf66'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {}
                },
                title: {
                    display: true,
                    text: 'Top Products (6-10)'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {},
                    grid: {}
                },
                x: {
                    ticks: {},
                    grid: { display: false }
                }
            }
        }
    });
}

// ============================================
// LOGOUT FUNCTIONALITY
// ============================================

function logoutHandler() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

const logoutBtn = document.getElementById('logoutBtn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        logoutHandler();
    });
}

const profileLogoutBtn = document.getElementById('profileLogoutBtn');
if (profileLogoutBtn) {
    profileLogoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        logoutHandler();
    });
}

// ============================================
// CONSOLE LOG FOR DEVELOPMENT
// ============================================

console.log('Dashboard Features:');
console.log('✓ Responsive sidebar (toggle with hamburger button)');
console.log('✓ Submenu expansion (Models dropdown)');
console.log('✓ Profile dropdown menu');
console.log('✓ Interactive Chart.js visualizations');
console.log('✓ LocalStorage sidebar state persistence');
console.log('✓ Professional enterprise design');

// ============================================
// DATASET SYNCHRONIZATION
// ============================================

function initializeDatasetSync() {
    // Get the current dataset from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const currentDataset = urlParams.get('dataset');
    
    // If there's a dataset in the URL, save it to localStorage
    if (currentDataset && currentDataset !== 'all') {
        localStorage.setItem('selectedDataset', currentDataset);
        console.log('Dataset selected:', currentDataset);
    } else if (currentDataset === 'all' || !currentDataset) {
        // If explicitly viewing all data, clear the selection
        // localStorage.removeItem('selectedDataset');
    }
    
    // Add dataset parameter to all navigation links
    addDatasetToLinksV2();
    
    // Listen for clicks on dataset cards to save selection
    const datasetCards = document.querySelectorAll('.dataset-card-dash');
    datasetCards.forEach(card => {
        card.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href) {
                const cardUrlParams = new URLSearchParams(new URL(href, window.location.origin).search);
                const datasetFromCard = cardUrlParams.get('dataset') || 'all';
                if (datasetFromCard === 'all') {
                    localStorage.removeItem('selectedDataset');
                } else {
                    localStorage.setItem('selectedDataset', datasetFromCard);
                }
                console.log('Saved dataset selection:', datasetFromCard);
            }
        });
    });
    
    // Show current dataset in console for debugging
    const savedDS = localStorage.getItem('selectedDataset');
    console.log('Current saved dataset:', savedDS || 'none (viewing all)');
}

function addDatasetToLinksV2() {
    const savedDataset = localStorage.getItem('selectedDataset');
    
    // If no saved dataset or all data, don't modify links
    if (!savedDataset || savedDataset === 'all') {
        return;
    }
    
    // Find all navigation links that should include the dataset parameter
    // More comprehensive selector for all menu links and sidebar items
    const navLinks = document.querySelectorAll('a.menu-link, .sidebar a[href*=".php"], a[href$=".php"]');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;
        
        // Skip links that are already fully qualified or external
        if (href.startsWith('http') || href.startsWith('//')) return;
        
        // Skip logout and other non-dataset pages
        const skipPages = ['logout.php', 'profile.php', 'settings.php', 'signup.php', 'login.php', 'verify', 'check', 'test'];
        if (skipPages.some(page => href.includes(page))) return;
        
        // Skip if already has dataset parameter with correct value
        if (href.includes('dataset=' + encodeURIComponent(savedDataset))) return;
        
        // Add or replace the dataset parameter
        if (href.includes('?')) {
            // Has query string already
            if (href.includes('dataset=')) {
                // Replace existing dataset param
                const newHref = href.replace(/dataset=[^&]*/i, 'dataset=' + encodeURIComponent(savedDataset));
                link.setAttribute('href', newHref);
            } else {
                // Append dataset param
                link.setAttribute('href', href + '&dataset=' + encodeURIComponent(savedDataset));
            }
        } else {
            // No query string
            link.setAttribute('href', href + '?dataset=' + encodeURIComponent(savedDataset));
        }
    });
}
