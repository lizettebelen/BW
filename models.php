<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

function identifyGrouping(string $itemName, string $groupings = ''): string {
    $grouping = strtolower(trim($groupings));
    if ($grouping !== '') {
        if (strpos($grouping, 'multi') !== false || strpos($grouping, 'group b') !== false) {
            return 'Group B - Multi Gas';
        }
        if (strpos($grouping, 'single') !== false || strpos($grouping, 'group a') !== false) {
            return 'Group A - Single Gas';
        }
    }

    $lowerName = strtolower($itemName);

    // Multi Gas indicators
    if (strpos($lowerName, 'multi') !== false || 
        strpos($lowerName, 'quattro') !== false || 
        strpos($lowerName, 'quad') !== false ||
        strpos($lowerName, 'o2/lel/h2s/co') !== false ||
        preg_match('/o2.*lel|lel.*o2/', $lowerName)) {
        return 'Group B - Multi Gas';
    }

    // Single Gas indicators
    if (strpos($lowerName, 'single') !== false ||
        preg_match('/\b(O2|LEL|H2S|CO)\b/i', $lowerName)) {
        return 'Group A - Single Gas';
    }

    // Default to Single Gas if unclear
    return 'Group A - Single Gas';
}

// Fetch all unique item_codes with their stats from delivery_records (inventory)
$allModels = [];
$groupA = [];  // Single Gas Detectors
$groupB = [];  // Multi Gas Detectors

// List of month names to exclude from being treated as item codes
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
$monthRegex = implode('|', $monthNames);

$result = $conn->query("
    SELECT 
        item_code,
        MAX(item_name) as item_name,
        MAX(groupings) as groupings,
        SUM(quantity) as total_qty,
        COUNT(*) as order_count,
        MAX(delivery_year) as last_year,
        MAX(delivery_month) as last_month,
        MAX(box_code) as box_code
    FROM delivery_records
    WHERE company_name = 'Stock Addition'
      AND item_code IS NOT NULL 
      AND item_code != ''
    GROUP BY item_code
    HAVING item_name IS NOT NULL AND item_name != ''
    ORDER BY total_qty DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $code = trim($row['item_code']);
        $name = trim($row['item_name']);
        
        // Skip if item_code is just a month name or empty
        if (preg_match('/^(' . $monthRegex . ')$/i', $code) || empty($name)) {
            continue;
        }
        
        $allModels[] = $row;
        
        $groupLabel = identifyGrouping($name, (string) ($row['groupings'] ?? ''));

        if ($groupLabel === 'Group B - Multi Gas') {
            $groupB[] = $row;
        } else {
            $groupA[] = $row;
        }
    }
}

// Calculate total stats
$totalModels = count($allModels);
$totalQty = array_sum(array_column($allModels, 'total_qty'));
$totalOrders = array_sum(array_column($allModels, 'order_count'));

