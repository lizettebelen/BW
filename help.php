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
    <title>Help & Support - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .help-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .help-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .help-header h1 {
            font-size: 36px;
            color: #fff;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-weight: 700;
        }

        .help-header h1 i {
            color: #f4d03f;
            font-size: 40px;
        }

        .help-header p {
            color: #a0a0a0;
            font-size: 16px;
            margin: 0;
        }

        /* Search Bar */
        .help-search {
            margin: 30px 0 30px 0;
            position: relative;
        }

        .help-search input {
            width: 100%;
            padding: 14px 20px;
            padding-left: 45px;
            border-radius: 10px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .help-search input::placeholder {
            color: #a0a0a0;
        }

        .help-search input:focus {
            outline: none;
            border-color: #f4d03f;
            background: rgba(255, 255, 255, 0.08);
        }

        .help-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0a0a0;
            pointer-events: none;
        }

        .help-content {
            display: grid;
            gap: 25px;
        }

        .help-section {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .help-section:hover {
            border-color: rgba(244, 208, 63, 0.3);
            box-shadow: 0 8px 25px rgba(244, 208, 63, 0.1);
        }

        .section-header {
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }

        .section-header:hover {
            background: rgba(0, 0, 0, 0.2);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .section-title h2 {
            font-size: 20px;
            color: #f4d03f;
            margin: 0;
            font-weight: 600;
        }

        .section-title i {
            font-size: 24px;
            color: #f4d03f;
        }

        .section-toggle {
            background: none;
            border: none;
            color: #f4d03f;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .section-content {
            max-height: 1000px;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
            opacity: 1;
            padding: 0 25px 25px 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-content.collapsed {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            padding: 0 25px;
            border-top: none;
        }

        .help-item {
            margin-bottom: 25px;
        }

        .help-item:last-child {
            margin-bottom: 0;
        }

        .help-item h3 {
            color: #5bbcff;
            font-size: 16px;
            margin: 0 0 8px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .help-item h3::before {
            content: "";
            display: inline-block;
            width: 5px;
            height: 5px;
            background: #5bbcff;
            border-radius: 50%;
        }

        .help-item p {
            color: #e0e0e0;
            font-size: 14px;
            margin: 0 0 8px 0;
            line-height: 1.6;
        }

        .help-item ul {
            color: #e0e0e0;
            font-size: 14px;
            margin: 10px 0 0 0;
            padding-left: 25px;
            line-height: 1.7;
        }

        .help-item li {
            margin-bottom: 8px;
        }

        .help-item strong {
            color: #f4d03f;
            font-weight: 600;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #5bbcff;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #f4d03f;
            gap: 12px;
        }

        .back-link i {
            font-size: 18px;
        }

        .contact-box {
            background: rgba(244, 208, 63, 0.1);
            border: 2px solid #f4d03f;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-top: 40px;
        }

        .contact-box h3 {
            color: #f4d03f;
            font-size: 20px;
            margin: 0 0 12px 0;
            font-weight: 700;
        }

        .contact-box p {
            color: #a0a0a0;
            font-size: 14px;
            margin: 0;
            line-height: 1.6;
        }

        .step-box {
            background: rgba(0, 0, 0, 0.2);
            padding: 12px 15px;
            border-left: 4px solid #5bbcff;
            border-radius: 4px;
            margin: 10px 0;
        }

        .step-box p {
            margin: 0;
            color: #e0e0e0;
            font-size: 13px;
            line-height: 1.6;
        }

        .tip-box {
            background: rgba(39, 174, 96, 0.1);
            border-left: 4px solid #27ae60;
            padding: 12px 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .tip-box p {
            margin: 0;
            color: #e0e0e0;
            font-size: 13px;
        }

        .tip-box strong {
            color: #27ae60;
        }

        @media (max-width: 768px) {
            .help-wrapper {
                padding: 20px 15px;
            }

            .help-header h1 {
                font-size: 24px;
            }

            .section-header {
                padding: 18px;
            }

            .section-content {
                padding: 0 18px 18px 18px;
            }
        }

        html.light-mode .help-section,
        body.light-mode .help-section {
            background: linear-gradient(135deg, #e8f0f7 0%, #d4e4f0 100%);
            border-color: rgba(31, 93, 184, 0.2);
        }

        html.light-mode .section-header:hover,
        body.light-mode .section-header:hover {
            background: rgba(31, 93, 184, 0.1);
        }

        html.light-mode .section-title h2,
        html.light-mode .section-title i,
        body.light-mode .section-title h2,
        body.light-mode .section-title i {
            color: #1e5db8;
        }

        html.light-mode .section-toggle,
        body.light-mode .section-toggle {
            color: #1e5db8;
        }

        html.light-mode .help-item h3,
        body.light-mode .help-item h3 {
            color: #1e88e5;
        }

        html.light-mode .help-item p,
        html.light-mode .help-item ul,
        body.light-mode .help-item p,
        body.light-mode .help-item ul {
            color: #333;
        }

        html.light-mode .help-search input,
        body.light-mode .help-search input {
            background: rgba(31, 93, 184, 0.05);
            border-color: rgba(31, 93, 184, 0.2);
            color: #333;
        }

        html.light-mode .help-search input::placeholder,
        body.light-mode .help-search input::placeholder {
            color: #999;
        }

        html.light-mode .help-search input:focus,
        body.light-mode .help-search input:focus {
            background: rgba(31, 93, 184, 0.1);
            border-color: #1e5db8;
        }

        html.light-mode .contact-box,
        body.light-mode .contact-box {
            background: rgba(31, 93, 184, 0.08);
            border-color: #1e5db8;
        }

        html.light-mode .contact-box h3,
        body.light-mode .contact-box h3 {
            color: #1e5db8;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #a0a0a0;
        }

        .no-results i {
            font-size: 48px;
            color: #5bbcff;
            margin-bottom: 15px;
            display: block;
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

            <!-- Center Title -->
            <div class="navbar-center">
                <h1 class="dashboard-title">Help & Support</h1>
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
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <!-- Sidebar Menu -->
            <ul class="sidebar-menu">
                <!-- Dashboard -->
                <li class="menu-item">
                    <a href="index.php" class="menu-link">
                        <i class="fas fa-chart-line"></i>
                        <span class="menu-label">Dashboard</span>
                    </a>
                </li>

                <!-- Sales Overview -->
                <li class="menu-item">
                    <a href="sales-overview.php" class="menu-link">
                        <i class="fas fa-chart-pie"></i>
                        <span class="menu-label">Sales Overview</span>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="inquiry.php" class="menu-link">
                        <i class="fas fa-file-invoice"></i>
                        <span class="menu-label">Inquiry</span>
                    </a>
                </li>


                <!-- Delivery Records -->
                <li class="menu-item">
                    <a href="delivery-records.php" class="menu-link">
                        <i class="fas fa-truck"></i>
                        <span class="menu-label">Delivery Records</span>
                    </a>
                </li>

                <!-- Inventory -->
                <li class="menu-item">
                    <a href="inventory.php" class="menu-link">
                        <i class="fas fa-boxes"></i>
                        <span class="menu-label">Inventory</span>
                    </a>
                </li>

                <!-- Analytics -->
                <li class="menu-item">
                    <a href="analytics.php" class="menu-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="menu-label">Analytics</span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="menu-item">
                    <a href="reports.php" class="menu-link">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">Reports</span>
                    </a>
                </li>

                <!-- Models -->
                <li class="menu-item">
                    <a href="models.php" class="menu-link">
                        <i class="fas fa-layer-group"></i>
                        <span class="menu-label">Models</span>
                    </a>
                </li>

                <!-- Client Companies -->
                <li class="menu-item">
                    <a href="client-companies.php" class="menu-link">
                        <i class="fas fa-building"></i>
                        <span class="menu-label">Client Companies</span>
                    </a>
                </li>

                <!-- Upload Data -->
                <li class="menu-item">
                    <a href="upload-data.php" class="menu-link">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span class="menu-label">Upload Data</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Logout Theme Toggle -->
        <div class="sidebar-footer">
            <button type="button" id="themeToggle" class="theme-toggle" title="Toggle Dark/Light Mode">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="help-wrapper">
            <a href="javascript:history.back()" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>

            <div class="help-header">
                <h1>
                    <i class="fas fa-question-circle"></i>
                    Help & Support
                </h1>
                <p>Find answers to common questions and learn how to use the BW Gas Detector Sales Dashboard</p>
            </div>

            <!-- Search Bar -->
            <div class="help-search">
                <i class="fas fa-search"></i>
                <input type="text" id="helpSearch" placeholder="Search help topics...">
            </div>

            <div class="help-content" id="helpContent">
                <!-- Getting Started Section -->
                <section class="help-section" data-section="getting-started">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-rocket"></i>
                            <h2>Getting Started</h2>
                        </div>
                        <button class="section-toggle" aria-label="Toggle section">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="section-content">
                        <div class="help-item">
                            <h3>Dashboard Overview</h3>
                            <p>The Dashboard is your main hub for viewing sales metrics, recent deliveries, and key performance indicators. It provides a snapshot of your business performance in real-time.</p>
                            <div class="tip-box">
                                <p><strong>Tip:</strong> The dashboard updates automatically. Refresh your browser to see the latest data.</p>
                            </div>
                        </div>
                        <div class="help-item">
                            <h3>Navigation</h3>
                            <p>Use the sidebar menu on the left to navigate between different sections of the application.</p>
                            <div class="step-box">
                                <p><strong>Step 1:</strong> Click the hamburger menu (three horizontal lines) in the top-left to toggle the sidebar</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 2:</strong> Select any menu item to navigate to that section</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 3:</strong> Click the hamburger again to collapse and gain more screen space</p>
                            </div>
                        </div>
                        <div class="help-item">
                            <h3>User Profile</h3>
                            <p>Access your profile by clicking on your name in the top-right corner dropdown menu. From here you can:</p>
                            <ul>
                                <li>View and edit your profile information</li>
                                <li>Manage account settings and preferences</li>
                                <li>Configure security and two-factor authentication</li>
                                <li>Access this help page</li>
                                <li>Logout from your account</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Sales & Inventory Section -->
                <section class="help-section" data-section="sales-inventory">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-shopping-cart"></i>
                            <h2>Sales & Inventory</h2>
                        </div>
                        <button class="section-toggle" aria-label="Toggle section">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="section-content">
                        <div class="help-item">
                            <h3>Sales Overview</h3>
                            <p>View comprehensive sales data and performance metrics:</p>
                            <ul>
                                <li><strong>Total Sales:</strong> Monitor your overall sales volume</li>
                                <li><strong>Top Products:</strong> Identify your best-selling items</li>
                                <li><strong>Sales Trends:</strong> Analyze patterns over time</li>
                                <li><strong>Revenue Metrics:</strong> Track financial performance</li>
                                <li><strong>Period Comparison:</strong> Compare performance across different time periods</li>
                            </ul>
                            <div class="tip-box">
                                <p><strong>Pro Tip:</strong> Use filters and date ranges to narrow down your analysis for specific products or time periods.</p>
                            </div>
                        </div>
                        <div class="help-item">
                            <h3>Managing Inventory</h3>
                            <p>Track and manage inventory levels for all product models:</p>
                            <div class="step-box">
                                <p><strong>Step 1:</strong> Go to the Inventory section from the sidebar</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 2:</strong> Review stock levels for all items - items are color-coded by status</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 3:</strong> Use filters to find low-stock items that need reordering</p>
                            </div>
                            <ul>
                                <li><strong>Critical (Red):</strong> 5 or fewer units - immediate reorder needed</li>
                                <li><strong>Low (Orange):</strong> 5-20 units - consider reordering soon</li>
                                <li><strong>Adequate (Blue):</strong> 20-100 units - normal stock level</li>
                                <li><strong>High (Green):</strong> 100+ units - plenty in stock</li>
                            </ul>
                        </div>
                        <div class="help-item">
                            <h3>Delivery Records</h3>
                            <p>Keep track of all deliveries and shipments:</p>
                            <div class="step-box">
                                <p><strong>Step 1:</strong> Navigate to Delivery Records in the sidebar</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 2:</strong> Use filters to find specific deliveries by date, client, or product</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 3:</strong> View details, edit records if needed, or export data</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Data Management Section -->
                <section class="help-section" data-section="data-management">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-database"></i>
                            <h2>Data Management</h2>
                        </div>
                        <button class="section-toggle" aria-label="Toggle section">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="section-content">
                        <div class="help-item">
                            <h3>Uploading Data</h3>
                            <p>Import sales and delivery records into the system:</p>
                            <div class="step-box">
                                <p><strong>Step 1:</strong> Go to Upload Data section</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 2:</strong> Select your file or drag and drop it</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 3:</strong> Follow the preview and validation steps</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 4:</strong> Confirm and complete the import</p>
                            </div>
                            <div class="tip-box">
                                <p><strong>Supported Formats:</strong> CSV (.csv), Excel (.xlsx, .xls). File size should not exceed 10MB.</p>
                            </div>
                        </div>
                        <div class="help-item">
                            <h3>Analytics & Reports</h3>
                            <p>Generate detailed reports to analyze your business performance:</p>
                            <ul>
                                <li>Create custom reports based on your needs</li>
                                <li>Apply filters to focus on specific data</li>
                                <li>Export data in multiple formats</li>
                                <li>Track key performance indicators (KPIs) over time</li>
                                <li>Generate forecasts and trends</li>
                            </ul>
                        </div>
                        <div class="help-item">
                            <h3>Models & Products</h3>
                            <p>Organize and manage your product models:</p>
                            <div class="step-box">
                                <p><strong>Step 1:</strong> Access the Models section</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 2:</strong> View all product models and their details</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 3:</strong> Group products by category or characteristics</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 4:</strong> Track performance metrics for each model</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Account & Settings Section -->
                <section class="help-section" data-section="account-settings">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-cog"></i>
                            <h2>Account & Settings</h2>
                        </div>
                        <button class="section-toggle" aria-label="Toggle section">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="section-content">
                        <div class="help-item">
                            <h3>Profile Settings</h3>
                            <p>Update your account profile information:</p>
                            <div class="step-box">
                                <p><strong>Step 1:</strong> Click your name in the top-right and select "My Profile"</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 2:</strong> Edit your personal information as needed</p>
                            </div>
                            <div class="step-box">
                                <p><strong>Step 3:</strong> Save your changes</p>
                            </div>
                            <div class="tip-box">
                                <p><strong>Important:</strong> Keep your email address current for account recovery and notifications.</p>
                            </div>
                        </div>
                        <div class="help-item">
                            <h3>Security Settings</h3>
                            <p>Protect your account with security features:</p>
                            <ul>
                                <li><strong>Password Management:</strong> Change your password regularly</li>
                                <li><strong>Two-Factor Authentication:</strong> Add an extra layer of security</li>
                                <li><strong>Session Management:</strong> View and manage active sessions</li>
                                <li><strong>Security Alerts:</strong> Receive notifications for account activities</li>
                            </ul>
                            <div class="tip-box">
                                <p><strong>Best Practice:</strong> Enable two-factor authentication for enhanced account security.</p>
                            </div>
                        </div>
                        <div class="help-item">
                            <h3>Theme & Display</h3>
                            <p>Customize your viewing experience:</p>
                            <ul>
                                <li><strong>Light/Dark Mode:</strong> Toggle between themes using the moon/sun icon in the sidebar</li>
                                <li><strong>Font Size:</strong> Adjust text size for better readability</li>
                                <li><strong>Layout Options:</strong> Choose compact or comfortable display modes</li>
                                <li><strong>Animations:</strong> Enable or disable animations</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Troubleshooting Section -->
                <section class="help-section" data-section="troubleshooting">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-wrench"></i>
                            <h2>Troubleshooting</h2>
                        </div>
                        <button class="section-toggle" aria-label="Toggle section">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="section-content">
                        <div class="help-item">
                            <h3>Data Not Appearing</h3>
                            <ul>
                                <li>Refresh the page (Ctrl+R or Cmd+R)</li>
                                <li>Clear browser cache and cookies</li>
                                <li>Try a different browser</li>
                                <li>Check your internet connection</li>
                            </ul>
                        </div>
                        <div class="help-item">
                            <h3>Upload Failed</h3>
                            <ul>
                                <li>Verify the file format (CSV or Excel)</li>
                                <li>Check file size (max 10MB)</li>
                                <li>Ensure all required columns are present</li>
                                <li>Check for special characters in data</li>
                                <li>Try uploading in smaller batches</li>
                            </ul>
                        </div>
                        <div class="help-item">
                            <h3>Login Issues</h3>
                            <ul>
                                <li>Verify your email and password are correct</li>
                                <li>Use the "Forgot Password" link to reset</li>
                                <li>Clear browser cache</li>
                                <li>Check if two-factor authentication is enabled</li>
                            </ul>
                        </div>
                        <div class="help-item">
                            <h3>Slow Performance</h3>
                            <ul>
                                <li>Clear browser cache cookies</li>
                                <li>Disable unnecessary browser extensions</li>
                                <li>Check your internet connection speed</li>
                                <li>Try using a different browser</li>
                                <li>Reduce the amount of data filters applied</li>
                            </ul>
                        </div>
                        <div class="help-item">
                            <h3>Missing or Incorrect Data</h3>
                            <ul>
                                <li>Check if you're viewing the correct dataset</li>
                                <li>Verify all applied filters</li>
                                <li>Ensure data was properly imported</li>
                                <li>Check the date range selection</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Contact Section -->
                <div class="contact-box">
                    <h3><i class="fas fa-headset"></i> Still Need Help?</h3>
                    <p>If you have questions or issues not covered in this help guide, please contact our support team through your account settings or check back for additional resources.</p>
                </div>
            </div>
        </div>
    </main>

    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize collapsible sections
            const sections = document.querySelectorAll('.help-section');
            
            sections.forEach((section, index) => {
                const header = section.querySelector('.section-header');
                const content = section.querySelector('.section-content');
                const toggle = section.querySelector('.section-toggle');
                
                // Open first section by default
                if (index === 0) {
                    content.classList.remove('collapsed');
                } else {
                    content.classList.add('collapsed');
                }
                
                header.addEventListener('click', function() {
                    content.classList.toggle('collapsed');
                    toggle.style.transform = content.classList.contains('collapsed') 
                        ? 'rotate(0deg)' 
                        : 'rotate(180deg)';
                });
            });

            // Search functionality
            const searchInput = document.getElementById('helpSearch');
            const helpContent = document.getElementById('helpContent');
            const helpSections = document.querySelectorAll('.help-section');

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                let visibleSections = 0;

                helpSections.forEach(section => {
                    const text = section.textContent.toLowerCase();
                    const isMatch = searchTerm === '' || text.includes(searchTerm);
                    
                    if (isMatch) {
                        section.style.display = 'block';
                        visibleSections++;
                        // Open section if it matches search
                        if (searchTerm !== '') {
                            const content = section.querySelector('.section-content');
                            content.classList.remove('collapsed');
                            const toggle = section.querySelector('.section-toggle');
                            toggle.style.transform = 'rotate(180deg)';
                        }
                    } else {
                        section.style.display = 'none';
                    }
                });

                // Show no results message if needed
                if (searchTerm !== '' && visibleSections === 0) {
                    if (!document.getElementById('noResults')) {
                        const noResults = document.createElement('div');
                        noResults.id = 'noResults';
                        noResults.className = 'no-results';
                        noResults.innerHTML = `
                            <i class="fas fa-search"></i>
                            <h3 style="color: #a0a0a0; margin: 15px 0 10px 0;">No results found</h3>
                            <p>Try adjusting your search terms</p>
                        `;
                        helpContent.appendChild(noResults);
                    }
                } else {
                    const noResults = document.getElementById('noResults');
                    if (noResults) {
                        noResults.remove();
                    }
                }
            });

            // Close all sections button functionality (optional feature)
            document.addEventListener('keydown', function(e) {
                // Ctrl+/ or Cmd+/ to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        });
    </script>
</body>
</html>
