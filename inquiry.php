<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

$selectedDataset = isset($_GET['dataset']) ? trim((string) $_GET['dataset']) : (isset($_SESSION['active_dataset']) ? trim((string) $_SESSION['active_dataset']) : 'all');

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function inq_id($id) {
    return 'INQ-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
}

function sanitize_hex_color($value) {
    $color = strtoupper(trim((string) $value));
    if ($color === '') return '';
    if ($color[0] !== '#') $color = '#' . $color;
    return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : '';
}

function hex_to_rgba($hex, $alpha = 0.18) {
    $color = sanitize_hex_color($hex);
    if ($color === '') return '';
    $r = hexdec(substr($color, 1, 2));
    $g = hexdec(substr($color, 3, 2));
    $b = hexdec(substr($color, 5, 2));
    $a = max(0, min(1, floatval($alpha)));
    return "rgba({$r}, {$g}, {$b}, {$a})";
}

function parse_cell_styles($rawStyles) {
    if (!is_string($rawStyles) || trim($rawStyles) === '') return [];
    $decoded = json_decode($rawStyles, true);
    if (!is_array($decoded)) return [];

    $normalized = [];
    foreach ($decoded as $field => $value) {
        $key = strtolower(trim((string) $field));
        if ($key === '') continue;

        if (is_array($value)) {
            $bg = sanitize_hex_color((string) ($value['bg'] ?? ''));
            $text = sanitize_hex_color((string) ($value['text'] ?? ''));
            if ($bg === '' && $text === '') continue;
            $normalized[$key] = ['bg' => $bg, 'text' => $text];
            continue;
        }

        if (!is_scalar($value)) continue;
        $safeColor = sanitize_hex_color((string) $value);
        if ($safeColor === '') continue;
        $normalized[$key] = ['bg' => $safeColor, 'text' => ''];
    }

    return $normalized;
}

function is_inquiry_admin(): bool {
    $name = strtolower(trim((string) ($_SESSION['user_name'] ?? '')));
    $email = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
    $allowedNames = ['admin', 'lizette macalindol'];
    $allowedEmails = ['lizuu131@gmail.com', 'lizettemacalindol.official@gmail.com'];

    return in_array($name, $allowedNames, true) || in_array($email, $allowedEmails, true) || str_contains($email, 'admin');
}