$groupings = [
    'Group A' => $groupA,
    'Group B' => $groupB
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Models - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Page title ── */
        .page-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--color-text-light);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title i { color: var(--color-accent); }

        /* ── Summary strip ── */
        .models-summary {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .models-summary-item {
            background: var(--color-dark-secondary);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 12px 20px;
            min-width: 130px;
            flex: 0 1 calc(33.333% - 8px);
        }
        .models-summary-item .ms-label {
            font-size: 11px;
            color: var(--color-text-lighter);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .models-summary-item .ms-value {
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
        }
        .ms-value.accent  { color: var(--color-accent); }
        .ms-value.cyan    { color: #00d9ff; }
        .ms-value.primary { color: var(--color-text-light); }

        /* ── Group cards grid ── */
        .group-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            width: 100%;
            max-width: 100%;
        }

        /* ── Individual group card ── */
        .group-card {
            background: var(--color-dark-secondary);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            font-family: 'Poppins', sans-serif;
        }
        .group-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.35);
        }
        .group-card.card-a:hover { border-color: #2f5fa7; }
        .group-card.card-b:hover { border-color: #0891b2; }

        .group-card-banner {
            padding: 28px 20px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            position: relative;
            overflow: hidden;
        }
        .card-a .group-card-banner {
            background: linear-gradient(135deg, #1e4fa0 0%, #2f5fa7 50%, #0090c8 100%);
        }
        .card-b .group-card-banner {
            background: linear-gradient(135deg, #0e7490 0%, #0891b2 50%, #00d9ff 100%);
        }
        .group-card-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 70% 30%, rgba(255,255,255,0.12) 0%, transparent 70%);
        }
        .group-letter {
            font-size: 64px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            letter-spacing: -2px;
            position: relative;
            text-shadow: 0 4px 16px rgba(0,0,0,0.25);
        }
        .group-name {
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            letter-spacing: 0.5px;
            position: relative;
        }

        .group-card-body {
            padding: 18px;
        }
        .group-kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-bottom: 14px;
        }
        .group-kpi {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 8px 4px;
            text-align: center;
        }
        .group-kpi-value {
            font-size: 16px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 3px;
        }
        .card-a .group-kpi-value.v1 { color: var(--color-accent); }
        .card-a .group-kpi-value.v2 { color: #00d9ff; }
        .card-a .group-kpi-value.v3 { color: var(--color-text-light); }
        .card-b .group-kpi-value.v1 { color: var(--color-accent); }
        .card-b .group-kpi-value.v2 { color: #00d9ff; }
        .card-b .group-kpi-value.v3 { color: var(--color-text-light); }
        .group-kpi-label {
            font-size: 9px;
            color: var(--color-text-lighter);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .group-card-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: var(--color-text-lighter);
            padding-top: 10px;
            border-top: 1px solid var(--color-border);
            transition: color 0.2s;
        }
        .group-card:hover .group-card-cta { color: var(--color-accent); }
        .group-card-cta i { font-size: 11px; transition: transform 0.2s; }
        .group-card:hover .group-card-cta i { transform: translateX(3px); }

        /* ── Content Area ── */
        .main-content {
            padding: 32px 40px;
        }

        /* ── Modal ── */
        .gmodal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.72);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(2px);
        }
        .gmodal-box {
            background: var(--color-dark-secondary);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            width: 100%;
            max-width: 820px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 64px rgba(0,0,0,0.55);
            font-family: 'Poppins', sans-serif;
        }
        .gmodal-header {
            padding: 22px 28px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .gmodal-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--color-text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .gmodal-title .badge-a { background: rgba(47,95,167,0.25); color: #6ea8fe; }
        .gmodal-title .badge-b { background: rgba(124,58,237,0.25); color: #c084fc; }
        .gmodal-title .group-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .gmodal-close {
            background: none;
            border: none;
            color: var(--color-text-lighter);
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            line-height: 1;
            transition: background 0.15s, color 0.15s;
        }
        .gmodal-close:hover {
            background: rgba(255,255,255,0.08);
            color: var(--color-text-light);
        }
        .gmodal-search {
            padding: 14px 28px;
            border-bottom: 1px solid var(--color-border);
            flex-shrink: 0;
        }
        .gmodal-search input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--color-border);
            border-radius: 7px;
            padding: 10px 14px 10px 38px;
            color: var(--color-text-light);
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 14px center;
            transition: border-color 0.2s;
        }
        .gmodal-search input:focus { border-color: var(--color-primary); }
        .gmodal-table-wrap {
            overflow-y: auto;
            flex: 1;
            padding: 0 28px 16px;
        }
        .gmodal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .gmodal-table thead th {
            font-size: 11px;
            color: var(--color-text-lighter);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 10px 10px 0;
            border-bottom: 1px solid var(--color-border);
            position: sticky;
            top: 0;
            background: var(--color-dark-secondary);
            white-space: nowrap;
        }
        .gmodal-table thead th:last-child,
        .gmodal-table thead th:nth-last-child(2) { text-align: right; padding-right: 0; }
        .gmodal-table tbody tr {
            border-bottom: 1px solid rgba(255,255,255,0.04);
            transition: background 0.15s;
        }
        .gmodal-table tbody tr:hover { background: rgba(255,255,255,0.03); }
        .gmodal-table td {
            padding: 13px 10px 13px 0;
            vertical-align: middle;
        }
        .gmodal-table td:last-child,
        .gmodal-table td:nth-last-child(1) { text-align: right; padding-right: 0; }
        .td-num  { color: rgba(255,255,255,0.25); font-size: 12px; width: 32px; }
        .td-code { color: var(--color-accent); font-weight: 600; font-size: 14px; }
        .td-name { color: var(--color-text-lighter); font-size: 13px; }
        .td-units { color: #00d9ff; font-weight: 700; text-align: right !important; font-size: 14px; }
        .td-orders { color: var(--color-text-light); text-align: right !important; padding-right: 4px !important; font-size: 14px; }
        .gmodal-footer {
            padding: 13px 28px;
            border-top: 1px solid var(--color-border);
            font-size: 12px;
            color: var(--color-text-lighter);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            gap: 12px;
        }
        .gmodal-footer strong { color: var(--color-text-light); }
        .gmodal-empty {
            display: none;
            text-align: center;
            padding: 48px 20px;
            color: var(--color-text-lighter);
        }
        .gmodal-empty i { display: block; font-size: 28px; margin-bottom: 10px; opacity: 0.35; }

        /* ── Light mode adjustments ── */
        html.light-mode .group-card,
        body.light-mode .group-card {
            background: #ffffff;
            border-color: #dde2ec;
        }
        html.light-mode .group-kpi,
        body.light-mode .group-kpi {
            background: rgba(0,0,0,0.03);
            border-color: #dde2ec;
        }
        html.light-mode .group-kpi-label,
        body.light-mode .group-kpi-label { color: #5a7a9a; }
        html.light-mode .group-card-cta,
        body.light-mode .group-card-cta { color: #5a7a9a; border-color: #dde2ec; }
        html.light-mode .gmodal-box,
        body.light-mode .gmodal-box { background: #ffffff; border-color: #dde2ec; }
        html.light-mode .gmodal-header,
        html.light-mode .gmodal-search,
        html.light-mode .gmodal-footer,
        body.light-mode .gmodal-header,
        body.light-mode .gmodal-search,
        body.light-mode .gmodal-footer { border-color: #dde2ec; }
        html.light-mode .gmodal-title,
        body.light-mode .gmodal-title { color: #1a3a5c; }
        html.light-mode .gmodal-search input,
        body.light-mode .gmodal-search input {
            background: #f4f8fc;
            border-color: #b8d4e8;
            color: #1a3a5c;
        }
        html.light-mode .gmodal-table thead,
        body.light-mode .gmodal-table thead { background: #ffffff; }
        html.light-mode .gmodal-table thead th,
        body.light-mode .gmodal-table thead th { background: #ffffff; border-color: #dde2ec; }
        html.light-mode .gmodal-table tbody tr:hover,
        body.light-mode .gmodal-table tbody tr:hover { background: #f0f7ff; }
        html.light-mode .td-name,
        body.light-mode .td-name { color: #4a6a8a; }
        html.light-mode .td-orders,
        body.light-mode .td-orders { color: #1a3a5c; }
        html.light-mode .models-summary-item,
        body.light-mode .models-summary-item { background: #fff; border-color: #dde2ec; }
        html.light-mode .models-summary-item .ms-label,
        body.light-mode .models-summary-item .ms-label { color: #5a7a9a; }

        @media (max-width: 600px) {
            .group-cards-grid { max-width: 100%; grid-template-columns: 1fr; }
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
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <div class="page-title">
            <i class="fas fa-cube"></i> Product Models
        </div>

        <?php
        // Calculate summary from all groupings
        $summaryByGroup = [];
        foreach ($groupings as $group => $items) {
            $summaryByGroup[$group] = [
                'count' => count($items),
                'qty' => array_sum(array_column($items, 'total_qty')),
                'orders' => array_sum(array_column($items, 'order_count'))
            ];
        }
        ?>

        <!-- Summary Strip -->
        <div class="models-summary">
            <div class="models-summary-item">
                <div class="ms-label">Total Models</div>
                <div class="ms-value accent"><?php echo $totalModels; ?></div>
            </div>
            <div class="models-summary-item">
                <div class="ms-label">Units Delivered</div>
                <div class="ms-value cyan"><?php echo number_format($totalQty); ?></div>
            </div>
            <div class="models-summary-item">
                <div class="ms-label">Total Orders</div>
                <div class="ms-value primary"><?php echo number_format($totalOrders); ?></div>
            </div>
        </div>

        <!-- Group Cards Grid -->
        <div class="group-cards-grid">
            <?php
            // Group A - Single Gas Detectors
            $statsA = [
                'count' => count($groupA),
                'qty' => array_sum(array_column($groupA, 'total_qty')),
                'orders' => array_sum(array_column($groupA, 'order_count'))
            ];
            // Group B - Multi Gas Detectors
            $statsB = [
                'count' => count($groupB),
                'qty' => array_sum(array_column($groupB, 'total_qty')),
                'orders' => array_sum(array_column($groupB, 'order_count'))
            ];
            ?>
            
            <!-- Group A Card -->
            <div class="group-card card-a" onclick="openModal('Group A')">
                <div class="group-card-banner">
                    <div class="group-letter">A</div>
                    <div class="group-name">Group A &mdash; Single Gas</div>
                </div>
                <div class="group-card-body">
                    <div class="group-kpi-row">
                        <div class="group-kpi">
                            <div class="group-kpi-value v1"><?php echo $statsA['count']; ?></div>
                            <div class="group-kpi-label">Models</div>
                        </div>
                        <div class="group-kpi">
                            <div class="group-kpi-value v2"><?php echo number_format($statsA['qty']); ?></div>
                            <div class="group-kpi-label">Units</div>
                        </div>
                        <div class="group-kpi">
                            <div class="group-kpi-value v3"><?php echo number_format($statsA['orders']); ?></div>
                            <div class="group-kpi-label">Orders</div>
                        </div>
                    </div>
                    <div class="group-card-cta">
                        <span>View products</span>
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
            </div>

            <!-- Group B Card -->
            <div class="group-card card-b" onclick="openModal('Group B')">
                <div class="group-card-banner">
                    <div class="group-letter">B</div>
                    <div class="group-name">Group B &mdash; Multi Gas</div>
                </div>
                <div class="group-card-body">
                    <div class="group-kpi-row">
                        <div class="group-kpi">
                            <div class="group-kpi-value v1"><?php echo $statsB['count']; ?></div>
                            <div class="group-kpi-label">Models</div>
                        </div>
                        <div class="group-kpi">
                            <div class="group-kpi-value v2"><?php echo number_format($statsB['qty']); ?></div>
                            <div class="group-kpi-label">Units</div>
                        </div>
                        <div class="group-kpi">
                            <div class="group-kpi-value v3"><?php echo number_format($statsB['orders']); ?></div>
                            <div class="group-kpi-label">Orders</div>
                        </div>
                    </div>
                    <div class="group-card-cta">
                        <span>View products</span>
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Top Models Section -->
        <div style="margin-top: 48px;">
            <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-trending-up" style="color: #00d9ff;"></i> Top 10 Models
            </h2>
            
            <div style="background: var(--color-dark-secondary); border: 1px solid var(--color-border); border-radius: 10px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: rgba(0, 217, 255, 0.08); border-bottom: 1px solid var(--color-border);">
                            <th style="padding: 12px 16px; text-align: left; color: var(--color-text-lighter); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Rank</th>
                            <th style="padding: 12px 16px; text-align: left; color: var(--color-text-lighter); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Item Code</th>
                            <th style="padding: 12px 16px; text-align: left; color: var(--color-text-lighter); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Product Name</th>
                            <th style="padding: 12px 28px; text-align: center; color: var(--color-text-lighter); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; min-width: 100px;">Units</th>
                            <th style="padding: 12px 28px; text-align: center; color: var(--color-text-lighter); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; min-width: 100px;">Orders</th>
                            <th style="padding: 12px 16px; text-align: center; color: var(--color-text-lighter); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Group</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $allItemsSorted = array_merge($groupA, $groupB);
                        usort($allItemsSorted, fn($a, $b) => $b['total_qty'] - $a['total_qty']);
                        $topItems = array_slice($allItemsSorted, 0, 10);
                        
                        foreach ($topItems as $index => $item):
                            $rank = $index + 1;
                            $itemGroup = strpos(strtolower($item['groupings']), 'multi') !== false ? 'Group B' : 'Group A';
                            $medalEmoji = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : ''));
                        ?>
                        <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.2s;">
                            <td style="padding: 12px 16px; color: #00d9ff; font-weight: 700; font-size: 14px;">
                                <?php echo $medalEmoji !== '' ? $medalEmoji . ' #' . $rank : '#' . $rank; ?>
                            </td>
                            <td style="padding: 12px 16px; color: var(--color-accent); font-weight: 600;">
                                <?php echo htmlspecialchars($item['item_code']); ?>
                            </td>
                            <td style="padding: 12px 16px; color: var(--color-text-light);">
                                <?php echo htmlspecialchars(substr($item['item_name'], 0, 50)); ?>
                            </td>
                            <td style="padding: 12px 28px; text-align: center; color: #00d9ff; font-weight: 600;">
                                <?php echo number_format($item['total_qty']); ?>
                            </td>
                            <td style="padding: 12px 28px; text-align: center; color: var(--color-text-light);">
                                <?php echo $item['order_count']; ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: center;">
                                <span style="background: <?php echo $itemGroup === 'Group B' ? 'rgba(8, 145, 178, 0.15)' : 'rgba(30, 79, 160, 0.15)'; ?>; color: <?php echo $itemGroup === 'Group B' ? '#0891b2' : '#1e4fa0'; ?>; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    <?php echo $itemGroup; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Group Products Modal -->
    <div class="gmodal-overlay" id="groupModal" onclick="if(event.target===this)closeModal()">
        <div class="gmodal-box">
            <div class="gmodal-header">
                <div class="gmodal-title" id="modalTitle"></div>
                <button class="gmodal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="gmodal-search">
                <input type="text" id="modalSearch" placeholder="Search item code or name..." oninput="filterModal(this.value)">
            </div>
            <div class="gmodal-table-wrap">
                <table class="gmodal-table">
                    <thead>
                        <tr>
                            <th style="width:28px;">#</th>
                            <th>Item Code</th>
                            <th>Name</th>
                            <th style="text-align:right;">Units</th>
                            <th style="text-align:right;">Orders</th>
                        </tr>
                    </thead>
                    <tbody id="modalBody"></tbody>
                </table>
                <div class="gmodal-empty" id="modalEmpty">
                    <i class="fas fa-search"></i>No items found.
                </div>
            </div>
            <div class="gmodal-footer" id="modalFooter"></div>
        </div>
    </div>

    <script>
        const groupData = {
            'Group A': <?php echo json_encode($groupA); ?>,
            'Group B': <?php echo json_encode($groupB); ?>
        };
        let currentGroup = null;

        function openModal(group) {
            currentGroup = group;
            const data = groupData[group];
            const badge = group === 'Group A'
                ? `<span class="group-badge badge-a">Group A &mdash; Single Gas</span>`
                : `<span class="group-badge badge-b">Group B &mdash; Multi Gas</span>`;
            document.getElementById('modalTitle').innerHTML = badge + ' &nbsp;' + data.length + ' model' + (data.length !== 1 ? 's' : '');
            document.getElementById('modalSearch').value = '';
            renderModalRows(data);
            document.getElementById('groupModal').style.display = 'flex';
        }

        function renderModalRows(data) {
            const tbody = document.getElementById('modalBody');
            const empty = document.getElementById('modalEmpty');
            const footer = document.getElementById('modalFooter');
            empty.style.display = data.length === 0 ? 'block' : 'none';
            if (data.length === 0) { tbody.innerHTML = ''; footer.innerHTML = ''; return; }
            tbody.innerHTML = data.map((m, i) => `
                <tr>
                    <td class="td-num">${i + 1}</td>
                    <td class="td-code">${m.item_code}</td>
                    <td class="td-name">${m.item_name || '&mdash;'}</td>
                    <td class="td-units">${parseInt(m.total_qty).toLocaleString()}</td>
                    <td class="td-orders">${parseInt(m.order_count).toLocaleString()}</td>
                </tr>
            `).join('');
            const total = data.reduce((s, m) => s + parseInt(m.total_qty), 0);
            footer.innerHTML = `Showing <strong>${data.length}</strong> item${data.length !== 1 ? 's' : ''} &nbsp;&middot;&nbsp; Total units: <strong style="color:#00d9ff;">${total.toLocaleString()}</strong>`;
        }

        function filterModal(query) {
            const q = query.toLowerCase();
            const filtered = groupData[currentGroup].filter(m =>
                m.item_code.toLowerCase().includes(q) || (m.item_name || '').toLowerCase().includes(q)
            );
            renderModalRows(filtered);
        }

        function closeModal() {
            document.getElementById('groupModal').style.display = 'none';
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    </script>
    <script src="js/app.js" defer></script>
</body>
</html>


