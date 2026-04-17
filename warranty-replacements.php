<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';
require_once 'dataset-indicator.php';

// Initialize warranty table if needed
$isMysql = ($conn instanceof mysqli);
if ($isMysql) {
    $check = $conn->query("SHOW TABLES LIKE 'warranty_replacements'");
    if (!$check || $check->num_rows === 0) {
        // Table doesn't exist, redirect to setup
        header('Location: api/create-warranty-table.php');
        exit;
    }
} else {
    $check = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='warranty_replacements'");
    if (!$check || $check->num_rows === 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS warranty_replacements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            delivery_record_id INTEGER DEFAULT NULL,
            invoice_no VARCHAR(100) DEFAULT NULL,
            delivery_month VARCHAR(20),
            delivery_day INTEGER,
            delivery_year INTEGER,
            record_date DATE DEFAULT NULL,
            delivery_date DATE DEFAULT NULL,
            item_code VARCHAR(50),
            item_name VARCHAR(255),
            company_name VARCHAR(255),
            sold_to VARCHAR(255) DEFAULT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'Warranty Pending',
            uom VARCHAR(20) DEFAULT NULL,
            serial_no VARCHAR(150) DEFAULT NULL,
            transferred_to VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            warranty_flag INTEGER DEFAULT 1,
            warranty_date DATE DEFAULT NULL,
            red_text_detected INTEGER DEFAULT 1,
            dataset_name VARCHAR(50) DEFAULT NULL,
            highlight_color VARCHAR(20) DEFAULT NULL,
            cell_styles TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$date_from = isset($_GET['from']) ? trim($_GET['from']) : '';
$date_to = isset($_GET['to']) ? trim($_GET['to']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build WHERE clause
$where = "1=1";

if ($search) {
    $search_safe = $conn->real_escape_string($search);
    $where .= " AND (item_code LIKE '%$search_safe%' 
                     OR item_name LIKE '%$search_safe%' 
                     OR serial_no LIKE '%$search_safe%' 
                     OR company_name LIKE '%$search_safe%')";
}

if ($status_filter !== 'all') {
    $status_safe = $conn->real_escape_string($status_filter);
    $where .= " AND status = '$status_safe'";
}

if ($date_from) {
    $date_from_safe = $conn->real_escape_string($date_from);
    $where .= " AND warranty_date >= '$date_from_safe'";
}

if ($date_to) {
    $date_to_safe = $conn->real_escape_string($date_to);
    $where .= " AND warranty_date <= '$date_to_safe'";
}

// Get statistics
$stats = [
    'total_warranty' => 0,
    'pending' => 0,
    'approved' => 0,
    'replaced' => 0,
    'total_qty' => 0
];

$stat_query = "SELECT status, COUNT(*) as cnt, COALESCE(SUM(quantity), 0) as qty FROM warranty_replacements WHERE $where GROUP BY status";
$stat_result = $conn->query($stat_query);
if ($stat_result) {
    while ($row = $stat_result->fetch_assoc()) {
        $cnt = intval($row['cnt']);
        $qty = intval($row['qty']);
        $stats['total_warranty'] += $cnt;
        $stats['total_qty'] += $qty;
        
        $status_lower = strtolower($row['status']);
        if (strpos($status_lower, 'pending') !== false) {
            $stats['pending'] += $cnt;
        } elseif (strpos($status_lower, 'approved') !== false) {
            $stats['approved'] += $cnt;
        } elseif (strpos($status_lower, 'replace') !== false) {
            $stats['replaced'] += $cnt;
        }
    }
}

// Build ORDER BY clause
$order_by = "warranty_date DESC, id DESC";
if ($sort_by === 'oldest') {
    $order_by = "warranty_date ASC, id ASC";
} elseif ($sort_by === 'item') {
    $order_by = "item_code ASC, item_name ASC";
} elseif ($sort_by === 'company') {
    $order_by = "company_name ASC";
} elseif ($sort_by === 'status') {
    $order_by = "status ASC, warranty_date DESC";
}

// Get warranty records
$warranty_records = [];
$records_query = "SELECT * FROM warranty_replacements WHERE $where ORDER BY $order_by";
$records_result = $conn->query($records_query);
if ($records_result) {
    while ($row = $records_result->fetch_assoc()) {
        $warranty_records[] = $row;
    }
}

// Get unique statuses for filter dropdown
$statuses = ['Warranty Pending', 'Approved', 'Replaced', 'Cancelled'];

// Get unique companies
$companies = [];
$companies_query = "SELECT DISTINCT company_name FROM warranty_replacements WHERE company_name IS NOT NULL ORDER BY company_name ASC";
$companies_result = $conn->query($companies_query);
if ($companies_result) {
    while ($row = $companies_result->fetch_assoc()) {
        $companies[] = $row['company_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty Replacements - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .warranty-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .warranty-header {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .warranty-header-title {
            margin: 0 0 5px 0;
            color: #ffffff !important;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
        }

        .warranty-header-subtitle {
            color: #d7e2ef !important;
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
        }

        .warranty-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .stat-card h3 {
            color: #a0a0a0;
            font-size: 12px;
            font-weight: 600;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .number {
            color: #f4d03f;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .stat-card .subtitle {
            color: #607080;
            font-size: 11px;
            margin-top: 5px;
        }

        .filters-section {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filters-section h3 {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-input {
            display: flex;
            flex-direction: column;
        }

        .filter-input label {
            color: #8a9ab5;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .filter-input input,
        .filter-input select {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 6px;
            color: #fff;
            padding: 8px 12px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
        }

        .filter-input input:focus,
        .filter-input select:focus {
            outline: none;
            border-color: #f4d03f;
            background: rgba(0, 0, 0, 0.5);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-filter {
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            color: #1a3a5c;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(244, 208, 63, 0.3);
        }

        .btn-reset {
            background: rgba(255, 255, 255, 0.1);
            color: #a0a0a0;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .warranty-table-section {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 20px;
            overflow-x: auto;
        }

        .warranty-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .warranty-table thead {
            background: rgba(47, 95, 167, 0.1);
            border-bottom: 2px solid rgba(244, 208, 63, 0.3);
        }

        .warranty-table th {
            color: #f4d03f;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .warranty-table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: background-color 0.2s ease;
        }

        .warranty-table tbody tr:hover {
            background-color: rgba(244, 208, 63, 0.05);
        }

        .warranty-table td {
            color: #dce8f0;
            padding: 12px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.4);
        }

        .status-approved {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.4);
        }

        .status-replaced {
            background: rgba(33, 150, 243, 0.2);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.4);
        }

        .status-cancelled {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.4);
        }

        .red-text-indicator {
            color: #ff6b6b;
            font-weight: 600;
            font-size: 11px;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #8a9ab5;
        }

        .no-records i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .right-align {
            text-align: right;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        .btn-small {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s ease;
        }

        .btn-small:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <!-- TOP NAVBAR -->
    <nav class="navbar">
        <div class="navbar-container">
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

            <div class="navbar-center">
                <h1 class="dashboard-title">BW Gas Detector Sales</h1>
            </div>

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
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="index.php" class="menu-link">
                        <i class="fas fa-chart-line"></i>
                        <span class="menu-label">Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="sales-overview.php" class="menu-link">
                        <i class="fas fa-chart-pie"></i>
                        <span class="menu-label">Sales Overview</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="sales-records.php" class="menu-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="menu-label">Sales Records</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="inquiry.php" class="menu-link">
                        <i class="fas fa-file-invoice"></i>
                        <span class="menu-label">Inquiry</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="delivery-records.php" class="menu-link">
                        <i class="fas fa-truck"></i>
                        <span class="menu-label">Delivery Records</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="inventory.php" class="menu-link">
                        <i class="fas fa-boxes"></i>
                        <span class="menu-label">Inventory</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="andison-manila.php" class="menu-link">
                        <i class="fas fa-truck-fast"></i>
                        <span class="menu-label">Andison Manila</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="client-companies.php" class="menu-link">
                        <i class="fas fa-building"></i>
                        <span class="menu-label">Client Companies</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="models.php" class="menu-link">
                        <i class="fas fa-cube"></i>
                        <span class="menu-label">Models</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reports.php" class="menu-link">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">Reports</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="upload-data.php" class="menu-link">
                        <i class="fas fa-upload"></i>
                        <span class="menu-label">Upload Data</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="warranty-replacements.php" class="menu-link">
                        <i class="fas fa-wrench"></i>
                        <span class="menu-label">Warranty Items</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="settings.php" class="menu-link">
                        <i class="fas fa-cog"></i>
                        <span class="menu-label">Settings</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <p class="company-info">Andison Industrial</p>
            <p class="company-year">© 2025</p>
        </div>
    </aside>

    <main class="main-content" id="mainContent">
        <div class="warranty-container">
            <!-- Header -->
            <div class="warranty-header">
                <h1 class="warranty-header-title" style="margin: 0 0 5px 0; color: #ffffff !important; font-size: 28px; font-weight: 700; line-height: 1.2; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);">
                    <i class="fas fa-wrench" style="color: #f4d03f !important; margin-right: 10px;"></i>
                    Warranty Replacements
                </h1>
                <p class="warranty-header-subtitle" style="color: #d7e2ef !important; margin: 0; font-size: 13px; line-height: 1.5;">
                    <i class="fas fa-info-circle" style="margin-right: 5px; color: #d7e2ef !important;"></i>
                    Records flagged with RED text during import
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="warranty-stats">
                <div class="stat-card">
                    <h3><i class="fas fa-check-square" style="color: #f4d03f;"></i> Total Items</h3>
                    <p class="number"><?php echo $stats['total_warranty']; ?></p>
                    <span class="subtitle">Warranty records</span>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-hourglass-half" style="color: #ffc107;"></i> Pending</h3>
                    <p class="number"><?php echo $stats['pending']; ?></p>
                    <span class="subtitle">Awaiting review</span>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-check-circle" style="color: #4caf50;"></i> Approved</h3>
                    <p class="number"><?php echo $stats['approved']; ?></p>
                    <span class="subtitle">Approved for replacement</span>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-box-open" style="color: #2196f3;"></i> Replaced</h3>
                    <p class="number"><?php echo $stats['replaced']; ?></p>
                    <span class="subtitle">Replacement completed</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <h3><i class="fas fa-filter"></i> Filter Records</h3>
                <form method="GET" id="filterForm">
                    <div class="filters-group">
                        <div class="filter-input">
                            <label for="search">Search (Item/Serial/Company)</label>
                            <input type="text" id="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-input">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="all">All Statuses</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-input">
                            <label for="from">From Date</label>
                            <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="filter-input">
                            <label for="to">To Date</label>
                            <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="filter-input">
                            <label for="sort">Sort By</label>
                            <select id="sort" name="sort">
                                <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="item" <?php echo $sort_by === 'item' ? 'selected' : ''; ?>>Item Code</option>
                                <option value="company" <?php echo $sort_by === 'company' ? 'selected' : ''; ?>>Company</option>
                                <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply Filters</button>
                        <button type="button" onclick="window.location='warranty-replacements.php'" class="btn-reset"><i class="fas fa-times"></i> Reset</button>
                    </div>
                </form>
            </div>

            <!-- Records Table -->
            <div class="warranty-table-section">
                <?php if (empty($warranty_records)): ?>
                    <div class="no-records">
                        <i class="fas fa-inbox"></i>
                        <p>No warranty records found</p>
                        <small style="color: #607080;">Try uploading an Excel file with red text items.</small>
                    </div>
                <?php else: ?>
                    <table class="warranty-table">
                        <thead>
                            <tr>
                                <th>Date Flagged</th>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Serial No.</th>
                                <th>Company</th>
                                <th>Qty</th>
                                <th>Red Text</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warranty_records as $record): ?>
                                <?php
                                    $warranty_date = $record['warranty_date'] ?: date('Y-m-d');
                                    $red_detected = intval($record['red_text_detected'] ?? 1);
                                ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($warranty_date)); ?></td>
                                    <td style="font-weight: 600; color: #f4d03f;"><?php echo htmlspecialchars($record['item_code'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($record['item_name'] ?: '-', 0, 40)); ?></td>
                                    <td><?php echo htmlspecialchars($record['serial_no'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($record['company_name'] ?: '-'); ?></td>
                                    <td class="right-align"><?php echo intval($record['quantity']); ?> <?php echo htmlspecialchars($record['uom'] ?: ''); ?></td>
                                    <td>
                                        <?php if ($red_detected): ?>
                                            <span class="red-text-indicator"><i class="fas fa-circle" style="font-size: 8px;"></i> Red</span>
                                        <?php else: ?>
                                            <span style="color: #607080; font-size: 11px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="right-align">
                                        <div class="action-buttons">
                                            <button class="btn-small" onclick="viewWarrantyDetail(<?php echo $record['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Summary -->
            <div style="margin-top: 20px; padding: 15px; background: rgba(244, 208, 63, 0.1); border: 1px solid rgba(244, 208, 63, 0.3); border-radius: 8px; color: #dce8f0; font-size: 12px;">
                <i class="fas fa-info-circle" style="color: #f4d03f; margin-right: 8px;"></i>
                Showing <strong><?php echo count($warranty_records); ?></strong> warranty record(s) with total <strong><?php echo $stats['total_qty']; ?></strong> units
            </div>
        </div>
    </main>

    <script src="js/app.js"></script>
    <script>
        const ALLOWED_STATUSES = ['Warranty Pending', 'Approved', 'Replaced', 'Cancelled'];

        function viewWarrantyDetail(warrantId) {
            // Fetch warranty details and show in modal
            fetch('api/get-warranty-detail.php?id=' + warrantId)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showWarrantyDetailModal(data.warranty);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => alert('Error loading warranty details: ' + err.message));
        }

        function showWarrantyDetailModal(warranty) {
            const lines = [
                `Item Code: ${warranty.item_code || '-'}`,
                `Item Name: ${warranty.item_name || '-'}`,
                `Serial No.: ${warranty.serial_no || '-'}`,
                `Company: ${warranty.company_name || '-'}`,
                `Quantity: ${warranty.quantity || 0} ${warranty.uom || ''}`,
                `Warranty Date: ${warranty.warranty_date ? new Date(warranty.warranty_date).toLocaleDateString() : '-'}`,
                `Status: ${warranty.status || '-'}`
            ];

            if (warranty.notes) {
                lines.push(`Notes: ${warranty.notes}`);
            }

            alert(lines.join('\n'));
        }

        function editWarrantyStatus(warrantId, currentStatus) {
            const statusList = ALLOWED_STATUSES
                .filter(s => s !== currentStatus)
                .map(s => `${ALLOWED_STATUSES.indexOf(s) + 1}. ${s}`)
                .join('\n');
            
            const prompt_text = `Update warranty status for ID ${warrantId}\n\nCurrent Status: ${currentStatus}\n\nAvailable Statuses:\n${statusList}`;
            const response = prompt(prompt_text, currentStatus);
            
            if (response && response !== currentStatus && ALLOWED_STATUSES.includes(response)) {
                updateWarrantyStatus(warrantId, response);
            } else if (response && response !== currentStatus) {
                alert('Invalid status selected');
            }
        }

        function updateWarrantyStatus(warrantId, newStatus) {
            fetch('api/update-warranty-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: warrantId,
                    status: newStatus
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Status updated successfully');
                    // Reload page to show updated status
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(err => alert('Error updating status: ' + err.message));
        }
    </script>
</body>
</html>
