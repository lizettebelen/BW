<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';
require_once 'dataset-indicator.php';

// Initialize warranty table if needed
if (!($conn instanceof mysqli)) {
    header('Location: api/create-warranty-table.php');
    exit;
}

$check = $conn->query("SHOW TABLES LIKE 'warranty_replacements'");
if (!$check || $check->num_rows === 0) {
    // Table doesn't exist, redirect to setup
    header('Location: api/create-warranty-table.php');
    exit;
}

$isMysql = true;

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

        .warranty-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-add-record {
            background: linear-gradient(135deg, #f4d03f 0%, #f9d76a 100%);
            color: #1a3a5c;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-add-record:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(244, 208, 63, 0.35);
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

        .warranty-summary-note {
            margin-top: 20px;
            padding: 15px;
            background: rgba(244, 208, 63, 0.14);
            border: 1px solid rgba(244, 208, 63, 0.35);
            border-radius: 8px;
            color: #dce8f0;
            font-size: 12px;
            line-height: 1.45;
        }

        .warranty-summary-note i {
            color: #f4d03f;
            margin-right: 8px;
        }

        .warranty-summary-note strong {
            color: #ffffff;
            font-weight: 700;
        }

        html.light-mode .warranty-summary-note,
        body.light-mode .warranty-summary-note {
            background: #fff8d8;
            border-color: #f2cf63;
            color: #31465f;
        }

        html.light-mode .warranty-summary-note i,
        body.light-mode .warranty-summary-note i {
            color: #b48200;
        }

        html.light-mode .warranty-summary-note strong,
        body.light-mode .warranty-summary-note strong {
            color: #1d2f45;
        }

        .right-align {
            text-align: right;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
        }

        .btn-small {
            border: 1px solid transparent;
            color: #12365b;
            padding: 5px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s ease;
            min-width: 28px;
            min-height: 28px;
        }

        .btn-small:hover {
            transform: translateY(-1px);
        }

        .btn-view {
            background: #e8f3ff;
            border-color: #b8d3ef;
            color: #1f5f97;
        }

        .btn-view:hover {
            background: #d8eafb;
        }

        .btn-status {
            background: #fff5da;
            border-color: #f2dc98;
            color: #7d6200;
        }

        .btn-status:hover {
            background: #ffeec1;
        }

        .btn-delete {
            background: #fff0f0;
            border-color: #eabcbc;
            color: #b44545;
        }

        .btn-delete:hover {
            background: #ffe3e3;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(7, 14, 26, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1200;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-card {
            width: min(760px, 100%);
            background: linear-gradient(180deg, #f9fcff 0%, #eef4fb 100%);
            border: 1px solid #c9d8ea;
            border-radius: 14px;
            box-shadow: 0 20px 48px rgba(8, 24, 44, 0.22);
            overflow: hidden;
            color: #17324d;
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #d6e2f0;
            background: linear-gradient(180deg, #f2f8ff 0%, #ebf3fd 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            color: #12365b;
            font-size: 18px;
            font-weight: 700;
        }

        .modal-close {
            background: #e7effa;
            border: 1px solid #b9cbe2;
            color: #2b4b71;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
        }

        .modal-close:hover {
            background: #d8e7f8;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .modal-field {
            display: flex;
            flex-direction: column;
        }

        .modal-field.full {
            grid-column: 1 / -1;
        }

        .modal-field label {
            color: #24486e;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            text-shadow: none;
        }

        .modal-field input,
        .modal-field select,
        .modal-field textarea {
            background: #ffffff;
            border: 1px solid #b8cce3;
            border-radius: 7px;
            color: #162f47;
            padding: 9px 12px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
        }

        .modal-field input::placeholder,
        .modal-field textarea::placeholder {
            color: #6c849f;
            opacity: 1;
        }

        .modal-field select option {
            color: #162f47;
        }

        .modal-field textarea {
            min-height: 88px;
            resize: vertical;
        }

        .modal-field input:focus,
        .modal-field select:focus,
        .modal-field textarea:focus {
            outline: none;
            border-color: #4b88c7;
            box-shadow: 0 0 0 3px rgba(75, 136, 199, 0.22);
        }

        .modal-actions {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .system-alert-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 28, 46, 0.55);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3200;
            padding: 16px;
        }

        .system-alert-overlay.show {
            display: flex;
        }

        .system-alert-card {
            width: min(420px, 100%);
            background: #ffffff;
            border-radius: 6px;
            border-top: 4px solid #1f2937;
            box-shadow: 0 26px 55px rgba(11, 25, 43, 0.32);
            position: relative;
            padding: 24px 22px 18px;
            text-align: center;
            color: #1f2937;
        }

        .system-alert-card.warning {
            border-top-color: #f2a63c;
        }

        .system-alert-card.success {
            border-top-color: #24bf93;
        }

        .system-alert-card.error {
            border-top-color: #ef4f4f;
        }

        .system-alert-close {
            position: absolute;
            right: 10px;
            top: 8px;
            border: none;
            background: transparent;
            color: #5c6472;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
        }

        .system-alert-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            border: 2px solid #1f2937;
            color: #1f2937;
        }

        .system-alert-card.warning .system-alert-icon {
            border-color: #f2a63c;
            color: #f2a63c;
        }

        .system-alert-card.success .system-alert-icon {
            border-color: #24bf93;
            color: #24bf93;
        }

        .system-alert-card.error .system-alert-icon {
            border-color: #ef4f4f;
            color: #ef4f4f;
        }

        .system-alert-title {
            margin: 0 0 10px;
            font-size: 23px;
            font-weight: 700;
            color: #1f2937;
        }

        .system-alert-message {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #5c6472;
            white-space: pre-line;
        }

        .system-alert-details {
            margin-top: 14px;
            text-align: left;
            border: 1px solid #e1e8f1;
            border-radius: 8px;
            background: #f8fbff;
            overflow: hidden;
            display: none;
        }

        .system-alert-detail-row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 10px;
            padding: 9px 12px;
            border-bottom: 1px solid #e9eef6;
            font-size: 13px;
            line-height: 1.45;
        }

        .system-alert-detail-row:last-child {
            border-bottom: none;
        }

        .system-alert-detail-label {
            color: #375574;
            font-weight: 700;
        }

        .system-alert-detail-value {
            color: #1f2937;
            word-break: break-word;
        }

        .system-alert-select-wrap {
            margin-top: 14px;
            display: none;
        }

        .system-alert-select {
            width: 100%;
            border: 1px solid #cfd8e3;
            border-radius: 6px;
            padding: 10px;
            font-size: 14px;
            color: #1f2937;
            background: #fff;
            font-family: 'Poppins', sans-serif;
        }

        .system-alert-actions {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .system-alert-btn {
            border: none;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            cursor: pointer;
            min-width: 145px;
            transition: opacity 0.2s ease;
        }

        .system-alert-btn:hover {
            opacity: 0.92;
        }

        .system-alert-btn-primary {
            background: #111827;
            color: #ffffff;
        }

        .system-alert-btn-cancel {
            background: transparent;
            color: #1f2937;
            text-decoration: none;
            min-width: auto;
            padding: 0;
        }

        @media (max-width: 720px) {
            .modal-grid {
                grid-template-columns: 1fr;
            }

            .system-alert-detail-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }
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
    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="warranty-container">
            <!-- Header -->
            <div class="warranty-header">
                <div class="warranty-header-top">
                    <div>
                        <h1 class="warranty-header-title" style="margin: 0 0 5px 0; color: #ffffff !important; font-size: 28px; font-weight: 700; line-height: 1.2; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);">
                            <i class="fas fa-wrench" style="color: #f4d03f !important; margin-right: 10px;"></i>
                            Warranty Replacements
                        </h1>
                        <p class="warranty-header-subtitle" style="color: #d7e2ef !important; margin: 0; font-size: 13px; line-height: 1.5;">
                            <i class="fas fa-info-circle" style="margin-right: 5px; color: #d7e2ef !important;"></i>
                            Records flagged with RED text during import
                        </p>
                    </div>
                    <button type="button" class="btn-add-record" onclick="openAddWarrantyModal()">
                        <i class="fas fa-plus"></i> Add New Record
                    </button>
                </div>
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
                                            <button class="btn-small btn-view" onclick="viewWarrantyDetail(<?php echo $record['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-small btn-status" onclick="editWarrantyStatus(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['status'] ?? 'Warranty Pending', ENT_QUOTES); ?>')" title="Update Status">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button class="btn-small btn-delete" onclick="deleteWarrantyRecord(<?php echo $record['id']; ?>)" title="Delete Record">
                                                <i class="fas fa-trash"></i>
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
            <div class="warranty-summary-note">
                <i class="fas fa-info-circle"></i>
                Showing <strong><?php echo count($warranty_records); ?></strong> warranty record(s) with total <strong><?php echo $stats['total_qty']; ?></strong> units
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="addWarrantyModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="addWarrantyTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="addWarrantyTitle"><i class="fas fa-plus-circle"></i> Add Warranty Record</h2>
                <button type="button" class="modal-close" onclick="closeAddWarrantyModal()" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="addWarrantyForm">
                    <div class="modal-grid">
                        <div class="modal-field">
                            <label for="add_item_code">Item Code *</label>
                            <input type="text" id="add_item_code" name="item_code" required maxlength="50">
                        </div>
                        <div class="modal-field">
                            <label for="add_item_name">Item Name *</label>
                            <input type="text" id="add_item_name" name="item_name" required maxlength="255">
                        </div>
                        <div class="modal-field">
                            <label for="add_serial_no">Serial No.</label>
                            <input type="text" id="add_serial_no" name="serial_no" maxlength="150">
                        </div>
                        <div class="modal-field">
                            <label for="add_company_name">Company *</label>
                            <input type="text" id="add_company_name" name="company_name" required maxlength="255" list="companySuggestions">
                        </div>
                        <div class="modal-field">
                            <label for="add_quantity">Quantity *</label>
                            <input type="number" id="add_quantity" name="quantity" min="1" step="1" value="1" required>
                        </div>
                        <div class="modal-field">
                            <label for="add_uom">UOM</label>
                            <input type="text" id="add_uom" name="uom" maxlength="20" placeholder="pcs / units">
                        </div>
                        <div class="modal-field">
                            <label for="add_status">Status *</label>
                            <select id="add_status" name="status" required>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $s === 'Warranty Pending' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-field">
                            <label for="add_warranty_date">Warranty Date *</label>
                            <input type="date" id="add_warranty_date" name="warranty_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="modal-field">
                            <label for="add_red_text_detected">Red Text Detected</label>
                            <select id="add_red_text_detected" name="red_text_detected">
                                <option value="1" selected>Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="modal-field full">
                            <label for="add_notes">Notes</label>
                            <textarea id="add_notes" name="notes" maxlength="1000" placeholder="Optional notes"></textarea>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-reset" onclick="closeAddWarrantyModal()">Cancel</button>
                        <button type="submit" class="btn-filter" id="addWarrantySubmitBtn"><i class="fas fa-save"></i> Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <datalist id="companySuggestions">
        <?php foreach ($companies as $company): ?>
            <option value="<?php echo htmlspecialchars($company); ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <div class="system-alert-overlay" id="systemAlertOverlay" aria-hidden="true">
        <div class="system-alert-card info" id="systemAlertCard" role="dialog" aria-modal="true" aria-labelledby="systemAlertTitle">
            <button type="button" class="system-alert-close" id="systemAlertClose" aria-label="Close">&times;</button>
            <div class="system-alert-icon" id="systemAlertIcon"><i class="fas fa-info"></i></div>
            <h3 class="system-alert-title" id="systemAlertTitle">Notice</h3>
            <p class="system-alert-message" id="systemAlertMessage"></p>
            <div class="system-alert-details" id="systemAlertDetails"></div>
            <div class="system-alert-select-wrap" id="systemAlertSelectWrap">
                <select class="system-alert-select" id="systemAlertSelect"></select>
            </div>
            <div class="system-alert-actions">
                <button type="button" class="system-alert-btn system-alert-btn-primary" id="systemAlertConfirmBtn">OK</button>
                <button type="button" class="system-alert-btn system-alert-btn-cancel" id="systemAlertCancelBtn">Cancel</button>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script>
        const ALLOWED_STATUSES = ['Warranty Pending', 'Approved', 'Replaced', 'Cancelled'];
        const addWarrantyModal = document.getElementById('addWarrantyModal');
        const addWarrantyForm = document.getElementById('addWarrantyForm');
        const addWarrantySubmitBtn = document.getElementById('addWarrantySubmitBtn');
        const systemAlertOverlay = document.getElementById('systemAlertOverlay');
        const systemAlertCard = document.getElementById('systemAlertCard');
        const systemAlertIcon = document.getElementById('systemAlertIcon');
        const systemAlertTitle = document.getElementById('systemAlertTitle');
        const systemAlertMessage = document.getElementById('systemAlertMessage');
        const systemAlertConfirmBtn = document.getElementById('systemAlertConfirmBtn');
        const systemAlertCancelBtn = document.getElementById('systemAlertCancelBtn');
        const systemAlertClose = document.getElementById('systemAlertClose');
        const systemAlertSelectWrap = document.getElementById('systemAlertSelectWrap');
        const systemAlertSelect = document.getElementById('systemAlertSelect');
        const systemAlertDetails = document.getElementById('systemAlertDetails');

        let alertResolver = null;

        function getAlertMeta(type) {
            if (type === 'success') {
                return { icon: 'fa-check', title: 'Success' };
            }
            if (type === 'error') {
                return { icon: 'fa-times', title: 'Error' };
            }
            if (type === 'warning') {
                return { icon: 'fa-exclamation', title: 'Warning' };
            }
            return { icon: 'fa-info', title: 'Notice' };
        }

        function closeSystemAlert(confirmed) {
            if (!systemAlertOverlay) {
                return;
            }

            systemAlertOverlay.classList.remove('show');
            systemAlertOverlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';

            if (alertResolver) {
                const payload = {
                    confirmed,
                    value: systemAlertSelectWrap.style.display === 'block' ? systemAlertSelect.value : null
                };
                alertResolver(payload);
                alertResolver = null;
            }
        }

        function showSystemAlert(options = {}) {
            const {
                type = 'info',
                title = '',
                message = '',
                confirmText = 'OK',
                cancelText = 'Cancel',
                showCancel = false,
                showSelect = false,
                selectOptions = [],
                selectedValue = '',
                details = []
            } = options;

            if (!systemAlertOverlay) {
                return Promise.resolve({ confirmed: true, value: null });
            }

            if (alertResolver) {
                alertResolver({ confirmed: false, value: null });
                alertResolver = null;
            }

            const meta = getAlertMeta(type);
            systemAlertCard.className = `system-alert-card ${type}`;
            systemAlertIcon.innerHTML = `<i class="fas ${meta.icon}"></i>`;
            systemAlertTitle.textContent = title || meta.title;
            systemAlertMessage.textContent = String(message || '');
            systemAlertConfirmBtn.textContent = confirmText;
            systemAlertCancelBtn.textContent = cancelText;
            systemAlertCancelBtn.style.display = showCancel ? 'inline-block' : 'none';

            if (Array.isArray(details) && details.length > 0) {
                systemAlertDetails.innerHTML = '';
                details.forEach((row) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'system-alert-detail-row';

                    const label = document.createElement('div');
                    label.className = 'system-alert-detail-label';
                    label.textContent = String(row.label || '-');

                    const value = document.createElement('div');
                    value.className = 'system-alert-detail-value';
                    value.textContent = String(row.value || '-');

                    wrapper.appendChild(label);
                    wrapper.appendChild(value);
                    systemAlertDetails.appendChild(wrapper);
                });
                systemAlertDetails.style.display = 'block';
            } else {
                systemAlertDetails.innerHTML = '';
                systemAlertDetails.style.display = 'none';
            }

            if (showSelect) {
                systemAlertSelect.innerHTML = '';
                selectOptions.forEach((option) => {
                    const opt = document.createElement('option');
                    opt.value = option;
                    opt.textContent = option;
                    systemAlertSelect.appendChild(opt);
                });
                if (selectedValue && selectOptions.includes(selectedValue)) {
                    systemAlertSelect.value = selectedValue;
                }
                systemAlertSelectWrap.style.display = 'block';
            } else {
                systemAlertSelectWrap.style.display = 'none';
            }

            systemAlertOverlay.classList.add('show');
            systemAlertOverlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            setTimeout(() => {
                if (showSelect) {
                    systemAlertSelect.focus();
                } else {
                    systemAlertConfirmBtn.focus();
                }
            }, 0);

            return new Promise((resolve) => {
                alertResolver = resolve;
            });
        }

        async function showAppMessage(message, type = 'info') {
            await showSystemAlert({
                type,
                message,
                confirmText: 'Continue',
                showCancel: false
            });
        }

        systemAlertConfirmBtn.addEventListener('click', () => closeSystemAlert(true));
        systemAlertCancelBtn.addEventListener('click', () => closeSystemAlert(false));
        systemAlertClose.addEventListener('click', () => closeSystemAlert(false));
        systemAlertOverlay.addEventListener('click', (event) => {
            if (event.target === systemAlertOverlay) {
                closeSystemAlert(false);
            }
        });

        // Force native browser dialogs to use system-styled modal dialogs.
        window.alert = function(message) {
            return showSystemAlert({
                type: 'info',
                message: String(message || ''),
                confirmText: 'OK',
                showCancel: false
            });
        };

        window.confirm = async function(message) {
            const result = await showSystemAlert({
                type: 'warning',
                title: 'Please Confirm',
                message: String(message || ''),
                confirmText: 'Continue',
                cancelText: 'Cancel',
                showCancel: true
            });
            return !!result.confirmed;
        };

        function openAddWarrantyModal() {
            if (!addWarrantyModal) {
                return;
            }

            addWarrantyModal.classList.add('show');
            addWarrantyModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            const firstInput = document.getElementById('add_item_code');
            if (firstInput) {
                firstInput.focus();
            }
        }

        function closeAddWarrantyModal() {
            if (!addWarrantyModal) {
                return;
            }

            addWarrantyModal.classList.remove('show');
            addWarrantyModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (addWarrantyForm) {
                addWarrantyForm.reset();
                document.getElementById('add_quantity').value = '1';
                document.getElementById('add_red_text_detected').value = '1';
                document.getElementById('add_status').value = 'Warranty Pending';
                document.getElementById('add_warranty_date').value = '<?php echo date('Y-m-d'); ?>';
            }
        }

        if (addWarrantyModal) {
            addWarrantyModal.addEventListener('click', (event) => {
                if (event.target === addWarrantyModal) {
                    closeAddWarrantyModal();
                }
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && addWarrantyModal && addWarrantyModal.classList.contains('show')) {
                closeAddWarrantyModal();
            }
        });

        if (addWarrantyForm) {
            addWarrantyForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const formData = new FormData(addWarrantyForm);
                const payload = {
                    item_code: (formData.get('item_code') || '').toString().trim(),
                    item_name: (formData.get('item_name') || '').toString().trim(),
                    serial_no: (formData.get('serial_no') || '').toString().trim(),
                    company_name: (formData.get('company_name') || '').toString().trim(),
                    quantity: Number(formData.get('quantity')) || 0,
                    uom: (formData.get('uom') || '').toString().trim(),
                    status: (formData.get('status') || 'Warranty Pending').toString(),
                    warranty_date: (formData.get('warranty_date') || '').toString(),
                    red_text_detected: Number(formData.get('red_text_detected')) === 1 ? 1 : 0,
                    notes: (formData.get('notes') || '').toString().trim()
                };

                if (!payload.item_code || !payload.item_name || !payload.company_name || payload.quantity <= 0) {
                    await showAppMessage('Please complete all required fields.', 'error');
                    return;
                }

                addWarrantySubmitBtn.disabled = true;
                addWarrantySubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                try {
                    const response = await fetch('api/add-warranty-record.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();
                    if (data.success) {
                        await showAppMessage('Warranty record added successfully.', 'success');
                        closeAddWarrantyModal();
                        location.reload();
                    } else {
                        await showAppMessage('Error: ' + (data.message || 'Unable to add warranty record'), 'error');
                    }
                } catch (error) {
                    await showAppMessage('Error adding warranty record: ' + error.message, 'error');
                } finally {
                    addWarrantySubmitBtn.disabled = false;
                    addWarrantySubmitBtn.innerHTML = '<i class="fas fa-save"></i> Save Record';
                }
            });
        }

        function viewWarrantyDetail(warrantId) {
            // Fetch warranty details and show in modal
            fetch('api/get-warranty-detail.php?id=' + warrantId)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showWarrantyDetailModal(data.warranty);
                    } else {
                        showAppMessage('Error: ' + data.message, 'error');
                    }
                })
                .catch(err => showAppMessage('Error loading warranty details: ' + err.message, 'error'));
        }

        function showWarrantyDetailModal(warranty) {
            const details = [
                { label: 'Item Code', value: warranty.item_code || '-' },
                { label: 'Item Name', value: warranty.item_name || '-' },
                { label: 'Serial No.', value: warranty.serial_no || '-' },
                { label: 'Company', value: warranty.company_name || '-' },
                { label: 'Quantity', value: `${warranty.quantity || 0} ${warranty.uom || ''}`.trim() },
                { label: 'Warranty Date', value: warranty.warranty_date ? new Date(warranty.warranty_date).toLocaleDateString() : '-' },
                { label: 'Status', value: warranty.status || '-' }
            ];

            if (warranty.notes) {
                details.push({ label: 'Notes', value: warranty.notes });
            }

            showSystemAlert({
                type: 'info',
                title: 'Warranty Details',
                message: 'Record information:',
                details,
                confirmText: 'Close',
                showCancel: false
            });
        }

        function editWarrantyStatus(warrantId, currentStatus) {
            showSystemAlert({
                type: 'info',
                title: 'Update Status',
                message: `Select the new status for warranty record #${warrantId}.`,
                confirmText: 'Save Status',
                cancelText: 'Cancel',
                showCancel: true,
                showSelect: true,
                selectOptions: ALLOWED_STATUSES,
                selectedValue: currentStatus
            }).then((result) => {
                if (!result.confirmed) {
                    return;
                }

                const newStatus = result.value;
                if (newStatus && newStatus !== currentStatus && ALLOWED_STATUSES.includes(newStatus)) {
                    updateWarrantyStatus(warrantId, newStatus);
                } else if (newStatus === currentStatus) {
                    showAppMessage('Status is already set to that value.', 'info');
                } else {
                    showAppMessage('Invalid status selected', 'error');
                }
            });
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
                    showAppMessage('Status updated successfully', 'success');
                    // Reload page to show updated status
                    setTimeout(() => location.reload(), 500);
                } else {
                    showAppMessage('Error: ' + data.message, 'error');
                }
            })
            .catch(err => showAppMessage('Error updating status: ' + err.message, 'error'));
        }

        async function deleteWarrantyRecord(warrantId) {
            let ok = false;
            if (typeof window.showStyledConfirm === 'function') {
                const result = await window.showStyledConfirm('Delete warranty record #' + warrantId + '? This cannot be undone.', 'Delete Warranty Record');
                ok = !!(result && result.confirmed);
            } else {
                ok = window.confirm('Delete warranty record #' + warrantId + '? This cannot be undone.');
            }
            if (!ok) {
                return;
            }

            fetch('api/delete-warranty-record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: warrantId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAppMessage('Warranty record deleted successfully', 'success');
                    setTimeout(() => location.reload(), 450);
                } else {
                    showAppMessage('Error: ' + data.message, 'error');
                }
            })
            .catch(err => showAppMessage('Error deleting record: ' + err.message, 'error'));
        }
    </script>
</body>
</html>

