<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

// Include database configuration
require_once 'db_config.php';
require_once 'dataset-indicator.php';

// Get selected dataset from URL or session
$selected_dataset = isset($_GET['dataset']) ? trim($_GET['dataset']) : (isset($_SESSION['active_dataset']) ? $_SESSION['active_dataset'] : 'all');

// Update session if dataset is passed via GET
if (isset($_GET['dataset'])) {
    $_SESSION['active_dataset'] = $selected_dataset;
}

// Build dataset filter
$dataset_filter = "";
if ($selected_dataset !== 'all' && $selected_dataset !== '') {
    $safe_dataset = $conn->real_escape_string($selected_dataset);
    $dataset_filter = " AND dataset_name = '$safe_dataset'";
}

// Get all unique companies with their stats
$companies = [];
$result = $conn->query("
    SELECT 
        company_name, 
        COUNT(*) as total_orders,
        SUM(quantity) as total_units,
        COUNT(DISTINCT item_code) as unique_products,
        MAX(delivery_date) as last_delivery,
        MAX(delivery_month) as last_month
    FROM delivery_records 
    WHERE company_name IS NOT NULL AND company_name != '' AND company_name != ''$dataset_filter
    GROUP BY company_name 
    ORDER BY total_units DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }
}

// Get total stats
$total_companies = count($companies);
$total_units_all = 0;
foreach ($companies as $c) {
    $total_units_all += intval($c['total_units']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Companies - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .company-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .company-card:hover {
            transform: translateY(-5px);
            border-color: #2f5fa7;
            box-shadow: 0 10px 30px rgba(47, 95, 167, 0.2);
        }
        
        .company-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2f5fa7, #00d9ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .company-industry {
            font-size: 12px;
            color: #a0a0a0;
            margin-bottom: 15px;
        }
        
        .company-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #e0e0e0;
        }
        
        .info-item i {
            color: #f4d03f;
            width: 16px;
        }
        
        .company-stats {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 14px;
            font-weight: 700;
            color: #f4d03f;
        }
        
        .stat-label {
            font-size: 10px;
            color: #a0a0a0;
            text-transform: uppercase;
        }
        
        .search-container {
            margin-bottom: 25px;
            display: flex;
            gap: 12px;
        }
        
        .search-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px 16px;
            color: #e0e0e0;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }
        
        .search-input::placeholder {
            color: #a0a0a0;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #2f5fa7;
            background: rgba(255, 255, 255, 0.08);
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
        
        .view-more-btn {
            background: linear-gradient(135deg, #2f5fa7, #1e3c72);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            width: 100%;
            margin-top: 15px;
        }
        
        .view-more-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(47, 95, 167, 0.3);
        }
        
        @media (max-width: 768px) {
            .companies-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .search-container {
                flex-direction: column;
            }
        }

        /* ── Light mode: company list cards ── */
        html.light-mode .page-title,
        body.light-mode .page-title { color: #1a3a5c; }

        html.light-mode .search-input,
        body.light-mode .search-input {
            background: #fff;
            border: 1px solid #c5ddf0;
            color: #1a3a5c;
        }
        html.light-mode .search-input::placeholder,
        body.light-mode .search-input::placeholder { color: #7a9ab5; }
        html.light-mode .search-input:focus,
        body.light-mode .search-input:focus { border-color: #1e88e5; background: #fff; }

        html.light-mode .company-card,
        body.light-mode .company-card {
            background: linear-gradient(145deg, #ffffff, #e8f4fc);
            border: 1px solid #c5ddf0;
        }
        html.light-mode .company-card:hover,
        body.light-mode .company-card:hover {
            border-color: #1e88e5;
            box-shadow: 0 10px 30px rgba(30,136,229,0.15);
        }
        html.light-mode .company-name,
        body.light-mode .company-name { color: #1a3a5c; }
        html.light-mode .company-industry,
        body.light-mode .company-industry { color: #5a7a9a; }
        html.light-mode .company-info,
        body.light-mode .company-info { border-bottom-color: rgba(0,0,0,0.08); }
        html.light-mode .info-item,
        body.light-mode .info-item { color: #2a4a6a; }
        html.light-mode .info-item i,
        body.light-mode .info-item i { color: #1e88e5; }
        html.light-mode .stat-value,
        body.light-mode .stat-value { color: #1565c0; }
        html.light-mode .stat-label,
        body.light-mode .stat-label { color: #5a7a9a; }

        /* ── Light mode: profile modal ── */
        html.light-mode .profile-modal,
        body.light-mode .profile-modal {
            background: #f0f6fc;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        html.light-mode .profile-modal-body,
        body.light-mode .profile-modal-body { background: #f0f6fc; }

        html.light-mode .profile-stat,
        body.light-mode .profile-stat {
            background: #fff;
            border: 1px solid #c5ddf0;
        }
        html.light-mode .profile-stat-value,
        body.light-mode .profile-stat-value { color: #1565c0; }
        html.light-mode .profile-stat-label,
        body.light-mode .profile-stat-label { color: #5a7a9a; }

        html.light-mode .profile-section-title,
        body.light-mode .profile-section-title {
            color: #1a3a5c;
            border-bottom-color: #1e88e5;
        }

        /* Activity period text generated in JS */
        html.light-mode .profile-section p,
        body.light-mode .profile-section p { color: #3a5a7a !important; }
        html.light-mode .profile-section p strong,
        body.light-mode .profile-section p strong { color: #1a3a5c !important; }

        html.light-mode .profile-table th,
        body.light-mode .profile-table th {
            background: #ddeef8;
            color: #1565c0;
            border-bottom: 2px solid #1e88e5;
        }
        html.light-mode .profile-table td,
        body.light-mode .profile-table td {
            border-bottom-color: #dde8f0;
            color: #2a3a4a;
        }
        html.light-mode .profile-table tr:hover td,
        body.light-mode .profile-table tr:hover td { background: #e8f4fc; }

        html.light-mode .yearly-card,
        body.light-mode .yearly-card {
            background: #fff;
            border: 1px solid #c5ddf0;
        }
        html.light-mode .yearly-card-year,
        body.light-mode .yearly-card-year { color: #1565c0; }
        html.light-mode .yearly-card-stats,
        body.light-mode .yearly-card-stats { color: #4a6a8a; }
        html.light-mode .yearly-card-stats span,
        body.light-mode .yearly-card-stats span { color: #4a6a8a; }

        html.light-mode .loading-spinner,
        body.light-mode .loading-spinner { color: #5a7a9a; }
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
    <a href="orders.php" class="menu-link">
        <i class="fas fa-file-invoice-dollar"></i>
        <span class="menu-label">Orders</span>
    </a>
</li>

                <!-- Sales Records -->
                <li class="menu-item">
                    <a href="sales-records.php" class="menu-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="menu-label">Sales Records</span>
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

                <!-- Andison Manila -->
                <li class="menu-item">
                    <a href="andison-manila.php" class="menu-link">
                        <i class="fas fa-truck-fast"></i>
                        <span class="menu-label">Andison Manila</span>
                    </a>
                </li>

                <!-- Client Companies -->
                <li class="menu-item active">
                    <a href="client-companies.php" class="menu-link">
                        <i class="fas fa-building"></i>
                        <span class="menu-label">Client Companies</span>
                    </a>
                </li>

                <!-- Models -->
                <li class="menu-item">
                    <a href="models.php" class="menu-link">
                        <i class="fas fa-cube"></i>
                        <span class="menu-label">Models</span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="menu-item">
                    <a href="reports.php" class="menu-link">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">Reports</span>
                    </a>
                </li>

                <!-- Upload Data -->
                <li class="menu-item">
                    <a href="upload-data.php" class="menu-link">
                        <i class="fas fa-upload"></i>
                        <span class="menu-label">Upload Data</span>
                    </a>
                </li>

                <!-- Settings -->
                <li class="menu-item">
                    <a href="settings.php" class="menu-link">
                        <i class="fas fa-cog"></i>
                        <span class="menu-label">Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <p class="company-info">Andison Industrial</p>
            <p class="company-year">© 2025</p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <div class="page-title">
            <i class="fas fa-building"></i> Client Companies<?php echo renderDatasetIndicator($active_dataset); ?>
        </div>

        <div class="search-container">
            <input type="text" class="search-input" id="companySearch" placeholder="Search companies by name..." oninput="filterCompanies(this.value)">
        </div>

        <div class="companies-grid">
            <?php 
            $icons = ['fa-building', 'fa-industry', 'fa-shield-alt', 'fa-mountain', 'fa-globe', 'fa-flask', 'fa-cog', 'fa-warehouse'];
            $index = 0;
            foreach ($companies as $company): 
                $icon = $icons[$index % count($icons)];
                $index++;
            ?>
            <div class="company-card">
                <div class="company-logo">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
                <div class="company-industry">Gas Detection Client</div>
                <div class="company-info">
                    <div class="info-item">
                        <i class="fas fa-box"></i>
                        <span><?php echo $company['unique_products']; ?> Product Types</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-file-invoice"></i>
                        <span><?php echo $company['total_orders']; ?> Orders</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar"></i>
                        <span>Last: <?php echo $company['last_delivery'] ? date('M d, Y', strtotime($company['last_delivery'])) : 'N/A'; ?></span>
                    </div>
                </div>
                <div class="company-stats">
                    <div class="stat">
                        <div class="stat-value"><?php echo number_format($company['total_units']); ?></div>
                        <div class="stat-label">Units Sold</div>
                    </div>
                </div>
                <button class="view-more-btn" onclick="viewCompanyProfile('<?php echo htmlspecialchars(addslashes($company['company_name'])); ?>')">View Profile</button>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($companies)): ?>
            <div id="noDataMsg" class="no-data" style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">
                <i class="fas fa-building" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                <p>No company data available. Import delivery records to see client companies.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Company Profile Modal -->
    <div class="profile-modal-overlay" id="profileModal">
        <div class="profile-modal">
            <div class="profile-modal-header">
                <h2><i class="fas fa-building"></i> <span id="modalCompanyName">Company Profile</span></h2>
                <button class="close-modal" onclick="closeProfileModal()">&times;</button>
            </div>
            <div class="profile-modal-body" id="profileModalBody">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    </div>

    <style>
        .profile-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .profile-modal-overlay.active {
            display: flex;
        }
        .profile-modal {
            background: #1e1e1e;
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .profile-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px 16px 0 0;
        }
        .profile-modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .close-modal {
            background: rgba(255,255,255,0.2);
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .close-modal:hover {
            background: rgba(255,255,255,0.3);
        }
        .profile-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .loading-spinner {
            text-align: center;
            padding: 60px;
            color: #888;
            font-size: 1.2rem;
        }
        .profile-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .profile-stat {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .profile-stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 4px;
        }
        .profile-stat-label {
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .profile-section {
            margin-bottom: 24px;
        }
        .profile-section-title {
            font-size: 1.1rem;
            color: #fff;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-table {
            width: 100%;
            border-collapse: collapse;
        }
        .profile-table th {
            background: #2a2a2a;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #667eea;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .profile-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #333;
            color: #ddd;
        }
        .profile-table tr:hover td {
            background: #2a2a2a;
        }
        .yearly-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }
        .yearly-card {
            background: #2a2a2a;
            padding: 16px;
            border-radius: 10px;
            text-align: center;
        }
        .yearly-card-year {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }
        .yearly-card-stats {
            font-size: 0.9rem;
            color: #aaa;
        }
        .yearly-card-stats span {
            display: block;
            margin: 4px 0;
        }
        @media (max-width: 768px) {
            .profile-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            .profile-modal {
                max-height: 95vh;
            }
        }
    </style>

    <script>
        function viewCompanyProfile(companyName) {
            const modal = document.getElementById('profileModal');
            const modalBody = document.getElementById('profileModalBody');
            const modalTitle = document.getElementById('modalCompanyName');
            
            modalTitle.textContent = companyName;
            modalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            fetch('api/get-company-profile.php?company=' + encodeURIComponent(companyName), {
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = '<div style="text-align:center;color:#ff6b6b;padding:40px;">' + data.error + '</div>';
                        return;
                    }
                    
                    const summary = data.summary;
                    
                    let html = `
                        <div class="profile-summary">
                            <div class="profile-stat">
                                <div class="profile-stat-value">${Number(summary.total_orders).toLocaleString()}</div>
                                <div class="profile-stat-label">Total Orders</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">${Number(summary.total_units).toLocaleString()}</div>
                                <div class="profile-stat-label">Units Sold</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">${summary.unique_products}</div>
                                <div class="profile-stat-label">Products</div>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <div class="profile-section-title"><i class="fas fa-calendar-alt"></i> Activity Period</div>
                            <p style="color:#aaa;margin:0;">
                                <strong style="color:#fff;">First Order:</strong> ${formatDate(summary.first_delivery)} &nbsp;&nbsp;|&nbsp;&nbsp;
                                <strong style="color:#fff;">Last Order:</strong> ${formatDate(summary.last_delivery)}
                            </p>
                        </div>
                    `;
                    
                    // Yearly breakdown
                    if (data.yearly && data.yearly.length > 0) {
                        html += `
                            <div class="profile-section">
                                <div class="profile-section-title"><i class="fas fa-chart-bar"></i> Yearly Breakdown</div>
                                <div class="yearly-grid">
                                    ${data.yearly.map(y => `
                                        <div class="yearly-card">
                                            <div class="yearly-card-year">${y.year}</div>
                                            <div class="yearly-card-stats">
                                                <span><i class="fas fa-box"></i> ${Number(y.units).toLocaleString()} units</span>
                                                <span><i class="fas fa-file-invoice"></i> ${y.orders} orders</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                    
                    // Products table
                    if (data.products && data.products.length > 0) {
                        html += `
                            <div class="profile-section">
                                <div class="profile-section-title"><i class="fas fa-box-open"></i> Products Purchased</div>
                                <table class="profile-table">
                                    <thead>
                                        <tr>
                                            <th>Model</th>
                                            <th>Total Qty</th>
                                            <th>Orders</th>
                                            <th>Last Ordered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.products.map(p => `
                                            <tr>
                                                <td>${p.model}</td>
                                                <td>${Number(p.total_qty).toLocaleString()}</td>
                                                <td>${p.order_count}</td>
                                                <td>${formatDate(p.last_ordered)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                    
                    // Recent deliveries
                    if (data.deliveries && data.deliveries.length > 0) {
                        html += `
                            <div class="profile-section">
                                <div class="profile-section-title"><i class="fas fa-truck"></i> Recent Deliveries</div>
                                <table class="profile-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Model</th>
                                            <th>Qty</th>
                                            <th>Groupings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.deliveries.map(d => `
                                            <tr>
                                                <td>${formatDate(d.delivery_date)}</td>
                                                <td>${d.model}</td>
                                                <td>${d.qty}</td>
                                                <td>${d.groupings || '-'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                    
                    modalBody.innerHTML = html;
                })
                .catch(err => {
                    modalBody.innerHTML = '<div style="text-align:center;color:#ff6b6b;padding:40px;">Error loading profile</div>';
                    console.error(err);
                });
        }
        
        function closeProfileModal() {
            document.getElementById('profileModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeProfileModal();
        });
        
        // Close modal on overlay click
        document.getElementById('profileModal').addEventListener('click', function(e) {
            if (e.target === this) closeProfileModal();
        });

        function filterCompanies(query) {
            const cards = document.querySelectorAll('.company-card');
            const q = query.trim().toLowerCase();
            let visible = 0;
            cards.forEach(card => {
                const name = card.querySelector('.company-name').textContent.toLowerCase();
                const show = !q || name.includes(q);
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const noData = document.getElementById('noDataMsg');
            if (noData) noData.style.display = visible === 0 ? '' : 'none';
        }
    </script>

    <script src="js/app.js" defer></script>
</body>
</html>
