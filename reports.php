<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

// Database connection
require_once 'db_config.php';

// Include dataset indicator helper
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

// Initialize variables
$totalUnits = 0;
$totalOrders = 0;
$activeClients = 0;
$recentDeliveries = [];

// Get total units delivered
$result = $conn->query("SELECT COUNT(*) as total_orders, COALESCE(SUM(quantity), 0) as total_units FROM delivery_records WHERE 1=1$dataset_filter");
if ($result && $row = $result->fetch_assoc()) {
    $totalUnits = intval($row['total_units']);
    $totalOrders = intval($row['total_orders']);
}

// Get unique companies count
$result = $conn->query("SELECT COUNT(DISTINCT company_name) as company_count FROM delivery_records WHERE company_name IS NOT NULL AND company_name != ''$dataset_filter");
if ($result && $row = $result->fetch_assoc()) {
    $activeClients = intval($row['company_count']);
}

// Get ALL deliveries for export (complete dataset)
$result = $conn->query("SELECT * FROM delivery_records WHERE 1=1$dataset_filter ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentDeliveries[] = $row;
    }
}

// Get top 5 products by quantity
$topProducts = [];
$result = $conn->query("SELECT item_code, item_name, SUM(quantity) as total_qty, COUNT(*) as order_count FROM delivery_records WHERE item_code IS NOT NULL AND item_code != ''$dataset_filter GROUP BY item_code ORDER BY total_qty DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topProducts[] = $row;
    }
}

// Get top 5 clients by quantity
$topClients = [];
$result = $conn->query("SELECT company_name, SUM(quantity) as total_qty, COUNT(*) as order_count FROM delivery_records WHERE company_name IS NOT NULL AND company_name != ''$dataset_filter GROUP BY company_name ORDER BY total_qty DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topClients[] = $row;
    }
}

// Get status breakdown
$statusBreakdown = ['Delivered' => 0, 'In Transit' => 0, 'Pending' => 0, 'Cancelled' => 0];
$result = $conn->query("SELECT status, COUNT(*) as cnt FROM delivery_records GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $s = $row['status'];
        $statusBreakdown[$s] = intval($row['cnt']);
    }
}

// Get monthly breakdown for current year
$isMysql = ($conn instanceof mysqli);
$yearExpr = $isMysql
    ? "CASE WHEN delivery_year > 0 THEN delivery_year WHEN delivery_date IS NOT NULL THEN YEAR(delivery_date) ELSE YEAR(created_at) END"
    : "CASE WHEN delivery_year > 0 THEN delivery_year WHEN delivery_date IS NOT NULL THEN CAST(strftime('%Y', delivery_date) AS INTEGER) ELSE CAST(strftime('%Y', created_at) AS INTEGER) END";
