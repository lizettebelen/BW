<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

function so_id($id) {
    return 'SO-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$flash = $_SESSION['orders_flash'] ?? null;
unset($_SESSION['orders_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_order') {
    $customer = 'Andison Internal Order';
    $orderDate = trim($_POST['order_date'] ?? '');
    $poStatus = trim($_POST['po_status'] ?? 'No PO');
    $poNumber = trim($_POST['po_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate order date
    if ($orderDate === '') {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Order date is required.'];
        header('Location: inventory.php?tab=orders', true, 302);
        exit;
    }
    
    $dt = DateTime::createFromFormat('Y-m-d', $orderDate);
    if (!$dt) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Invalid order date format.'];
        header('Location: inventory.php?tab=orders', true, 302);
        exit;
    }

    $allowedPoStatus = ['No PO', 'Pending', 'Received'];
    if (!in_array($poStatus, $allowedPoStatus, true)) {
        $poStatus = 'No PO';
    }

    // Process multiple products
    $products = isset($_POST['products']) && is_array($_POST['products']) ? $_POST['products'] : [];
    
    if (empty($products)) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Please add at least one product.'];
        header('Location: inventory.php?tab=orders', true, 302);
        exit;
    }
    
    $successCount = 0;
    $failureCount = 0;
    $failureMessages = [];
    
    foreach ($products as $product) {
        $itemCode = trim($product['item_code'] ?? '');
        $itemName = trim($product['item_name'] ?? '');
        $quantity = max(1, intval($product['quantity'] ?? 1));
        $unitPrice = floatval($product['unit_price'] ?? 0);
        $foreignCost = floatval($product['foreign_cost'] ?? 0);
        
        // Validate required fields for this product
        if ($itemCode === '' || $itemName === '') {
            $failureCount++;
            $failureMessages[] = 'Product code and name are required for each product.';
            continue;
        }

        $deliveryMonth = $dt->format('F');
        $deliveryDay = intval($dt->format('j'));
        $deliveryYear = intval($dt->format('Y'));
        $status = 'Pending';
        $companyName = 'Orders';
        $totalAmount = $quantity * $unitPrice;
        $productNotes = $notes;
        
        // Add foreign cost to notes if provided
        if ($foreignCost > 0) {
            $costInfo = "[Peso Cost: " . number_format($unitPrice, 2) . " | Foreign Cost: " . number_format($foreignCost, 2) . "]";
            $productNotes = !empty($notes) ? $costInfo . " " . $notes : $costInfo;
        }

        $sql = "INSERT INTO delivery_records (
                    invoice_no, serial_no, delivery_month, delivery_day, delivery_year, delivery_date,
                    item_code, item_name, company_name, quantity, status, notes,
                    order_customer, order_date, unit_price, total_amount, po_number, po_status,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $invoiceNo = '';
            $serialNo = '';
            $stmt->bind_param(
                'sssiissssissssddss',
                $invoiceNo,
                $serialNo,
                $deliveryMonth,
                $deliveryDay,
                $deliveryYear,
                $orderDate,
                $itemCode,
                $itemName,
                $companyName,
                $quantity,
                $status,
                $productNotes,
                $customer,
                $orderDate,
                $unitPrice,
                $totalAmount,
                $poNumber,
                $poStatus
            );

            if ($stmt->execute()) {
                $successCount++;
            } else {
                $failureCount++;
                $failureMessages[] = 'Failed to create order for ' . $itemName . ': ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $failureCount++;
            $failureMessages[] = 'Failed to prepare order creation query.';
        }
    }
    
    // Set result message
    if ($successCount > 0 && $failureCount === 0) {
        $_SESSION['orders_flash'] = ['type' => 'success', 'message' => 'Order created successfully with ' . $successCount . ' product(s).'];
    } elseif ($successCount > 0 && $failureCount > 0) {
        $_SESSION['orders_flash'] = ['type' => 'warning', 'message' => 'Created ' . $successCount . ' product(s) but ' . $failureCount . ' failed. ' . implode(' ', $failureMessages)];
    } else {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Failed to create order. ' . implode(' ', $failureMessages)];
    }

    header('Location: inventory.php?tab=orders', true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_order') {
    $deleteId = intval($_POST['order_id'] ?? 0);

    if ($deleteId <= 0) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Invalid order id.'];
        header('Location: orders.php', true, 302);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM delivery_records WHERE id = ? AND company_name = 'Orders'");
    if ($stmt) {
        $stmt->bind_param('i', $deleteId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['orders_flash'] = ['type' => 'success', 'message' => 'Order deleted successfully.'];
            } else {
                $_SESSION['orders_flash'] = ['type' => 'warning', 'message' => 'Order not found or already deleted.'];
            }
        } else {
            $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Failed to delete order: ' . $stmt->error];
        }
        $stmt->close();
    } else {
        $_SESSION['orders_flash'] = ['type' => 'error', 'message' => 'Failed to prepare delete query.'];
    }

    $redirectFilter = trim($_POST['redirect_filter'] ?? 'all');
    $allowedRedirectFilters = ['all', 'with_po', 'no_po', 'delivered'];
    if (!in_array($redirectFilter, $allowedRedirectFilters, true)) {
        $redirectFilter = 'all';
    }

    header('Location: orders.php?filter=' . urlencode($redirectFilter), true, 302);
    exit;
}

$filter = trim($_GET['filter'] ?? 'all');
$allowedFilters = ['all', 'with_po', 'no_po', 'delivered'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$where = "company_name = 'Orders'";
if ($filter === 'with_po') {
    $where .= " AND ((po_number IS NOT NULL AND po_number != '') OR po_status IN ('Pending', 'Received'))";
} elseif ($filter === 'no_po') {
    $where .= " AND ((po_number IS NULL OR po_number = '') AND (po_status IS NULL OR po_status = '' OR po_status = 'No PO'))";
} elseif ($filter === 'delivered') {
    $where .= " AND status = 'Delivered'";
}

$linkedInvoices = [];
$linkedOrderRefs = [];
$linkedResult = $conn->query("SELECT invoice_no, notes FROM delivery_records WHERE company_name != 'Orders'");
if ($linkedResult) {
    while ($linkedRow = $linkedResult->fetch_assoc()) {
        $linkedInvoice = trim((string) ($linkedRow['invoice_no'] ?? ''));
        if ($linkedInvoice !== '') {
            $linkedInvoices[$linkedInvoice] = true;
        }

        $linkedNotes = (string) ($linkedRow['notes'] ?? '');
        if ($linkedNotes !== '' && preg_match_all('/SO-\d+/i', $linkedNotes, $matches)) {
            foreach ($matches[0] as $ref) {
                $linkedOrderRefs[strtoupper($ref)] = true;
            }
        }
    }
}

$orders = [];
$listSql = "SELECT id, order_customer, order_date, item_code, item_name, quantity, unit_price, total_amount, invoice_no, po_number, po_status, status, created_at
            FROM delivery_records
            WHERE $where
            ORDER BY id DESC";
$listResult = $conn->query($listSql);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $orderInvoice = trim((string) ($row['invoice_no'] ?? ''));
        $orderRef = so_id(intval($row['id'] ?? 0));

        // Hide orders that already exist in delivery records.
        if (($orderInvoice !== '' && isset($linkedInvoices[$orderInvoice])) || isset($linkedOrderRefs[$orderRef])) {
            continue;
        }

        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title i { color: #f4d03f; }
        .action-btn {
            border: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(135deg, #2f5fa7, #1f4174);
            color: #fff;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .action-btn:hover { transform: translateY(-1px); }
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .filter-chip {
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--color-border);
            color: var(--color-text-lighter);
            text-decoration: none;
            font-size: 13px;
            background: rgba(255,255,255,0.04);
            transition: all 0.2s ease;
        }
        .filter-chip:hover {
            color: var(--color-text-light);
            border-color: var(--color-primary);
        }
        .filter-chip.active {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #fff;
        }
        html.light-mode .filter-chip,
        body.light-mode .filter-chip {
            background: #ffffff;
            color: #3a6a8a;
            border-color: #b8d4e8;
        }
        html.light-mode .filter-chip:hover,
        body.light-mode .filter-chip:hover {
            background: #eef6fd;
            color: #1e4f7a;
            border-color: #1e88e5;
        }
        html.light-mode .filter-chip.active,
        body.light-mode .filter-chip.active {
            background: #2f5fa7;
            border-color: #2f5fa7;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(47, 95, 167, 0.25);
        }
        .form-card,
        .table-container {
            background: var(--color-dark-secondary);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 18px;
        }

        .create-order-modal {
            position: fixed;
            inset: 0;
            background: rgba(7, 12, 22, 0.58);
            backdrop-filter: blur(3px);
            z-index: 1300;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .create-order-modal.show {
            display: flex;
        }
        .create-order-dialog {
            width: min(1120px, 96vw);
            max-height: 88vh;
            overflow: auto;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 12px;
        }
        .modal-title {
            margin: 0;
            color: var(--color-text-light);
            font-size: 20px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .close-btn {
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.06);
            color: var(--color-text-light);
            width: 34px;
            height: 34px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 17px;
            line-height: 1;
        }
        .close-btn:hover {
            background: rgba(255,255,255,0.13);
        }

        html.light-mode .create-order-modal,
        body.light-mode .create-order-modal {
            background: rgba(24, 61, 96, 0.22);
        }
        html.light-mode .close-btn,
        body.light-mode .close-btn {
            border-color: #b8d4e8;
            background: #ffffff;
            color: #1e4f7a;
        }
        html.light-mode .close-btn:hover,
        body.light-mode .close-btn:hover {
            background: #eef6fd;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #b8c2cf;
            font-size: 13px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.13);
            background: rgba(255,255,255,0.03);
            color: #fff;
            font-family: inherit;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-actions { margin-top: 12px; }
        .save-order-btn {
            min-width: 140px;
            justify-content: center;
        }
        .save-order-btn.loading {
            opacity: 0.9;
            pointer-events: none;
            cursor: not-allowed;
        }
        .save-order-btn .btn-loader {
            display: none;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: saveSpin 0.7s linear infinite;
        }
        .save-order-btn.loading .btn-loader {
            display: inline-block;
        }
        .save-order-btn.loading .btn-icon {
            display: none;
        }
        @keyframes saveSpin {
            to { transform: rotate(360deg); }
        }
        .flash {
            margin-bottom: 14px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
        }
        .flash.success {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid rgba(46, 204, 113, 0.35);
            color: #b3f5cb;
        }
        .flash.error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.35);
            color: #ffd2cc;
        }
        table.orders-table { width: 100%; border-collapse: collapse; min-width: 980px; }
        table.orders-table th,
        table.orders-table td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            text-align: left;
        }
        table.orders-table th {
            color: #9fb1c5;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.4px;
        }
        tr.clickable-row { cursor: pointer; }
        tr.clickable-row:hover { background: rgba(47,95,167,0.15); }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            font-size: 12px;
            padding: 4px 10px;
        }
        .badge.no-po { background: rgba(231,76,60,.18); color: #ffd2cc; }
        .badge.pending { background: rgba(241,196,15,.18); color: #ffeeb0; }
        .badge.received { background: rgba(46,204,113,.18); color: #c6f7d8; }
        .badge.delivered { background: rgba(39,174,96,.22); color: #c9f3d9; }
        .order-action-cell {
            text-align: center;
            white-space: nowrap;
        }
        .order-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .order-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .order-action-btn.view {
            color: #60a8ff;
            background: rgba(96, 168, 255, 0.14);
        }
        .order-action-btn.view:hover {
            color: #ffffff;
            background: rgba(96, 168, 255, 0.34);
        }
        .order-action-btn.edit {
            color: #f3be4d;
            background: rgba(243, 190, 77, 0.14);
        }
        .order-action-btn.edit:hover {
            color: #ffffff;
            background: rgba(243, 190, 77, 0.32);
        }
        .order-action-btn.delete {
            color: #ff7f7f;
            background: rgba(231, 76, 60, 0.14);
            border: none;
            font-family: inherit;
            cursor: pointer;
        }
        .order-action-btn.delete:hover {
            color: #ffffff;
            background: rgba(231, 76, 60, 0.34);
        }
        .order-delete-form {
            margin: 0;
        }
        .delete-confirm-modal {
            position: fixed;
            inset: 0;
            background: rgba(7, 12, 22, 0.62);
            backdrop-filter: blur(3px);
            z-index: 1400;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .delete-confirm-modal.show {
            display: flex;
        }
        .delete-confirm-card {
            width: min(460px, 94vw);
            background: var(--color-dark-secondary);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
        }
        .delete-confirm-title {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--color-text-light);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .delete-confirm-title i {
            color: #ff7f7f;
        }
        .delete-confirm-text {
            margin: 0;
            color: #b8c2cf;
            font-size: 14px;
            line-height: 1.5;
        }
        .delete-confirm-actions {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel-delete,
        .btn-confirm-delete {
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.15);
            font-family: inherit;
        }
        .btn-cancel-delete {
            background: rgba(255,255,255,0.06);
            color: #d7e2f0;
        }
        .btn-cancel-delete:hover {
            background: rgba(255,255,255,0.12);
        }
        .btn-confirm-delete {
            background: linear-gradient(135deg, #d35454, #b93f3f);
            color: #fff;
            border-color: rgba(211, 84, 84, 0.65);
        }
        .btn-confirm-delete:hover {
            filter: brightness(1.06);
        }
        html.light-mode .delete-confirm-modal,
        body.light-mode .delete-confirm-modal {
            background: rgba(24, 61, 96, 0.24);
        }
        html.light-mode .delete-confirm-card,
        body.light-mode .delete-confirm-card {
            background: #ffffff;
            border-color: #d8e2ef;
        }
        html.light-mode .delete-confirm-title,
        body.light-mode .delete-confirm-title {
            color: #1a1a1a;
        }
        html.light-mode .delete-confirm-text,
        body.light-mode .delete-confirm-text {
            color: #52657a;
        }
        html.light-mode .btn-cancel-delete,
        body.light-mode .btn-cancel-delete {
            background: #f2f7fc;
            border-color: #d8e2ef;
            color: #2f4c68;
        }
        html.light-mode .btn-cancel-delete:hover,
        body.light-mode .btn-cancel-delete:hover {
            background: #e6f0fa;
        }
        @media (max-width: 860px) {
            .create-order-dialog {
                width: 100%;
                max-height: 92vh;
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
                        <span class="profile-name"><?php echo h($_SESSION['user_name'] ?? 'User'); ?></span>
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
                    <li class="menu-item"><a href="inquiry.php" class="menu-link"><i class="fas fa-file-invoice"></i><span class="menu-label">Inquiry</span></a></li>
                    <li class="menu-item"><a href="delivery-records.php" class="menu-link"><i class="fas fa-truck"></i><span class="menu-label">Delivery Records</span></a></li>
                    <li class="menu-item"><a href="inventory.php" class="menu-link"><i class="fas fa-boxes"></i><span class="menu-label">Inventory</span></a></li>
                    <li class="menu-item"><a href="andison-manila.php" class="menu-link"><i class="fas fa-truck-fast"></i><span class="menu-label">Andison Manila</span></a></li>
                    <li class="menu-item"><a href="client-companies.php" class="menu-link"><i class="fas fa-building"></i><span class="menu-label">Client Companies</span></a></li>
                    <li class="menu-item"><a href="models.php" class="menu-link"><i class="fas fa-cube"></i><span class="menu-label">Models</span></a></li>
                    <li class="menu-item"><a href="reports.php" class="menu-link"><i class="fas fa-file-alt"></i><span class="menu-label">Reports</span></a></li>
                    <li class="menu-item"><a href="upload-data.php" class="menu-link"><i class="fas fa-upload"></i><span class="menu-label">Upload Data</span></a></li>
                    <li class="menu-item"><a href="settings.php" class="menu-link"><i class="fas fa-cog"></i><span class="menu-label">Settings</span></a></li>
                </ul>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-file-invoice-dollar"></i> Orders / Sales Orders</h1>
                <button class="action-btn" id="toggleCreateBtn"><i class="fas fa-plus"></i> Create Order</button>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
            <?php endif; ?>

            <div class="filters">
                <a class="filter-chip <?php echo $filter === 'all' ? 'active' : ''; ?>" href="orders.php?filter=all">All</a>
                <a class="filter-chip <?php echo $filter === 'with_po' ? 'active' : ''; ?>" href="orders.php?filter=with_po">With PO</a>
                <a class="filter-chip <?php echo $filter === 'no_po' ? 'active' : ''; ?>" href="orders.php?filter=no_po">No PO</a>
                <a class="filter-chip <?php echo $filter === 'delivered' ? 'active' : ''; ?>" href="orders.php?filter=delivered">Delivered</a>
            </div>

            <div class="table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Reference No.</th>
                            <th>PO Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="6" style="color:#9fb1c5;">No orders found for this filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                    $poClass = 'no-po';
                                    if (($order['po_status'] ?? '') === 'Pending') {
                                        $poClass = 'pending';
                                    } elseif (($order['po_status'] ?? '') === 'Received') {
                                        $poClass = 'received';
                                    }
                                ?>
                                <tr class="clickable-row" onclick="handleOrderRowClick(event, <?php echo intval($order['id']); ?>)">
                                    <td><?php echo h(so_id($order['id'])); ?></td>
                                    <td><?php echo h($order['order_customer'] ?: 'N/A'); ?></td>
                                    <td>PHP <?php echo number_format(floatval($order['total_amount'] ?? 0), 2); ?></td>
                                    <td>
                                        <?php $referenceNo = trim((string) ($order['invoice_no'] ?? '')); ?>
                                        <?php if ($referenceNo !== ''): ?>
                                            <?php echo h($referenceNo); ?>
                                        <?php else: ?>
                                            <span style="color: #ffcc80; font-weight: 600;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo h($poClass); ?>"><?php echo h($order['po_status'] ?: 'No PO'); ?></span></td>
                                    <td class="order-action-cell">
                                        <div class="order-actions">
                                            <a class="order-action-btn view" href="order-details.php?id=<?php echo intval($order['id']); ?>" onclick="event.stopPropagation();"><i class="fas fa-eye"></i> View</a>
                                            <a class="order-action-btn edit" href="order-details.php?id=<?php echo intval($order['id']); ?>" onclick="event.stopPropagation();"><i class="fas fa-pen"></i> Edit</a>
                                            <form method="post" class="order-delete-form js-delete-form" action="orders.php" onclick="event.stopPropagation();" onsubmit="event.stopPropagation(); return false;" data-order-label="<?php echo h(so_id($order['id'])); ?>">
                                                <input type="hidden" name="action" value="delete_order">
                                                <input type="hidden" name="order_id" value="<?php echo intval($order['id']); ?>">
                                                <input type="hidden" name="redirect_filter" value="<?php echo h($filter); ?>">
                                                <button type="submit" class="order-action-btn delete" onclick="event.stopPropagation();" title="Delete order"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="create-order-modal" id="createOrderModal" aria-hidden="true">
        <div class="form-card create-order-dialog" role="dialog" aria-modal="true" aria-labelledby="createOrderTitle">
            <div class="modal-header">
                <h2 class="modal-title" id="createOrderTitle"><i class="fas fa-plus-circle"></i> Create New Order</h2>
                <button type="button" class="close-btn" id="closeCreateBtn" aria-label="Close create order">&times;</button>
            </div>

            <form method="post" action="orders.php" id="createOrderForm">
                <input type="hidden" name="action" value="create_order">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="customer">Customer</label>
                        <input id="customer" name="customer" type="text" required>
                    </div>
                    <div class="form-group">
                        <label for="order_date">Order Date</label>
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
                        <label for="quantity">Quantity</label>
                        <input id="quantity" name="quantity" type="number" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="unit_price">Unit Price</label>
                        <input id="unit_price" name="unit_price" type="number" min="0" step="0.01" value="0">
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
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="action-btn save-order-btn" type="submit" id="saveOrderBtn">
                        <span class="btn-loader" aria-hidden="true"></span>
                        <i class="fas fa-save btn-icon" aria-hidden="true"></i>
                        <span class="btn-text">Save Order</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="delete-confirm-modal" id="deleteConfirmModal" aria-hidden="true">
        <div class="delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
            <h3 class="delete-confirm-title" id="deleteConfirmTitle"><i class="fas fa-triangle-exclamation"></i> Confirm Delete</h3>
            <p class="delete-confirm-text" id="deleteConfirmText">Are you sure you want to delete this order?</p>
            <div class="delete-confirm-actions">
                <button type="button" class="btn-cancel-delete" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn-confirm-delete" id="confirmDeleteBtn">Delete Order</button>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script>
        function handleOrderRowClick(event, orderId) {
            if (!event) return;

            const actionArea = event.target.closest('.order-action-cell');
            if (actionArea) {
                return;
            }

            window.location.href = 'order-details.php?id=' + orderId;
        }

        const toggleCreateBtn = document.getElementById('toggleCreateBtn');
        const createOrderModal = document.getElementById('createOrderModal');
        const closeCreateBtn = document.getElementById('closeCreateBtn');
        const createOrderForm = document.getElementById('createOrderForm');
        const saveOrderBtn = document.getElementById('saveOrderBtn');
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        const deleteConfirmText = document.getElementById('deleteConfirmText');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const deleteForms = document.querySelectorAll('.js-delete-form');
        const shouldAutoOpen = <?php echo ($flash && ($flash['type'] ?? '') === 'error') ? 'true' : 'false'; ?>;
        let pendingDeleteForm = null;

        function openCreateOrderModal() {
            if (!createOrderModal) return;
            createOrderModal.classList.add('show');
            createOrderModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeCreateOrderModal() {
            if (!createOrderModal) return;
            createOrderModal.classList.remove('show');
            createOrderModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function openDeleteModal(form) {
            if (!deleteConfirmModal) return;
            pendingDeleteForm = form;
            const orderLabel = form.getAttribute('data-order-label') || 'this order';
            if (deleteConfirmText) {
                deleteConfirmText.textContent = 'Delete ' + orderLabel + '? This action cannot be undone.';
            }
            deleteConfirmModal.classList.add('show');
            deleteConfirmModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            if (!deleteConfirmModal) return;
            deleteConfirmModal.classList.remove('show');
            deleteConfirmModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            pendingDeleteForm = null;
        }

        if (toggleCreateBtn && createOrderModal) {
            toggleCreateBtn.addEventListener('click', function () {
                openCreateOrderModal();
            });

            if (closeCreateBtn) {
                closeCreateBtn.addEventListener('click', closeCreateOrderModal);
            }

            createOrderModal.addEventListener('click', function (event) {
                if (event.target === createOrderModal) {
                    closeCreateOrderModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && createOrderModal.classList.contains('show')) {
                    closeCreateOrderModal();
                }
            });

            if (shouldAutoOpen) {
                openCreateOrderModal();
            }

            if (createOrderForm && saveOrderBtn) {
                createOrderForm.addEventListener('submit', function () {
                    saveOrderBtn.classList.add('loading');
                    saveOrderBtn.setAttribute('disabled', 'disabled');
                    const textEl = saveOrderBtn.querySelector('.btn-text');
                    if (textEl) {
                        textEl.textContent = 'Saving...';
                    }
                });
            }
        }

        deleteForms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openDeleteModal(form);
            });
        });

        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', closeDeleteModal);
        }

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function () {
                if (pendingDeleteForm) {
                    pendingDeleteForm.submit();
                }
            });
        }

        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('click', function (event) {
                if (event.target === deleteConfirmModal) {
                    closeDeleteModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && deleteConfirmModal && deleteConfirmModal.classList.contains('show')) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