$flash = $_SESSION['inquiry_flash'] ?? null;
unset($_SESSION['inquiry_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_inquiry') {
    if (!is_inquiry_admin()) {
        $_SESSION['inquiry_flash'] = ['type' => 'error', 'message' => 'Admin access is required to add inquiry items.'];
        header('Location: inquiry.php', true, 302);
        exit;
    }

    $customer = trim($_POST['customer'] ?? '');
    $orderDate = trim($_POST['order_date'] ?? '');
    $itemCode = trim($_POST['item_code'] ?? '');
    $itemName = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $unitPrice = floatval($_POST['unit_price'] ?? 0);
    $poNumber = trim($_POST['po_number'] ?? '');
    $poStatus = trim($_POST['po_status'] ?? 'No PO');
    $notes = trim($_POST['notes'] ?? '');
    $highlightColor = strtoupper(trim($_POST['highlight_color'] ?? ''));
    if ($highlightColor !== '' && !preg_match('/^#?[0-9A-F]{6}$/', $highlightColor)) {
        $highlightColor = '';
    }
    if ($highlightColor !== '' && $highlightColor[0] !== '#') {
        $highlightColor = '#' . $highlightColor;
    }

    if ($customer === '' || $orderDate === '' || $itemCode === '' || $itemName === '' || $category === '') {
        $_SESSION['inquiry_flash'] = ['type' => 'error', 'message' => 'Client, date, product code, product name, and groupings are required.'];
        header('Location: inquiry.php', true, 302);
        exit;
    }

    $allowedCategories = ['1A', '1B', '2A', '2B', '3A', '4A'];
    if (!in_array($category, $allowedCategories, true)) {
        $_SESSION['inquiry_flash'] = ['type' => 'error', 'message' => 'Invalid grouping. Allowed values are: 1A, 1B, 2A, 2B, 3A, 4A.'];
        header('Location: inquiry.php', true, 302);
        exit;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $orderDate);
    if (!$dt) {
        $_SESSION['inquiry_flash'] = ['type' => 'error', 'message' => 'Invalid inquiry date format.'];
        header('Location: inquiry.php', true, 302);
        exit;
    }

    $allowedPoStatuses = ['No PO', 'Pending', 'Received'];
    if (!in_array($poStatus, $allowedPoStatuses, true)) {
        $poStatus = 'No PO';
    }

    if ($poStatus === 'Received' && $poNumber === '') {
        $_SESSION['inquiry_flash'] = ['type' => 'error', 'message' => 'PO Number is required before setting PO Status to Received.'];
        header('Location: inquiry.php', true, 302);
        exit;
    }

    $deliveryMonth = $dt->format('F');
    $deliveryDay = intval($dt->format('j'));
    $deliveryYear = intval($dt->format('Y'));
    $deliveryDate = $dt->format('Y-m-d');
    $status = 'Pending';
    $targetCompany = 'Orders';
    if ($poStatus === 'Received') {
        $targetCompany = 'Delivery Records';
        $status = 'Ready for Delivery';
    }
    $soldTo = $customer;
    $datasetName = $selectedDataset !== 'all' ? $selectedDataset : '';
    $totalAmount = $quantity * $unitPrice;

    $sql = "INSERT INTO delivery_records (
                order_customer, order_date, delivery_month, delivery_day, delivery_year, delivery_date,
                item_code, item_name, company_name, quantity, status, highlight_color, notes,
                unit_price, total_amount, po_number, po_status, sold_to, groupings, dataset_name, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            'sssisssssisssddsssss',
            $customer,
            $orderDate,
            $deliveryMonth,
            $deliveryDay,
            $deliveryYear,
            $deliveryDate,
            $itemCode,
            $itemName,
            $targetCompany,
            $quantity,
            $status,
            $highlightColor,
            $notes,
            $unitPrice,
            $totalAmount,
            $poNumber,
            $poStatus,
            $soldTo,
            $category,
            $datasetName
        );

        if ($stmt->execute()) {
            $_SESSION['inquiry_flash'] = [
                'type' => 'success',
                'message' => $poStatus === 'Received'
                    ? 'Inquiry created and routed to Delivery Records.'
                    : 'Inquiry created successfully.'
            ];
            $stmt->close();
            $redirect = ($poStatus === 'Received') ? 'delivery-records.php' : 'inquiry.php';
            if ($selectedDataset !== 'all') {
                $redirect .= '?dataset=' . urlencode($selectedDataset);
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        $_SESSION['inquiry_flash'] = ['type' => 'error', 'message' => 'Failed to create inquiry: ' . $stmt->error];
        $stmt->close();
    } else {
        $_SESSION['inquiry_flash'] = ['type' => 'error', 'message' => 'Failed to prepare inquiry creation query.'];
    }

    header('Location: inquiry.php', true, 302);
    exit;
}

$filter = trim($_GET['filter'] ?? 'all');
$allowedFilters = ['all', 'no_po', 'pending'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$where = "company_name = 'Orders' AND (po_status IS NULL OR po_status = '' OR po_status = 'No PO' OR po_status = 'Pending')";
if ($filter === 'no_po') {
    $where .= " AND (po_status IS NULL OR po_status = '' OR po_status = 'No PO')";
} elseif ($filter === 'pending') {
    $where .= " AND po_status = 'Pending'";
}

$stats = ['total' => 0, 'no_po' => 0, 'pending' => 0];
$countResult = $conn->query("SELECT po_status, COUNT(*) as total FROM delivery_records WHERE company_name = 'Orders' GROUP BY po_status");
if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $poStatus = trim((string) ($row['po_status'] ?? ''));
        $count = intval($row['total'] ?? 0);
        if ($poStatus === 'Pending') {
            $stats['pending'] += $count;
            $stats['total'] += $count;
        } elseif ($poStatus === '' || $poStatus === 'No PO') {
            $stats['no_po'] += $count;
            $stats['total'] += $count;
        }
    }
}

$inquiries = [];
$listSql = "SELECT id, order_customer, order_date, item_code, item_name, quantity, unit_price, po_number, po_status, status, notes, groupings, highlight_color, cell_styles, created_at
            FROM delivery_records
            WHERE $where
            ORDER BY COALESCE(created_at, '1970-01-01 00:00:00') DESC, id DESC";
$listResult = $conn->query($listSql);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $inquiries[] = $row;
    }
}