$currentYear = date('Y');
$monthlyBreakdown = [];
$result = $conn->query("SELECT delivery_month, SUM(quantity) as total_qty, COUNT(*) as order_count FROM delivery_records WHERE ({$yearExpr}) = {$currentYear} GROUP BY delivery_month ORDER BY MIN(delivery_day)");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $monthlyBreakdown[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            border-color: #2f5fa7;
            box-shadow: 0 10px 30px rgba(47, 95, 167, 0.2);
        }
        
        .report-icon {
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
        
        .report-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .report-description {
            font-size: 13px;
            color: #a0a0a0;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .report-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .btn-report {
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .btn-report:hover {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #fff;
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
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 20px;
            margin-top: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f4d03f;
        }
        
        .date-range-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .date-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px 14px;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
        }
        
        .date-input:focus {
            outline: none;
            border-color: #2f5fa7;
            background: rgba(255, 255, 255, 0.08);
        }
        
        .date-label {
            color: #a0a0a0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #2f5fa7, #1e3c72);
            color: white;
            border: none;
        }
        
        /* Light mode styles for Custom Report Generator */
        html.light-mode .section-title,
        body.light-mode .section-title {
            color: #1a3a5c;
        }
        
        html.light-mode .date-label,
        body.light-mode .date-label {
            color: #3a6a8a;
        }
        
        html.light-mode .date-input,
        body.light-mode .date-input {
            background: #ffffff;
            border: 1px solid #b8d4e8;
            color: #1a3a5c;
        }
        
        html.light-mode .date-input:focus,
        body.light-mode .date-input:focus {
            border-color: #2f5fa7;
            background: #f8fbfd;
        }
        
        html.light-mode .btn-generate,
        body.light-mode .btn-generate {
            background: linear-gradient(135deg, #3a7bd5, #2f5fa7);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
        }
        
        .stat-label {
            font-size: 11px;
            color: #a0a0a0;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #f4d03f;
        }
        
        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .date-range-selector {
                flex-direction: column;
            }
        }
        
        /* Report Viewer Modal */
        .report-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .report-modal.show {
            display: flex;
        }
        
        .report-modal-content {
            background: #1a2332;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 900px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .report-modal-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .report-modal-header h2 {
            color: #fff;
            margin: 0;
            font-size: 20px;
        }
        
        .report-modal-close {
            background: none;
            border: none;
            color: #a0a0a0;
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .report-modal-close:hover {
            color: #fff;
        }
        
        .report-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            background: linear-gradient(135deg, #0f1419 0%, #1a2332 100%);
        }
        
        .report-content {
            color: #e0e0e0;
            line-height: 1.6;
        }
        
        .report-content h3 {
            color: #f4d03f;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .report-content p {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .report-table th {
            background: rgba(47, 95, 167, 0.2);
            padding: 12px;
            text-align: left;
            color: #00d9ff;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .report-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13px;
        }
        
        .report-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .report-modal-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-modal {
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-modal:hover {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #fff;
        }
        
        .btn-modal.primary {
            background: linear-gradient(135deg, #2f5fa7, #00d9ff);
            border: none;
            color: #fff;
        }
        
        .btn-modal.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(47, 95, 167, 0.3);
        }

        /* Report Filter Controls */
        .filter-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-search {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px 14px;
            color: #e0e0e0;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            flex: 1;
            min-width: 200px;
        }

        .filter-search::placeholder {
            color: #707070;
        }

        .filter-search:focus {
            outline: none;
            border-color: #2f5fa7;
            background: rgba(255, 255, 255, 0.08);
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #fff;
        }

        .report-card.hidden {
            display: none;
        }

        /* Light Mode - Modal Styles */
        html.light-mode .report-modal,
        body.light-mode .report-modal {
            background: rgba(0, 0, 0, 0.5);
        }

        html.light-mode .report-modal-content,
        body.light-mode .report-modal-content {
            background: #ffffff;
            border: 1px solid #c5ddf0;
            box-shadow: 0 10px 40px rgba(30, 136, 229, 0.2);
        }

        html.light-mode .report-modal-header,
        body.light-mode .report-modal-header {
            border-bottom: 1px solid #e0e0e0;
            background: linear-gradient(135deg, #f8fbff 0%, #e3f2fd 100%);
        }

        html.light-mode .report-modal-header h2,
        body.light-mode .report-modal-header h2 {
            color: #1a3a5c;
        }

        html.light-mode .report-modal-close,
        body.light-mode .report-modal-close {
            color: #5a7a9a;
        }

        html.light-mode .report-modal-close:hover,
        body.light-mode .report-modal-close:hover {
            color: #1a3a5c;
        }

        html.light-mode .report-modal-body,
        body.light-mode .report-modal-body {
            background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%);
        }

        html.light-mode .report-content,
        body.light-mode .report-content {
            color: #333;
        }

        html.light-mode .report-content h3,
        body.light-mode .report-content h3 {
            color: #1e88e5;
        }

        html.light-mode .report-content p,
        body.light-mode .report-content p {
            color: #444;
        }

        html.light-mode .report-table,
        body.light-mode .report-table {
            background: #fff;
            border: 1px solid #e0e0e0;
        }

        html.light-mode .report-table th,
        body.light-mode .report-table th {
            background: rgba(30, 136, 229, 0.1);
            color: #1e88e5;
            border-bottom: 2px solid #1e88e5;
        }

        html.light-mode .report-table td,
        body.light-mode .report-table td {
            color: #333;
            border-bottom: 1px solid #e0e0e0;
        }

        html.light-mode .report-table tr:hover,
        body.light-mode .report-table tr:hover {
            background: rgba(30, 136, 229, 0.05);
        }

        html.light-mode .report-modal-footer,
        body.light-mode .report-modal-footer {
            border-top: 1px solid #e0e0e0;
            background: linear-gradient(135deg, #f8fbff 0%, #e3f2fd 100%);
        }

        html.light-mode .btn-modal,
        body.light-mode .btn-modal {
            background: #f0f7ff;
            border: 1px solid #c5ddf0;
            color: #1a3a5c;
        }

        html.light-mode .btn-modal:hover,
        body.light-mode .btn-modal:hover {
            background: #1e88e5;
            border-color: #1e88e5;
            color: #fff;
        }

        html.light-mode .btn-modal.primary,
        body.light-mode .btn-modal.primary {
            background: linear-gradient(135deg, #1e88e5, #42a5f5);
            color: #fff;
        }

        /* PDF-specific styles */
        @media print {
            body {
                background: white;
            }
            .report-content {
                color: #333;
            }
            .report-content h3 {
                color: #1a5490;
                page-break-after: avoid;
            }
            .report-table {
                page-break-inside: avoid;
                border: 1px solid #ddd;
            }
            .report-table th {
                background: #e8eef5;
                color: #1a5490;
            }
            .report-table td {
                border: 1px solid #ddd;
                color: #333;
            }
            .report-modal-header,
            .report-modal-footer {
                display: none;
            }
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
                <li class="menu-item">
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
                <li class="menu-item active">
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
            <i class="fas fa-file-pdf"></i> Reports & Analytics
            <?php echo renderDatasetIndicator($active_dataset); ?>
        </div>

        <!-- Report Cards -->
        <div class="section-title">Available Reports</div>
        
        <!-- Filter Controls -->
        <div class="filter-controls">
            <input type="text" class="filter-search" id="reportSearch" placeholder="Search reports...">
            <button class="filter-btn active" onclick="filterReports('all')">All</button>
            <button class="filter-btn" onclick="filterReports('sales')">Sales</button>
            <button class="filter-btn" onclick="filterReports('inventory')">Inventory</button>
            <button class="filter-btn" onclick="filterReports('analytics')">Analytics</button>
            <button class="filter-btn" onclick="filterReports('delivery')">Delivery</button>
            <button class="filter-btn" onclick="filterReports('financial')">Financial</button>
        </div>

        <div class="reports-grid">
            <div class="report-card" data-category="sales">
                <div class="report-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="report-title">Sales Performance Report</div>
                <div class="report-description">Monthly sales trends, revenue breakdown, and growth analysis</div>
                <div class="report-actions">
                    <button class="btn-report" style="grid-column: 1 / -1;">PDF</button>
                </div>
            </div>

            <div class="report-card" data-category="inventory">
                <div class="report-icon">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="report-title">Inventory Status Report</div>
                <div class="report-description">Current stock levels, incoming shipments, and inventory forecast</div>
                <div class="report-actions">
                    <button class="btn-report">View</button>
                    <button class="btn-report">CSV</button>
                </div>
            </div>

            <div class="report-card" data-category="analytics">
                <div class="report-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="report-title">Client Analytics Report</div>
                <div class="report-description">Client acquisition, retention, and lifetime value analysis</div>
                <div class="report-actions">
                    <button class="btn-report" style="grid-column: 1 / -1;">PDF</button>
                </div>
            </div>

            <div class="report-card" data-category="delivery">
                <div class="report-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="report-title">Delivery Summary Report</div>
                <div class="report-description">Shipping performance, delivery times, and logistics analysis</div>
                <div class="report-actions">
                    <button class="btn-report">View</button>
                    <button class="btn-report">XLSX</button>
                </div>
            </div>

            <div class="report-card" data-category="financial">
                <div class="report-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="report-title">Financial Report</div>
                <div class="report-description">Revenue, expenses, profit margins, and financial forecasts</div>
                <div class="report-actions">
                    <button class="btn-report" style="grid-column: 1 / -1;">PDF</button>
                </div>
            </div>

            <div class="report-card" data-category="sales">
                <div class="report-icon">
                    <i class="fas fa-cube"></i>
                </div>
                <div class="report-title">Product Model Report</div>
                <div class="report-description">Sales by model, performance metrics, and product popularity</div>
                <div class="report-actions">
                    <button class="btn-report">View</button>
                    <button class="btn-report">CSV</button>
                </div>
            </div>
        </div>

        <!-- Report Generator -->
        <div class="section-title">Custom Report Generator</div>
        
        <div class="date-range-selector">
            <label class="date-label">
                <i class="fas fa-calendar"></i> From:
            </label>
            <input type="date" class="date-input" value="2025-01-01">
            <label class="date-label">
                <i class="fas fa-calendar"></i> To:
            </label>
            <input type="date" class="date-input" value="2025-02-12">
            <button class="btn-report btn-generate">Generate</button>
        </div>

        <!-- Quick Stats -->
        <div class="section-title">Year-to-Date Summary</div>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-label">Units Delivered</div>
                <div class="stat-value"><?php echo number_format($totalUnits); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Active Clients</div>
                <div class="stat-value"><?php echo number_format($activeClients); ?></div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="section-title">Data Export</div>
        <div class="reports-grid">
            <div class="report-card">
                <div class="report-icon">
                    <i class="fas fa-file-csv"></i>
                </div>
                <div class="report-title">Export to CSV</div>
                <div class="report-description">Download all sales and delivery data in CSV format for Excel</div>
                <button class="btn-report" style="grid-column: 1 / -1;">Export CSV</button>
            </div>

            <div class="report-card">
                <div class="report-icon">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div class="report-title">Export to Excel</div>
                <div class="report-description">Download comprehensive workbook with multiple worksheets and charts</div>
                <button class="btn-report" style="grid-column: 1 / -1;">Export XLSX</button>
            </div>

            <div class="report-card">
                <div class="report-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="report-title">Export to PDF</div>
                <div class="report-description">Download professional PDF report with charts and formatting</div>
                <button class="btn-report" style="grid-column: 1 / -1;">Export PDF</button>
            </div>
        </div>
    </main>

    <!-- Report Viewer Modal -->
    <div class="report-modal" id="reportModal">
        <div class="report-modal-content">
            <div class="report-modal-header">
                <h2 id="reportModalTitle">Report</h2>
                <button class="report-modal-close" onclick="closeReportModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="report-modal-body">
                <div class="report-content" id="reportModalBody">
                    <!-- Report content loaded here -->
                </div>
            </div>
            <div class="report-modal-footer">
                <button class="btn-modal" onclick="closeReportModal()">Close</button>
                <button class="btn-modal primary" onclick="downloadCurrentReport()">
                    <i class="fas fa-download"></i> Download PDF
                </button>
            </div>
        </div>
    </div>

    <script>
        // Report data built from real database values
        const reportData = {
            'Sales Performance Report': {
                title: 'Sales Performance Report',
                date: new Date().toLocaleDateString(),
                content: `
                    <h3>Summary</h3>
                    <p><strong>Total Units Delivered:</strong> <?php echo number_format($totalUnits); ?></p>
                    <p><strong>Total Orders:</strong> <?php echo number_format($totalOrders); ?></p>
                    <p><strong>Active Clients:</strong> <?php echo number_format($activeClients); ?></p>

                    <h3>Monthly Breakdown (<?php echo $currentYear; ?>)</h3>
                    <?php if (empty($monthlyBreakdown)): ?>
                    <p style="color:#a0a0a0;">No data for <?php echo $currentYear; ?> yet.</p>
                    <?php else: ?>
                    <table class="report-table">
                        <thead><tr><th>Month</th><th>Orders</th><th>Units</th></tr></thead>
                        <tbody>
                        <?php foreach ($monthlyBreakdown as $m): ?>
                            <tr><td><?php echo htmlspecialchars($m['delivery_month']); ?></td><td><?php echo number_format($m['order_count']); ?></td><td><?php echo number_format($m['total_qty']); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h3>Complete Delivery Records (<?php echo count($recentDeliveries); ?> total)</h3>
                    <?php if (empty($recentDeliveries)): ?>
                    <p style="color:#a0a0a0;">No delivery records available.</p>
                    <?php else: ?>
                    <table class="report-table" style="font-size: 11px;">
                        <thead><tr><th>Invoice</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Company</th><th>Serial No.</th><th>Delivery Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentDeliveries as $rec): 
                            $delivery_date = '';
                            if (!empty($rec['delivery_date'])) {
                                $delivery_date = date('M j, Y', strtotime($rec['delivery_date']));
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['invoice_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_name'] ?? ''); ?></td>
                                <td style="text-align:center;"><?php echo intval($rec['quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($rec['company_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['serial_no'] ?? ''); ?></td>
                                <td><?php echo $delivery_date; ?></td>
                                <td><?php echo htmlspecialchars($rec['status'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                `
            },
            'Client Analytics Report': {
                title: 'Client Analytics Report',
                date: new Date().toLocaleDateString(),
                content: `
                    <h3>Client Overview</h3>
                    <p><strong>Total Active Clients:</strong> <?php echo number_format($activeClients); ?></p>

                    <h3>Top Clients by Units Delivered</h3>
                    <?php if (empty($topClients)): ?>
                    <p style="color:#a0a0a0;">No client data yet.</p>
                    <?php else: ?>
                    <table class="report-table">
                        <thead><tr><th>Client</th><th>Orders</th><th>Units</th></tr></thead>
                        <tbody>
                        <?php foreach ($topClients as $c): ?>
                            <tr><td><?php echo htmlspecialchars($c['company_name']); ?></td><td><?php echo number_format($c['order_count']); ?></td><td><?php echo number_format($c['total_qty']); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h3>Complete Delivery Records (<?php echo count($recentDeliveries); ?> total)</h3>
                    <?php if (empty($recentDeliveries)): ?>
                    <p style="color:#a0a0a0;">No delivery records available.</p>
                    <?php else: ?>
                    <table class="report-table" style="font-size: 11px;">
                        <thead><tr><th>Invoice</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Company</th><th>Serial No.</th><th>Delivery Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentDeliveries as $rec): 
                            $delivery_date = '';
                            if (!empty($rec['delivery_date'])) {
                                $delivery_date = date('M j, Y', strtotime($rec['delivery_date']));
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['invoice_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_name'] ?? ''); ?></td>
                                <td style="text-align:center;"><?php echo intval($rec['quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($rec['company_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['serial_no'] ?? ''); ?></td>
                                <td><?php echo $delivery_date; ?></td>
                                <td><?php echo htmlspecialchars($rec['status'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                `
            },
            'Inventory Status Report': {
                title: 'Inventory Status Report',
                date: new Date().toLocaleDateString(),
                content: `
                    <h3>Delivery Records Overview</h3>
                    <p><strong>Total Records in System:</strong> <?php echo number_format($totalOrders); ?></p>
                    <p><strong>Total Units Tracked:</strong> <?php echo number_format($totalUnits); ?></p>
                    <p><strong>Unique Clients:</strong> <?php echo number_format($activeClients); ?></p>

                    <h3>Top Items on Record</h3>
                    <?php if (empty($topProducts)): ?>
                    <p style="color:#a0a0a0;">No data yet. Import delivery records to see items.</p>
                    <?php else: ?>
                    <table class="report-table">
                        <thead><tr><th>Item Code</th><th>Description</th><th>Total Units</th></tr></thead>
                        <tbody>
                        <?php foreach ($topProducts as $p): ?>
                            <tr><td><?php echo htmlspecialchars($p['item_code']); ?></td><td><?php echo htmlspecialchars($p['item_name'] ?: '-'); ?></td><td><?php echo number_format($p['total_qty']); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h3>Complete Delivery Records (<?php echo count($recentDeliveries); ?> total)</h3>
                    <?php if (empty($recentDeliveries)): ?>
                    <p style="color:#a0a0a0;">No delivery records available.</p>
                    <?php else: ?>
                    <table class="report-table" style="font-size: 11px;">
                        <thead><tr><th>Invoice</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Company</th><th>Serial No.</th><th>Delivery Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentDeliveries as $rec): 
                            $delivery_date = '';
                            if (!empty($rec['delivery_date'])) {
                                $delivery_date = date('M j, Y', strtotime($rec['delivery_date']));
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['invoice_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_name'] ?? ''); ?></td>
                                <td style="text-align:center;"><?php echo intval($rec['quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($rec['company_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['serial_no'] ?? ''); ?></td>
                                <td><?php echo $delivery_date; ?></td>
                                <td><?php echo htmlspecialchars($rec['status'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                `
            },
            'Financial Report': {
                title: 'Financial Report',
                date: new Date().toLocaleDateString(),
                content: `
                    <h3>Delivery Status Summary</h3>
                    <p><strong>Total Orders:</strong> <?php echo number_format($totalOrders); ?></p>
                    <?php if ($totalOrders > 0): ?>
                    <p><strong>Avg Units per Order:</strong> <?php echo round($totalUnits / $totalOrders, 1); ?></p>
                    <?php endif; ?>

                    <h3>Status Breakdown</h3>
                    <?php if ($totalOrders === 0): ?>
                    <p style="color:#a0a0a0;">No delivery records yet.</p>
                    <?php else: ?>
                    <table class="report-table">
                        <thead><tr><th>Status</th><th>Count</th><th>Percentage</th></tr></thead>
                        <tbody>
                        <?php foreach ($statusBreakdown as $status => $cnt):
                            if ($cnt === 0) continue;
                            $pct = $totalOrders > 0 ? round(($cnt / $totalOrders) * 100, 1) : 0;
                        ?>
                            <tr><td><?php echo htmlspecialchars($status); ?></td><td><?php echo number_format($cnt); ?></td><td><?php echo $pct; ?>%</td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h3>Top Clients by Units</h3>
                    <?php if (empty($topClients)): ?>
                    <p style="color:#a0a0a0;">No client data yet.</p>
                    <?php else: ?>
                    <table class="report-table">
                        <thead><tr><th>Client</th><th>Orders</th><th>Units</th></tr></thead>
                        <tbody>
                        <?php foreach ($topClients as $c): ?>
                            <tr><td><?php echo htmlspecialchars($c['company_name']); ?></td><td><?php echo number_format($c['order_count']); ?></td><td><?php echo number_format($c['total_qty']); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h3>Complete Delivery Records (<?php echo count($recentDeliveries); ?> total)</h3>
                    <?php if (empty($recentDeliveries)): ?>
                    <p style="color:#a0a0a0;">No delivery records available.</p>
                    <?php else: ?>
                    <table class="report-table" style="font-size: 11px;">
                        <thead><tr><th>Invoice</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Company</th><th>Serial No.</th><th>Delivery Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentDeliveries as $rec): 
                            $delivery_date = '';
                            if (!empty($rec['delivery_date'])) {
                                $delivery_date = date('M j, Y', strtotime($rec['delivery_date']));
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['invoice_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_name'] ?? ''); ?></td>
                                <td style="text-align:center;"><?php echo intval($rec['quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($rec['company_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['serial_no'] ?? ''); ?></td>
                                <td><?php echo $delivery_date; ?></td>
                                <td><?php echo htmlspecialchars($rec['status'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                `
            },
            'Delivery Summary Report': {
                title: 'Delivery Summary Report',
                date: new Date().toLocaleDateString(),
                content: `
                    <h3>Delivery Overview</h3>
                    <p><strong>Total Records:</strong> <?php echo number_format($totalOrders); ?></p>

                    <h3>Status Breakdown</h3>
                    <?php if ($totalOrders === 0): ?>
                    <p style="color:#a0a0a0;">No delivery records yet.</p>
                    <?php else: ?>
                    <table class="report-table">
                        <thead><tr><th>Status</th><th>Count</th><th>Percentage</th></tr></thead>
                        <tbody>
                        <?php foreach ($statusBreakdown as $status => $cnt):
                            if ($cnt === 0) continue;
                            $pct = $totalOrders > 0 ? round(($cnt / $totalOrders) * 100, 1) : 0;
                        ?>
                            <tr><td><?php echo htmlspecialchars($status); ?></td><td><?php echo number_format($cnt); ?></td><td><?php echo $pct; ?>%</td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h3>Complete Delivery Records (<?php echo count($recentDeliveries); ?> total)</h3>
                    <?php if (empty($recentDeliveries)): ?>
                    <p style="color:#a0a0a0;">No delivery records available.</p>
                    <?php else: ?>
                    <table class="report-table" style="font-size: 11px;">
                        <thead><tr><th>Invoice</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Company</th><th>Serial No.</th><th>Delivery Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentDeliveries as $rec): 
                            $delivery_date = '';
                            if (!empty($rec['delivery_date'])) {
                                $delivery_date = date('M j, Y', strtotime($rec['delivery_date']));
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['invoice_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_name'] ?? ''); ?></td>
                                <td style="text-align:center;"><?php echo intval($rec['quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($rec['company_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['serial_no'] ?? ''); ?></td>
                                <td><?php echo $delivery_date; ?></td>
                                <td><?php echo htmlspecialchars($rec['status'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                `
            },
            'Product Model Report': {
                title: 'Product Model Report',
                date: new Date().toLocaleDateString(),
                content: `
                    <h3>Top Products by Units Delivered</h3>
                    <?php if (empty($topProducts)): ?>
                    <p style="color:#a0a0a0;">No product data yet. Import data to see results.</p>
                    <?php else: ?>
                    <table class="report-table">
                        <thead><tr><th>Item Code</th><th>Description</th><th>Orders</th><th>Units</th></tr></thead>
                        <tbody>
                        <?php foreach ($topProducts as $p): ?>
                            <tr><td><?php echo htmlspecialchars($p['item_code']); ?></td><td><?php echo htmlspecialchars($p['item_name'] ?: '-'); ?></td><td><?php echo number_format($p['order_count']); ?></td><td><?php echo number_format($p['total_qty']); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <h3>Complete Delivery Records (<?php echo count($recentDeliveries); ?> total)</h3>
                    <?php if (empty($recentDeliveries)): ?>
                    <p style="color:#a0a0a0;">No delivery records available.</p>
                    <?php else: ?>
                    <table class="report-table" style="font-size: 11px;">
                        <thead><tr><th>Invoice</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Company</th><th>Serial No.</th><th>Delivery Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentDeliveries as $rec): 
                            $delivery_date = '';
                            if (!empty($rec['delivery_date'])) {
                                $delivery_date = date('M j, Y', strtotime($rec['delivery_date']));
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['invoice_no'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['item_name'] ?? ''); ?></td>
                                <td style="text-align:center;"><?php echo intval($rec['quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($rec['company_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($rec['serial_no'] ?? ''); ?></td>
                                <td><?php echo $delivery_date; ?></td>
                                <td><?php echo htmlspecialchars($rec['status'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                `
            }
        };

        let currentReportData = null;
        let currentFilterType = 'all';

        // Report functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Search input handler
            const searchInput = document.getElementById('reportSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    const searchTerm = this.value.toLowerCase();
                    filterBySearch(searchTerm);
                });
            }

            // Report card button handlers
            const reportCards = document.querySelectorAll('.report-card');
            reportCards.forEach((card, index) => {
                const buttons = card.querySelectorAll('.btn-report');
                const title = card.querySelector('.report-title')?.textContent || 'Report';
                
                buttons.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const action = this.textContent.trim();
                        handleReportAction(title, action);
                    });
                });
            });
            
            // Generate button handler
            const generateBtn = document.querySelector('button[style*="linear-gradient"]');
            if (generateBtn) {
                generateBtn.addEventListener('click', function() {
                    const fromDate = document.querySelectorAll('.date-input')[0].value;
                    const toDate = document.querySelectorAll('.date-input')[1].value;
                    
                    if (!fromDate || !toDate) {
                        showNotification('Please select both start and end dates', 'error');
                        return;
                    }
                    
                    if (new Date(fromDate) > new Date(toDate)) {
                        showNotification('Start date must be before end date', 'error');
                        return;
                    }
                    
                    showNotification(`Custom report generated for ${fromDate} to ${toDate}`, 'success');
                    console.log('Report generated for period:', fromDate, 'to', toDate);
                });
            }
            
            // Export option handlers
            const exportButtons = document.querySelectorAll('button[style*="grid-column"]');
            exportButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const action = this.textContent.trim();
                    handleExport(action);
                });
            });
        });
        
        function handleReportAction(reportName, action) {
            switch(action.toUpperCase()) {
                case 'VIEW':
                    viewReport(reportName);
                    break;
                case 'PDF':
                case 'CSV':
                case 'XLSX':
                    downloadReport(reportName, action.toUpperCase());
                    break;
                default:
                    console.log('Action:', action, 'Report:', reportName);
            }
        }
        
        function viewReport(reportName) {
            const report = reportData[reportName];
            if (report) {
                currentReportData = report;
                document.getElementById('reportModalTitle').textContent = report.title;
                document.getElementById('reportModalBody').innerHTML = report.content;
                document.getElementById('reportModal').classList.add('show');
                showNotification(`Opening ${reportName}...`, 'info');
            }
        }
        
        function closeReportModal() {
            document.getElementById('reportModal').classList.remove('show');
        }
        
        function openPrintWindow(title, contentHTML) {
            const dateStr = new Date().toISOString().split('T')[0];
            const w = window.open('', '_blank', 'width=960,height=750');
            if (!w) { showNotification('Please allow popups for PDF export.', 'error'); return; }
            w.document.write(`<!DOCTYPE html><html><head>
                <title>${title} - ${dateStr}</title>
                <style>
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: Arial, Helvetica, sans-serif; color: #333; background: #f0f4f8; }
                    .toolbar { background: #1a3a5c; padding: 12px 30px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; }
                    .toolbar span { color: #a0c8e8; font-size: 13px; }
                    .btn-save { background: #f4d03f; color: #1a3a5c; border: none; padding: 10px 24px; font-size: 14px; font-weight: 700; border-radius: 6px; cursor: pointer; font-family: Arial, sans-serif; }
                    .btn-save:hover { background: #e6c230; }
                    .page { background: #fff; max-width: 860px; margin: 24px auto; padding: 40px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); border-radius: 4px; }
                    .pdf-header { background: #1a3a5c; color: white; padding: 18px 22px; border-radius: 8px; margin-bottom: 22px; }
                    .pdf-header h1 { color: #fff; font-size: 20px; margin-bottom: 4px; }
                    .pdf-header p { color: #a0c8e8; font-size: 12px; }
                    .pdf-meta { color: #888; font-size: 11px; margin-bottom: 18px; border-bottom: 1px solid #e0e0e0; padding-bottom: 12px; }
                    h3 { color: #1a5490; margin: 22px 0 10px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f4d03f; padding-bottom: 5px; }
                    p { margin: 7px 0; font-size: 13px; line-height: 1.6; }
                    table { width: 100%; border-collapse: collapse; margin: 12px 0 20px; font-size: 12px; }
                    th { background: #2f5fa7; color: #fff; padding: 9px 12px; text-align: left; font-weight: 600; }
                    td { padding: 8px 12px; border-bottom: 1px solid #e8e8e8; }
                    tr:nth-child(even) td { background: #f7f9fc; }
                    strong { font-weight: 600; }
                    @media print {
                        .toolbar { display: none !important; }
                        body { background: #fff; }
                        .page { box-shadow: none; margin: 0; padding: 20px; }
                        .pdf-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    }
                </style>
            </head><body>
                <div class="toolbar">
                    <span>${title}</span>
                    <button class="btn-save" onclick="window.print()">&#x1F4E5; Save as PDF / Print</button>
                </div>
                <div class="page">
                    <div class="pdf-header">
                        <img src="${window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '')}/assets/logo.png" alt="Andison" style="height:50px;width:auto;margin-bottom:10px;display:block;">
                        <h1>${title}</h1>
                        <p>Andison Industrial Sales Inc.</p>
                    </div>
                    <p class="pdf-meta">Generated: ${new Date().toLocaleString()}</p>
                    ${contentHTML}
                </div>

            </body></html>`);
            w.document.close();
        }

        function downloadCurrentReport() {
            if (currentReportData) {
                const element = document.getElementById('reportModalBody');
                openPrintWindow(currentReportData.title, element.innerHTML);
                showNotification(`Opening ${currentReportData.title} for PDF export...`, 'success');
            }
        }
        
        function downloadReport(reportName, format) {
            const report = reportData[reportName];
            if (!report) return;
            
            if (format === 'PDF') {
                openPrintWindow(reportName, report.content);
                showNotification(`Opening ${reportName} for PDF export...`, 'success');
            } else if (format === 'CSV') {
                // Generate Excel XML for CSV (with styled headers)
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = report.content;
                const tables = tempDiv.querySelectorAll('table');
                
                let xmlRows = '';
                xmlRows += `<Row ss:Height="30"><Cell ss:StyleID="title"><Data ss:Type="String">${report.title}</Data></Cell></Row>`;
                xmlRows += `<Row><Cell><Data ss:Type="String">Generated: ${new Date().toLocaleString()}</Data></Cell></Row>`;
                xmlRows += '<Row></Row>';
                
                tables.forEach((table, idx) => {
                    const rows = table.querySelectorAll('tr');
                    rows.forEach((row, rIdx) => {
                        const cells = row.querySelectorAll('th, td');
                        const isHeader = row.querySelector('th') !== null;
                        let rowXml = '<Row>';
                        cells.forEach(cell => {
                            const style = isHeader ? 'ss:StyleID="header"' : 'ss:StyleID="cell"';
                            const value = cell.textContent.trim();
                            const numTest = value.replace(/[₱,%,]/g, '');
                            const type = !isNaN(numTest) && numTest !== '' ? 'Number' : 'String';
                            const cleanValue = type === 'Number' ? numTest : value;
                            rowXml += `<Cell ${style}><Data ss:Type="${type}">${cleanValue}</Data></Cell>`;
                        });
                        rowXml += '</Row>';
                        xmlRows += rowXml;
                    });
                    xmlRows += '<Row></Row>';
                });
                
                const excelXml = `<` + `?xml version="1.0" encoding="UTF-8"?>
<` + `?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
    <Style ss:ID="Default"><Font ss:FontName="Calibri" ss:Size="11"/></Style>
    <Style ss:ID="title"><Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#1a5490"/></Style>
    <Style ss:ID="header"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2f5fa7" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
    <Style ss:ID="cell"><Font ss:FontName="Calibri" ss:Size="11"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/></Borders></Style>
</Styles>
<Worksheet ss:Name="${report.title.substring(0, 31)}">
<Table>${xmlRows}</Table>
</Worksheet>
</Workbook>`;
                
                const blob = new Blob([excelXml], { type: 'application/vnd.ms-excel' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `${reportName.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.xls`;
                link.click();
                URL.revokeObjectURL(link.href);
                showNotification(`Downloading ${reportName} as Excel...`, 'success');
            } else if (format === 'XLSX') {
                // Generate Excel XML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = report.content;
                const tables = tempDiv.querySelectorAll('table');
                
                let xmlRows = '';
                let rowNum = 0;
                
                // Title row
                xmlRows += `<Row ss:Height="30"><Cell ss:StyleID="title"><Data ss:Type="String">${report.title}</Data></Cell></Row>`;
                xmlRows += `<Row><Cell><Data ss:Type="String">Generated: ${new Date().toLocaleString()}</Data></Cell></Row>`;
                xmlRows += `<Row></Row>`;
                
                tables.forEach((table, idx) => {
                    const rows = table.querySelectorAll('tr');
                    rows.forEach((row, rIdx) => {
                        const cells = row.querySelectorAll('th, td');
                        const isHeader = row.querySelector('th') !== null;
                        let rowXml = '<Row>';
                        cells.forEach(cell => {
                            const style = isHeader ? 'ss:StyleID="header"' : '';
                            const value = cell.textContent.trim();
                            const type = !isNaN(value.replace(/[₱,%]/g, '')) && value !== '' ? 'Number' : 'String';
                            const cleanValue = type === 'Number' ? value.replace(/[₱,%,]/g, '') : value;
                            rowXml += `<Cell ${style}><Data ss:Type="${type}">${cleanValue}</Data></Cell>`;
                        });
                        rowXml += '</Row>';
                        xmlRows += rowXml;
                    });
                    xmlRows += '<Row></Row>';
                });
                
                const excelXml = `<` + `?xml version="1.0" encoding="UTF-8"?>
<` + `?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
    <Style ss:ID="Default"><Font ss:FontName="Calibri" ss:Size="11"/></Style>
    <Style ss:ID="title"><Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#1a5490"/></Style>
    <Style ss:ID="header"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2f5fa7" ss:Pattern="Solid"/></Style>
</Styles>
<Worksheet ss:Name="${report.title.substring(0, 31)}">
<Table>${xmlRows}</Table>
</Worksheet>
</Workbook>`;
                
                const blob = new Blob([excelXml], { type: 'application/vnd.ms-excel' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `${reportName.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.xls`;
                link.click();
                URL.revokeObjectURL(link.href);
                showNotification(`Downloading ${reportName} as Excel...`, 'success');
            }
        }
        
        function handleExport(action) {
            const format = action.match(/CSV|XLSX|PDF/)[0];
            const filename = `BW_Gas_Detector_Export_${new Date().toISOString().split('T')[0]}`;
            
            // Get export data from PHP - includes ALL complete delivery records
            const exportData = {
                totalUnits: <?php echo $totalUnits; ?>,
                totalOrders: <?php echo $totalOrders; ?>,
                activeClients: <?php echo $activeClients; ?>,
                allDeliveries: <?php echo json_encode($recentDeliveries); ?>
            };
            
            if (format === 'PDF') {
                const exportHTML = `
                    <h3>Year-to-Date Summary</h3>
                    <table>
                        <tr><th>Metric</th><th>Value</th></tr>
                        <tr><td>Total Records</td><td>${exportData.allDeliveries.length.toLocaleString()}</td></tr>
                        <tr><td>Units Delivered</td><td>${exportData.totalUnits.toLocaleString()}</td></tr>
                        <tr><td>Total Orders</td><td>${exportData.totalOrders.toLocaleString()}</td></tr>
                        <tr><td>Active Clients</td><td>${exportData.activeClients}</td></tr>
                    </table>
                    <h3>Complete Delivery Records (${exportData.allDeliveries.length} total)</h3>
                    <table style="font-size: 11px;">
                        <tr><th>Invoice</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Company</th><th>Serial No.</th><th>Delivery Date</th><th>Status</th></tr>
                        ${exportData.allDeliveries.length ? exportData.allDeliveries.map(rec => {
                            const deliveryDate = rec.delivery_date ? new Date(rec.delivery_date).toLocaleDateString() : '';
                            return `<tr><td>${rec.invoice_no || ''}</td><td>${rec.item_code || ''}</td><td>${rec.item_name || ''}</td><td style="text-align:center;">${rec.quantity || 0}</td><td>${rec.company_name || ''}</td><td>${rec.serial_no || ''}</td><td>${deliveryDate}</td><td>${rec.status || ''}</td></tr>`;
                        }).join('') : '<tr><td colspan="8">No delivery records available.</td></tr>'}
                    </table>
                `;
                openPrintWindow('BW Gas Detector - Complete Data Export', exportHTML);
                showNotification(`✅ Opening complete data export for PDF (${exportData.allDeliveries.length} records)...`, 'success');
            } else if (format === 'CSV') {
                // Build XML string step by step
                let xmlContent = '<' + '?xml version="1.0" encoding="UTF-8"?>';
                xmlContent += '<' + '?mso-application progid="Excel.Sheet"?>';
                xmlContent += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
                xmlContent += '<Styles>';
                xmlContent += '<Style ss:ID="Default"><Font ss:FontName="Calibri" ss:Size="11"/></Style>';
                xmlContent += '<Style ss:ID="titleStyle"><Font ss:FontName="Calibri" ss:Size="18" ss:Bold="1" ss:Color="#1a3a5c"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>';
                xmlContent += '<Style ss:ID="dateStyle"><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#666666"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>';
                xmlContent += '<Style ss:ID="summaryHeader"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#003399" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/></Borders><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
                xmlContent += '<Style ss:ID="detailHeader"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#003399" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#003399"/></Borders><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
                xmlContent += '<Style ss:ID="dataLight"><Font ss:FontName="Calibri" ss:Size="10"/><Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/></Borders><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>';
                xmlContent += '<Style ss:ID="dataDark"><Font ss:FontName="Calibri" ss:Size="10"/><Interior ss:Color="#F5F5F5" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/></Borders><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>';
                xmlContent += '</Styles>';
                xmlContent += '<Worksheet ss:Name="Report">';
                xmlContent += '<Table ss:DefaultColumnWidth="100" ss:DefaultRowHeight="18">';
                xmlContent += '<Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="180"/><Column ss:Width="70"/><Column ss:Width="120"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="100"/>';
                
                // Title
                xmlContent += '<Row ss:Height="30"><Cell ss:StyleID="titleStyle" ss:MergeAcross="6"><Data ss:Type="String">Product Model Report</Data></Cell></Row>';
                xmlContent += '<Row ss:Height="18"><Cell ss:StyleID="dateStyle" ss:MergeAcross="6"><Data ss:Type="String">Generated: ' + new Date().toLocaleString() + '</Data></Cell></Row>';
                xmlContent += '<Row></Row>';
                
                // Summary header
                xmlContent += '<Row ss:Height="24">';
                xmlContent += '<Cell ss:StyleID="summaryHeader"><Data ss:Type="String">Item Code</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="summaryHeader"><Data ss:Type="String">Description</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="summaryHeader"><Data ss:Type="String">Orders</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="summaryHeader"><Data ss:Type="String">Units</Data></Cell>';
                xmlContent += '</Row>';
                
                // Group and summarize data
                const itemSummary = {};
                exportData.allDeliveries.forEach(rec => {
                    const code = rec.item_code || 'Unknown';
                    if (!itemSummary[code]) {
                        itemSummary[code] = {
                            code: code,
                            description: rec.item_name || '',
                            orders: 0,
                            units: 0
                        };
                    }
                    itemSummary[code].orders++;
                    itemSummary[code].units += parseInt(rec.quantity || 0);
                });
                
                // Add summary rows
                let summaryIndex = 0;
                Object.values(itemSummary).forEach((item) => {
                    const rowStyle = summaryIndex % 2 === 0 ? 'dataLight' : 'dataDark';
                    xmlContent += '<Row ss:Height="18">';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (item.code || '') + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (item.description || '') + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="Number">' + item.orders + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="Number">' + item.units + '</Data></Cell>';
                    xmlContent += '</Row>';
                    summaryIndex++;
                });
                
                xmlContent += '<Row></Row>';
                
                // Detail header
                xmlContent += '<Row ss:Height="24">';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Invoice</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Item Code</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Description</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Qty</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Company</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Serial No.</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Delivery Date</Data></Cell>';
                xmlContent += '<Cell ss:StyleID="detailHeader"><Data ss:Type="String">Status</Data></Cell>';
                xmlContent += '</Row>';
                
                // Add detailed rows
                exportData.allDeliveries.forEach((rec, index) => {
                    const rowStyle = index % 2 === 0 ? 'dataLight' : 'dataDark';
                    const deliveryDate = rec.delivery_date ? new Date(rec.delivery_date).toLocaleDateString() : '';
                    xmlContent += '<Row ss:Height="18">';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (rec.invoice_no || '') + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (rec.item_code || '') + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (rec.item_name || '') + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="Number">' + (rec.quantity || 0) + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (rec.company_name || '') + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (rec.serial_no || '') + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + deliveryDate + '</Data></Cell>';
                    xmlContent += '<Cell ss:StyleID="' + rowStyle + '"><Data ss:Type="String">' + (rec.status || '') + '</Data></Cell>';
                    xmlContent += '</Row>';
                });
                
                xmlContent += '</Table></Worksheet></Workbook>';
                
                const blob = new Blob([xmlContent], { type: 'application/vnd.ms-excel; charset=utf-8' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename + '.xls';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
                showNotification(`✅ Exporting ${exportData.allDeliveries.length} complete records to Excel...`, 'success');
            } else if (format === 'XLSX') {
                let xmlRows = '';
                
                // Title
                xmlRows += '<Row ss:Height="30"><Cell ss:StyleID="title" ss:MergeAcross="15"><Data ss:Type="String">BW Gas Detector - Complete Delivery Records Export</Data></Cell></Row>';
                xmlRows += `<Row><Cell ss:MergeAcross="15"><Data ss:Type="String">Generated: ${new Date().toLocaleString()}</Data></Cell></Row>`;
                xmlRows += '<Row></Row>';
                
                // Summary
                xmlRows += '<Row><Cell ss:StyleID="section" ss:MergeAcross="1"><Data ss:Type="String">EXPORT SUMMARY</Data></Cell></Row>';
                xmlRows += '<Row><Cell ss:StyleID="header"><Data ss:Type="String">Total Records</Data></Cell><Cell ss:StyleID="header"><Data ss:Type="Number">' + exportData.allDeliveries.length + '</Data></Cell></Row>';
                xmlRows += `<Row><Cell><Data ss:Type="String">Total Units</Data></Cell><Cell><Data ss:Type="Number">${exportData.totalUnits}</Data></Cell></Row>`;
                xmlRows += `<Row><Cell><Data ss:Type="String">Total Orders</Data></Cell><Cell><Data ss:Type="Number">${exportData.totalOrders}</Data></Cell></Row>`;
                xmlRows += `<Row><Cell><Data ss:Type="String">Active Clients</Data></Cell><Cell><Data ss:Type="Number">${exportData.activeClients}</Data></Cell></Row>`;
                xmlRows += '<Row></Row>';
                
                // All Delivery Records
                xmlRows += '<Row><Cell ss:StyleID="section" ss:MergeAcross="15"><Data ss:Type="String">ALL DELIVERY RECORDS (' + exportData.allDeliveries.length + ' total)</Data></Cell></Row>';
                xmlRows += '<Row ss:Height="24">'
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Invoice No.</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Item Code</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Description</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Quantity</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">UOM</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Company</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Serial No.</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Delivery Date</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Delivery Month</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Delivery Day</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Year</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Sold To</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Sold To Month</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Sold To Day</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Remarks</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Groupings</Data></Cell>';
                xmlRows += '<Cell ss:StyleID="header"><Data ss:Type="String">Status</Data></Cell>';
                xmlRows += '</Row>';
                
                exportData.allDeliveries.forEach(rec => {
                    xmlRows += '<Row>';
                    xmlRows += `<Cell><Data ss:Type="String">${rec.invoice_no || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.item_code || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.item_name || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="Number">${rec.quantity || 0}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.uom || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.company_name || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.serial_no || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.delivery_date ? new Date(rec.delivery_date).toLocaleDateString() : ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.delivery_month || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.delivery_day || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="Number">${rec.delivery_year || 0}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.sold_to || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.sold_to_month || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.sold_to_day || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.notes || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.groupings || ''}</Data></Cell>`;
                    xmlRows += `<Cell><Data ss:Type="String">${rec.status || ''}</Data></Cell>`;
                    xmlRows += '</Row>';
                });
                
                const excelXml = `<` + `?xml version="1.0" encoding="UTF-8"?>
<` + `?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
    <Style ss:ID="Default"><Font ss:FontName="Calibri" ss:Size="11"/></Style>
    <Style ss:ID="title"><Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#1a5490"/></Style>
    <Style ss:ID="section"><Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#333333"/><Interior ss:Color="#f4d03f" ss:Pattern="Solid"/></Style>
    <Style ss:ID="header"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2f5fa7" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style>
</Styles>
<Worksheet ss:Name="All Records">
<Table>${xmlRows}</Table>
</Worksheet>
</Workbook>`;
                
                const blob = new Blob([excelXml], { type: 'application/vnd.ms-excel' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename + '.xls';
                link.click();
                URL.revokeObjectURL(link.href);
                showNotification(`✅ Exporting ${exportData.allDeliveries.length} complete records to Excel...`, 'success');
            }
        }
        
        function showNotification(message, type = 'info') {
            console.log(`[${type.toUpperCase()}] ${message}`);
            
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#51cf66' : type === 'error' ? '#ff6b6b' : '#2f5fa7'};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Filter Reports by Category
        function filterReports(category) {
            currentFilterType = category;
            const reportCards = document.querySelectorAll('[data-category]');
            const filterBtns = document.querySelectorAll('.filter-btn');
            const searchInput = document.getElementById('reportSearch');
            
            // Clear search input when using category filter
            if (searchInput) {
                searchInput.value = '';
            }
            
            // Update active button state
            filterBtns.forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('onclick').includes(`'${category}'`)) {
                    btn.classList.add('active');
                }
            });
            
            // Filter cards
            let visibleCount = 0;
            reportCards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                
                if (category === 'all' || cardCategory === category) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            if (visibleCount === 0) {
                showNotification('No reports found in this category', 'error');
            } else {
                showNotification(`Showing ${visibleCount} report(s)`, 'info');
            }
        }
        
        // Search Reports by Title/Description
        function filterBySearch(searchTerm) {
            const reportCards = document.querySelectorAll('[data-category]');
            let visibleCount = 0;
            
            reportCards.forEach(card => {
                const title = card.querySelector('.report-title')?.textContent.toLowerCase() || '';
                const description = card.querySelector('.report-description')?.textContent.toLowerCase() || '';
                const category = card.getAttribute('data-category');
                const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
                
                // If category filter is active (not 'all'), respect it
                const matchesCategory = currentFilterType === 'all' || category === currentFilterType;
                
                if (matchesSearch && matchesCategory) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            if (searchTerm) {
                if (visibleCount === 0) {
                    showNotification(`No reports found matching "${searchTerm}"`, 'error');
                } else {
                    showNotification(`Found ${visibleCount} report(s)`, 'success');
                }
            }
        }
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>

    <script src="js/app.js" defer></script>
</body>
</html>