$canAddInquiry = is_inquiry_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiry - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 30px; overflow-x: hidden; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .page-title { display: flex; align-items: center; gap: 12px; font-size: 30px; font-weight: 700; color: #fff; margin: 0; }
        .page-title i { color: #f4d03f; }
        .page-subtitle { color: #b5c3d4; margin-top: 8px; max-width: 760px; line-height: 1.55; }
        .page-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .action-btn { padding: 12px 18px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: transform .2s ease, box-shadow .2s ease; }
        .action-btn:hover { transform: translateY(-1px); }
        .action-btn.primary { background: linear-gradient(135deg, #f4d03f, #f9d76a); color: #17324d; }
        .action-btn.secondary { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.12); }
        .flash { padding: 14px 16px; border-radius: 12px; margin-bottom: 18px; font-weight: 500; }
        .flash.success { background: rgba(46, 204, 113, 0.15); color: #7dffb0; border: 1px solid rgba(46, 204, 113, 0.25); }
        .flash.error { background: rgba(231, 76, 60, 0.16); color: #ffb5ad; border: 1px solid rgba(231, 76, 60, 0.28); }
        .stats-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: linear-gradient(135deg, #1e2a38, #2a3f5f); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 20px; }
        .stat-label { color: #b5c3d4; font-size: 13px; text-transform: uppercase; letter-spacing: .08em; }
        .stat-value { color: #fff; font-size: 32px; font-weight: 700; margin-top: 8px; }
        .stat-note { color: #95a8bf; font-size: 13px; margin-top: 8px; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .filter-chip { display: inline-flex; align-items: center; padding: 10px 14px; border-radius: 999px; background: rgba(255,255,255,0.06); color: #d9e4f2; text-decoration: none; border: 1px solid rgba(255,255,255,0.08); }
        .filter-chip.active { background: rgba(244, 208, 63, 0.18); color: #ffe688; border-color: rgba(244,208,63,0.35); }
        .panel { background: linear-gradient(135deg, #1e2a38, #2a3f5f); border-radius: 16px; border: 1px solid rgba(255,255,255,0.08); overflow: hidden; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.08); color: #e8eef7; }
        th { background: rgba(255,255,255,0.03); font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #c7d4e4; }
        tbody tr:hover { background: rgba(255,255,255,0.03); }
        .badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge.no-po { background: rgba(231, 76, 60, 0.14); color: #ffb1aa; }
        .badge.pending { background: rgba(241, 196, 15, 0.14); color: #ffe08a; }
        .badge.ready { background: rgba(46, 204, 113, 0.14); color: #8ef0b7; }
        .row-actions {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: nowrap;
        }
        .row-actions a {
            text-decoration: none;
            width: auto;
            justify-content: center;
            white-space: nowrap;
        }
        .empty-state { padding: 48px 20px; text-align: center; color: #9fb1c5; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(10, 16, 24, 0.72); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
        .modal-backdrop.show { display: flex; }
        .modal-card { width: min(920px, 100%); background: linear-gradient(135deg, #1e2a38, #2a3f5f); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); padding: 22px; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
        .modal-title { margin: 0; color: #fff; font-size: 22px; }
        .close-btn { background: transparent; border: none; color: #fff; font-size: 28px; cursor: pointer; line-height: 1; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { color: #dbe7f5; font-size: 14px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; box-sizing: border-box; border-radius: 10px; border: 1px solid rgba(255,255,255,0.14); background: rgba(8,14,22,0.35); color: #fff; padding: 12px 14px; font: inherit; }
        .form-group textarea { min-height: 110px; resize: vertical; }
        .form-span-2 { grid-column: 1 / -1; }
        .form-actions { display: flex; justify-content: flex-end; margin-top: 18px; }
        .helper-note { color: #a9bbce; font-size: 13px; margin: 0 0 18px; }
        html.light-mode .main-content,
        body.light-mode .main-content {
            background: #f8fafc;
        }
        html.light-mode .page-title,
        body.light-mode .page-title {
            color: #1e293b;
        }
        html.light-mode .page-subtitle,
        body.light-mode .page-subtitle {
            color: #475569;
        }
        html.light-mode .action-btn.secondary,
        body.light-mode .action-btn.secondary {
            background: #ffffff;
            color: #1e293b;
            border-color: #cbd5e1;
        }
        html.light-mode .flash.success,
        body.light-mode .flash.success {
            background: #ecfdf5;
            color: #166534;
            border-color: #bbf7d0;
        }
        html.light-mode .flash.error,
        body.light-mode .flash.error {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        html.light-mode .stat-card,
        body.light-mode .stat-card,
        html.light-mode .panel,
        body.light-mode .panel,
        html.light-mode .modal-card,
        body.light-mode .modal-card {
            background: #ffffff;
            border-color: #dbe4f0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        html.light-mode .stat-label,
        body.light-mode .stat-label,
        html.light-mode .stat-note,
        body.light-mode .stat-note,
        html.light-mode .helper-note,
        body.light-mode .helper-note,
        html.light-mode .empty-state,
        body.light-mode .empty-state {
            color: #64748b;
        }
        html.light-mode .stat-value,
        body.light-mode .stat-value,
        html.light-mode .modal-title,
        body.light-mode .modal-title,
        html.light-mode .form-group label,
        body.light-mode .form-group label {
            color: #0f172a;
        }
        html.light-mode .filter-chip,
        body.light-mode .filter-chip {
            background: #ffffff;
            color: #475569;
            border-color: #dbe4f0;
        }
        html.light-mode .filter-chip.active,
        body.light-mode .filter-chip.active {
            background: rgba(244, 208, 63, 0.22);
            color: #1e293b;
            border-color: rgba(244, 208, 63, 0.45);
        }
        html.light-mode th,
        html.light-mode td,
        body.light-mode th,
        body.light-mode td {
            color: #1e293b;
            border-bottom-color: #e2e8f0;
        }
        html.light-mode th,
        body.light-mode th {
            background: #f8fafc;
            color: #475569;
        }
        html.light-mode tbody tr:hover,
        body.light-mode tbody tr:hover {
            background: rgba(15, 23, 42, 0.03);
        }
        html.light-mode .badge.no-po,
        body.light-mode .badge.no-po {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }
        html.light-mode .badge.pending,
        body.light-mode .badge.pending {
            background: rgba(245, 158, 11, 0.14);
            color: #b45309;
        }
        html.light-mode .badge.ready,
        body.light-mode .badge.ready {
            background: rgba(34, 197, 94, 0.12);
            color: #166534;
        }
        html.light-mode .form-group input,
        html.light-mode .form-group select,
        html.light-mode .form-group textarea,
        body.light-mode .form-group input,
        body.light-mode .form-group select,
        body.light-mode .form-group textarea {
            background: #ffffff;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        html.light-mode .close-btn,
        body.light-mode .close-btn {
            color: #0f172a;
        }
        @media (max-width: 900px) {
            .stats-grid, .form-grid { grid-template-columns: 1fr; }
            .main-content { padding: 20px 16px; }
        }
    </style>
</head>
<body>
    <!-- LOADER -->
    <div id="recordsLoader" style="position: fixed; inset: 0; background: linear-gradient(135deg, #1e2a38, #2a3f5f); display: none; align-items: center; justify-content: center; z-index: 99999; opacity: 0; visibility: hidden; pointer-events: none; transition: opacity 0.3s cubic-bezier(0.22, 1, 0.36, 1), visibility 0.3s cubic-bezier(0.22, 1, 0.36, 1);">
        <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
            <div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-file-invoice" style="font-size: 80px; color: #f4d03f; opacity: 0.8;"></i>
            </div>
            <div style="color: #f4d03f; font-weight: 600; letter-spacing: 0.15em;">LOADING<span id="loaderDots">.</span></div>
        </div>
    </div>

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
            <div class="navbar-center" style="flex: 1; text-align: center;">
                <span style="color: #2c3e50; font-size: 18px; font-weight: 600;">BW Gas Detector Sales</span>
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

    <div class="dashboard-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <ul class="sidebar-menu">
                    <li class="menu-item"><a href="index.php" class="menu-link"><i class="fas fa-chart-line"></i><span class="menu-label">Dashboard</span></a></li>
                    <li class="menu-item"><a href="sales-overview.php" class="menu-link"><i class="fas fa-chart-pie"></i><span class="menu-label">Sales Overview</span></a></li>
                    <li class="menu-item"><a href="sales-records.php" class="menu-link"><i class="fas fa-calendar-alt"></i><span class="menu-label">Sales Records</span></a></li>
                    <li class="menu-item active"><a href="inquiry.php" class="menu-link"><i class="fas fa-file-invoice"></i><span class="menu-label">Inquiry</span></a></li>
                    <li class="menu-item"><a href="delivery-records.php" class="menu-link"><i class="fas fa-truck"></i><span class="menu-label">Delivery Records</span></a></li>
                    <li class="menu-item"><a href="inventory.php" class="menu-link"><i class="fas fa-boxes"></i><span class="menu-label">Inventory</span></a></li>
                    <li class="menu-item"><a href="andison-manila.php" class="menu-link"><i class="fas fa-truck-fast"></i><span class="menu-label">Andison Manila</span></a></li>
                    <li class="menu-item"><a href="client-companies.php" class="menu-link"><i class="fas fa-building"></i><span class="menu-label">Client Companies</span></a></li>
                    <li class="menu-item"><a href="models.php" class="menu-link"><i class="fas fa-cube"></i><span class="menu-label">Models</span></a></li>
                    <li class="menu-item"><a href="reports.php" class="menu-link"><i class="fas fa-file-alt"></i><span class="menu-label">Reports</span></a></li>
                    <li class="menu-item"><a href="upload-data.php" class="menu-link"><i class="fas fa-upload"></i><span class="menu-label">Upload Data</span></a></li>
                    <li class="menu-item"><a href="warranty-replacements.php" class="menu-link"><i class="fas fa-wrench"></i><span class="menu-label">Warranty Items</span></a></li>
                    <li class="menu-item"><a href="settings.php" class="menu-link"><i class="fas fa-cog"></i><span class="menu-label">Settings</span></a></li>
                </ul>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="fas fa-file-invoice"></i> Inquiry</h1>
                    <p class="page-subtitle">Client products that are still in inquiry status and have not yet been finalized into a PO. Once the PO is received, continue from Delivery Records.</p>
                </div>
                <div class="page-actions">
                    <button class="action-btn primary" type="button" id="openInquiryModalBtn"><i class="fas fa-plus"></i> Add Inquiry</button>
                    <a class="action-btn secondary" href="delivery-records.php"><i class="fas fa-truck"></i> Delivery Records</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?php echo h($flash['type'] ?? 'success'); ?>"><?php echo h($flash['message'] ?? ''); ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Inquiry Items</div>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-note">All client orders still awaiting PO finalization.</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">No PO</div>
                    <div class="stat-value"><?php echo number_format($stats['no_po']); ?></div>
                    <div class="stat-note">Products ordered by clients but not yet tied to a PO.</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending PO</div>
                    <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                    <div class="stat-note">Items that should continue to Delivery Records once PO is confirmed.</div>
                </div>
            </div>

            <div class="filters">
                <a class="filter-chip <?php echo $filter === 'all' ? 'active' : ''; ?>" href="inquiry.php?filter=all">All</a>
                <a class="filter-chip <?php echo $filter === 'no_po' ? 'active' : ''; ?>" href="inquiry.php?filter=no_po">No PO</a>
                <a class="filter-chip <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="inquiry.php?filter=pending">Pending PO</a>
            </div>

            <div class="panel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Inquiry ID</th>
                                <th>Client</th>
                                <th>Groupings</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Peso Cost</th>
                                <th>PO No.</th>
                                <th>PO Status</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inquiries)): ?>
                                <tr>
                                    <td colspan="11"><div class="empty-state">No inquiry items found for the selected filter.</div></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inquiries as $inquiry): ?>
                                    <?php
                                        $poStatus = trim((string) ($inquiry['po_status'] ?? 'No PO'));
                                        $poClass = 'no-po';
                                        if ($poStatus === 'Pending') {
                                            $poClass = 'pending';
                                        } elseif ($poStatus === 'Received') {
                                            $poClass = 'ready';
                                        }
                                        $poNumber = trim((string) ($inquiry['po_number'] ?? ''));
                                        $rowColor = sanitize_hex_color($inquiry['highlight_color'] ?? '');
                                        $rowBgStyle = $rowColor !== '' ? ' style="background:' . h(hex_to_rgba($rowColor, 0.22)) . ';"' : '';
                                        $cellStyleMap = parse_cell_styles($inquiry['cell_styles'] ?? '');
                                        $groupingStyle = $cellStyleMap['groupings'] ?? ['bg' => $rowColor, 'text' => ''];
                                        $itemCodeStyle = $cellStyleMap['item_code'] ?? ['bg' => '', 'text' => $rowColor];
                                        $itemNameStyle = $cellStyleMap['item_name'] ?? ['bg' => '', 'text' => $rowColor];

                                        $groupingCss = '';
                                        if (!empty($groupingStyle['bg'])) {
                                            $groupingCss .= 'background:' . h(hex_to_rgba($groupingStyle['bg'], 0.28)) . ';';
                                        }
                                        if (!empty($groupingStyle['text'])) {
                                            $groupingCss .= 'color:' . h($groupingStyle['text']) . ';font-weight:700;';
                                        }

                                        $itemNameCss = '';
                                        if (!empty($itemNameStyle['bg'])) {
                                            $itemNameCss .= 'background:' . h(hex_to_rgba($itemNameStyle['bg'], 0.20)) . ';';
                                        }
                                        if (!empty($itemNameStyle['text'])) {
                                            $itemNameCss .= 'color:' . h($itemNameStyle['text']) . ';';
                                        }

                                        $itemCodeCss = '';
                                        if (!empty($itemCodeStyle['bg'])) {
                                            $itemCodeCss .= 'background:' . h(hex_to_rgba($itemCodeStyle['bg'], 0.20)) . ';';
                                        }
                                        if (!empty($itemCodeStyle['text'])) {
                                            $itemCodeCss .= 'color:' . h($itemCodeStyle['text']) . ';';
                                        }
                                    ?>
                                    <tr<?php echo $rowBgStyle; ?>>
                                        <td><?php echo h(inq_id($inquiry['id'])); ?></td>
                                        <td><?php echo h($inquiry['order_customer'] ?: 'N/A'); ?></td>
                                        <td<?php echo $groupingCss !== '' ? ' style="' . $groupingCss . '"' : ''; ?>><?php echo h(trim((string) ($inquiry['groupings'] ?? '')) !== '' ? $inquiry['groupings'] : 'N/A'); ?></td>
                                        <td>
                                            <div style="font-weight: 600;<?php echo $itemNameCss; ?>"><?php echo h($inquiry['item_name']); ?></div>
                                            <div style="font-size: 12px; margin-top: 3px;<?php echo $itemCodeCss !== '' ? $itemCodeCss : 'color:#9fb1c5;'; ?>"><?php echo h($inquiry['item_code']); ?></div>
                                        </td>
                                        <td><?php echo number_format((int) ($inquiry['quantity'] ?? 0)); ?></td>
                                        <td><?php echo 'PHP ' . number_format((float) ($inquiry['unit_price'] ?? 0), 2); ?></td>
                                        <td><?php echo $poNumber !== '' ? h($poNumber) : '<span style="color:#9fb1c5;">None</span>'; ?></td>
                                        <td><span class="badge <?php echo h($poClass); ?>"><?php echo h($poStatus === '' ? 'No PO' : $poStatus); ?></span></td>
                                        <td><span class="badge <?php echo h($poClass); ?>"><?php echo h($inquiry['status'] ?: 'Pending'); ?></span></td>
                                        <td><?php echo h($inquiry['created_at'] ? date('M d, Y', strtotime($inquiry['created_at'])) : ''); ?></td>
                                        <td>
                                            <div class="row-actions">
                                                <a class="action-btn secondary" href="order-details.php?id=<?php echo (int) $inquiry['id']; ?>"><i class="fas fa-eye"></i> View</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div class="modal-backdrop" id="inquiryModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="inquiryModalTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="inquiryModalTitle"><i class="fas fa-plus-circle"></i> Add Inquiry</h2>
                <button type="button" class="close-btn" id="closeInquiryModalBtn" aria-label="Close inquiry modal">&times;</button>
            </div>
            <p class="helper-note">Use this form for client orders that are still in inquiry status. When the PO is received, continue from Delivery Records.</p>
                <form method="post" action="inquiry.php" id="inquiryForm">
                    <input type="hidden" name="action" value="create_inquiry">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="customer">Client</label>
                            <input id="customer" name="customer" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="order_date">Inquiry Date</label>
                            <input id="order_date" name="order_date" type="date" required>
                        </div>
                        <div class="form-group">
                            <label for="item_code">Product Code</label>
                            <input id="item_code" name="item_code" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="item_name">Product Name</label>
                            <input id="item_name" name="item_name" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Groupings (1A, 1B, 2A, 2B, 3A, 4A)</label>
                            <select id="category" name="category" required>
                                <option value="">Select Grouping</option>
                                <option value="1A" selected>1A</option>
                                <option value="1B">1B</option>
                                <option value="2A">2A</option>
                                <option value="2B">2B</option>
                                <option value="3A">3A</option>
                                <option value="4A">4A</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input id="quantity" name="quantity" type="number" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label for="unit_price">Peso Cost</label>
                            <input id="unit_price" name="unit_price" type="number" min="0" step="0.01" value="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="highlight_preset">Color Marker</label>
                            <select id="highlight_preset" onchange="toggleInquiryCustomColor()">
                                <option value="">No Color Marker</option>
                                <option value="#D8B4FE">Katay (Purple)</option>
                                <option value="#FDE68A">Send to Andison (Yellow)</option>
                                <option value="#FCA5A5">Warranty Replacement (Red)</option>
                                <option value="#93C5FD">Warranty to Purchase (Blue)</option>
                                <option value="#F9A8D4">Purchase to Warranty (Pink)</option>
                                <option value="custom">Custom Color...</option>
                            </select>
                            <input id="highlight_color_picker" type="color" value="#FDE68A" style="display:none; margin-top:8px;">
                            <input id="highlight_color" name="highlight_color" type="hidden" value="">
                        </div>
                        <div class="form-group">
                            <label for="po_status">PO Status</label>
                            <select id="po_status" name="po_status">
                                <option>No PO</option>
                                <option>Pending</option>
                                <option>Received</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="po_number">PO Number</label>
                            <input id="po_number" name="po_number" type="text" placeholder="Optional">
                        </div>
                        <div class="form-group form-span-2">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" placeholder="Optional notes"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="action-btn primary" type="submit"><i class="fas fa-save"></i> Save Inquiry</button>
                    </div>
                </form>
            </div>
        </div>

    <script>
        const inquiryModal = document.getElementById('inquiryModal');
        const openInquiryModalBtn = document.getElementById('openInquiryModalBtn');
        const closeInquiryModalBtn = document.getElementById('closeInquiryModalBtn');

        function openInquiryModal() {
            if (!inquiryModal) return;
            inquiryModal.classList.add('show');
            inquiryModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeInquiryModal() {
            if (!inquiryModal) return;
            inquiryModal.classList.remove('show');
            inquiryModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function toggleInquiryCustomColor() {
            const preset = document.getElementById('highlight_preset');
            const picker = document.getElementById('highlight_color_picker');
            const hidden = document.getElementById('highlight_color');
            if (!preset || !picker || !hidden) return;

            if (preset.value === 'custom') {
                picker.style.display = 'block';
                hidden.value = picker.value || '';
            } else {
                picker.style.display = 'none';
                hidden.value = preset.value || '';
            }
        }

        const inquiryColorPicker = document.getElementById('highlight_color_picker');
        if (inquiryColorPicker) {
            inquiryColorPicker.addEventListener('input', function() {
                const hidden = document.getElementById('highlight_color');
                if (hidden) hidden.value = inquiryColorPicker.value || '';
            });
        }

        const inquiryForm = document.getElementById('inquiryForm');
        if (inquiryForm) {
            inquiryForm.addEventListener('submit', function() {
                toggleInquiryCustomColor();
            });
        }

        if (openInquiryModalBtn) {
            openInquiryModalBtn.addEventListener('click', openInquiryModal);
        }

        if (closeInquiryModalBtn) {
            closeInquiryModalBtn.addEventListener('click', closeInquiryModal);
        }

        if (inquiryModal) {
            inquiryModal.addEventListener('click', function (event) {
                if (event.target === inquiryModal) {
                    closeInquiryModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeInquiryModal();
            }
        });

        // Hamburger Menu Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (hamburgerBtn && sidebar) {
            hamburgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed');
                }

                // Keep the user's sidebar preference consistent with other pages.
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
            });

            // Restore saved sidebar state on load.
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                if (mainContent) {
                    mainContent.classList.add('sidebar-collapsed');
                }
            }
        }

        // Profile Dropdown Menu
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        if (profileBtn && profileMenu) {
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
                    profileMenu.classList.remove('show');
                }
            });
        }

        // Close profile menu when item is clicked
        const profileLinks = document.querySelectorAll('#profileMenu a');
        profileLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (profileMenu) profileMenu.classList.remove('show');
            });
        });
    </script>
</body>
</html>