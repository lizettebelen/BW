<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

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

$allMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];

// Units SOLD to companies (records with company_name)
$unitsSold = 0;
$r = $conn->query("SELECT COALESCE(SUM(quantity),0) as t FROM delivery_records WHERE company_name IS NOT NULL AND company_name != ''$dataset_filter");
if ($r && $row = $r->fetch_assoc()) $unitsSold = intval($row['t']);

// Total deliveries (all records)
$totalDeliveries = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM delivery_records WHERE 1=1$dataset_filter");
if ($r && $row = $r->fetch_assoc()) $totalDeliveries = intval($row['t']);

// Unique products
$uniqueProducts = 0;
$r = $conn->query("SELECT COUNT(DISTINCT item_name) as t FROM delivery_records WHERE item_name IS NOT NULL AND item_name != ''$dataset_filter");
if ($r && $row = $r->fetch_assoc()) $uniqueProducts = intval($row['t']);

// Monthly sales data
$monthly_sales = array_fill_keys($allMonths, 0);
$r = $conn->query("SELECT delivery_month, COALESCE(SUM(quantity),0) AS total FROM delivery_records WHERE delivery_month IS NOT NULL AND delivery_month != ''$dataset_filter GROUP BY delivery_month");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if (array_key_exists($row['delivery_month'], $monthly_sales))
            $monthly_sales[$row['delivery_month']] = intval($row['total']);
    }
}

// Top products
$top_products = [];
$r = $conn->query("SELECT item_name, SUM(quantity) as total_qty FROM delivery_records WHERE item_name IS NOT NULL AND item_name != ''$dataset_filter GROUP BY item_name ORDER BY total_qty DESC LIMIT 5");
if ($r) {
    while ($row = $r->fetch_assoc()) $top_products[] = $row;
}

// Recent deliveries
$recent_sales = [];
$r = $conn->query("SELECT invoice_no, item_name, quantity, company_name, delivery_date, delivery_month, delivery_day FROM delivery_records WHERE 1=1$dataset_filter ORDER BY id DESC LIMIT 10");
if ($r) {
    while ($row = $r->fetch_assoc()) $recent_sales[] = $row;
}

$monthLabels = json_encode(array_map(function($m){ return substr($m,0,3); }, $allMonths));
$monthUnits  = json_encode(array_values(array_map(function($m) use ($monthly_sales){ return $monthly_sales[$m]; }, $allMonths)));
$topLabels   = json_encode(array_column($top_products, 'item_name'));
$topQtys     = json_encode(array_column($top_products, 'total_qty'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Overview - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(20px, 2.5vw, 30px);
            flex-wrap: wrap;
            gap: clamp(12px, 1.5vw, 20px);
        }
        .page-title {
            font-size: clamp(20px, 2.5vw, 28px);
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: clamp(8px, 1vw, 12px);
        }
        .page-title i { color: #f4d03f; }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: clamp(12px, 2vw, 20px);
            margin-bottom: clamp(20px, 3vw, 30px);
        }
        @media (max-width: 1400px) { .summary-cards { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 1100px) { .summary-cards { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .summary-cards { grid-template-columns: 1fr; } }
        
        .summary-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            padding: clamp(15px, 2vw, 25px);
            text-align: center;
            transition: all 0.3s ease;
            min-width: 0;
            display: block;
            overflow: hidden;
        }
        .summary-card:hover {
            transform: translateY(-3px);
            border-color: #f4d03f;
        }
        .summary-card.highlight {
            background: linear-gradient(135deg, #2f5fa7 0%, #00d9ff 100%);
        }
        .summary-card .icon {
            font-size: clamp(24px, 3vw, 36px);
            margin-bottom: clamp(8px, 1vw, 12px);
            color: #f4d03f;
        }
        .summary-card.highlight .icon { color: #fff; }
        .summary-card .value {
            font-size: clamp(20px, 2.5vw, 32px);
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
            white-space: nowrap;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .summary-card .label {
            font-size: clamp(10px, 1.2vw, 13px);
            color: #a0a0a0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card.highlight .label { color: rgba(255,255,255,0.8); }

        .section-title {
            font-size: clamp(16px, 1.8vw, 20px);
            font-weight: 600;
            color: #fff;
            margin: clamp(20px, 2.5vw, 30px) 0 clamp(15px, 1.5vw, 20px);
            padding-bottom: 10px;
            border-bottom: 2px solid #f4d03f;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i { color: #f4d03f; }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: clamp(15px, 2vw, 25px);
            margin-bottom: clamp(20px, 3vw, 30px);
        }
        @media (max-width: 1100px) { .charts-grid { grid-template-columns: 1fr; } }

        .chart-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            padding: clamp(15px, 2vw, 25px);
            min-width: 0;
            overflow: hidden;
        }
        .chart-card h3 {
            font-size: clamp(13px, 1.4vw, 16px);
            font-weight: 600;
            color: #fff;
            margin-bottom: clamp(12px, 1.5vw, 20px);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .chart-card h3 i { color: #f4d03f; }
        .chart-container { 
            position: relative; 
            height: clamp(200px, 30vw, 300px);
            width: 100%;
        }

        .table-container {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            padding: clamp(15px, 2vw, 25px);
            overflow-x: auto;
            margin-bottom: clamp(20px, 3vw, 30px);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(10px, 1.5vw, 15px);
        }
        .table-header h3 {
            font-size: clamp(13px, 1.4vw, 16px);
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table-header h3 i { color: #f4d03f; }

        .sales-table { width: 100%; border-collapse: collapse; margin-top: clamp(10px, 1vw, 15px); }
        .sales-table thead th {
            background: rgba(47,95,167,0.3);
            padding: clamp(10px, 1.2vw, 14px) clamp(12px, 1.5vw, 18px);
            text-align: left;
            font-weight: 600;
            color: #fff;
            font-size: clamp(11px, 1.1vw, 13px);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f4d03f;
            white-space: nowrap;
        }
        .sales-table tbody td {
            padding: clamp(10px, 1.2vw, 14px) clamp(12px, 1.5vw, 18px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: #e0e0e0;
            font-size: clamp(12px, 1.2vw, 14px);
        }
        .sales-table tbody tr:hover { background: rgba(47,95,167,0.15); }

        /* Light mode */
        html.light-mode .page-title,
        body.light-mode .page-title,
        html.light-mode .section-title,
        body.light-mode .section-title,
        html.light-mode .chart-card h3,
        body.light-mode .chart-card h3,
        html.light-mode .table-header h3,
        body.light-mode .table-header h3 { color: #1a3a5c; }
        html.light-mode .summary-card,
        body.light-mode .summary-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid #c5ddf0;
        }
        html.light-mode .summary-card .value,
        body.light-mode .summary-card .value { color: #1a3a5c; }
        html.light-mode .summary-card .label,
        body.light-mode .summary-card .label { color: #5a6a7a; }
        html.light-mode .chart-card,
        body.light-mode .chart-card,
        html.light-mode .table-container,
        body.light-mode .table-container {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid #c5ddf0;
        }
        html.light-mode .sales-table thead th,
        body.light-mode .sales-table thead th {
            background: rgba(30,136,229,0.1);
            color: #1a3a5c;
            border-bottom: 2px solid #1e88e5;
        }
        html.light-mode .sales-table tbody td,
        body.light-mode .sales-table tbody td { color: #333; border-bottom: 1px solid #e0e0e0; }
        html.light-mode .sales-table tbody tr:hover,
        body.light-mode .sales-table tbody tr:hover { background: rgba(30,136,229,0.05); }
        html.light-mode .section-title,
        body.light-mode .section-title { border-bottom: 2px solid #1e88e5; }
        html.light-mode .section-title i,
        body.light-mode .section-title i,
        html.light-mode .chart-card h3 i,
        body.light-mode .chart-card h3 i,
        html.light-mode .table-header h3 i,
        body.light-mode .table-header h3 i { color: #1e88e5; }
        html.light-mode .summary-card .icon,
        body.light-mode .summary-card .icon { color: #1e88e5; }
    </style>
</head>
<body>
    <!-- TOP NAVBAR -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-start">
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle sidebar">
                    <span></span><span></span><span></span>
                </button>
                <div class="logo">
                    <a href="index.php" style="display:flex;align-items:center;">
                        <img src="assets/logo.png" alt="Andison" style="height:48px;width:auto;object-fit:contain;">
                    </a>
                </div>
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
                        <a href="logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                    <a href="index.php" class="menu-link"><i class="fas fa-chart-line"></i><span class="menu-label">Dashboard</span></a>
                </li>
                <li class="menu-item active">
                    <a href="sales-overview.php" class="menu-link"><i class="fas fa-chart-pie"></i><span class="menu-label">Sales Overview</span></a>
                </li>
                <li class="menu-item">
                    <a href="sales-records.php" class="menu-link"><i class="fas fa-calendar-alt"></i><span class="menu-label">Sales Records</span></a>
                </li>
                <li class="menu-item">
                    <a href="inquiry.php" class="menu-link"><i class="fas fa-file-invoice"></i><span class="menu-label">Inquiry</span></a>
                </li>
                <li class="menu-item">
                    <a href="delivery-records.php" class="menu-link"><i class="fas fa-truck"></i><span class="menu-label">Delivery Records</span></a>
                </li>
                <li class="menu-item">
                    <a href="inventory.php" class="menu-link"><i class="fas fa-boxes"></i><span class="menu-label">Inventory</span></a>
                </li>
                <li class="menu-item">
                    <a href="andison-manila.php" class="menu-link"><i class="fas fa-truck-fast"></i><span class="menu-label">Andison Manila</span></a>
                </li>
                <li class="menu-item">
                    <a href="client-companies.php" class="menu-link"><i class="fas fa-building"></i><span class="menu-label">Client Companies</span></a>
                </li>
                <li class="menu-item">
                    <a href="models.php" class="menu-link">
                        <i class="fas fa-cube"></i><span class="menu-label">Models</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reports.php" class="menu-link"><i class="fas fa-file-alt"></i><span class="menu-label">Reports</span></a>
                </li>
                <li class="menu-item">
                    <a href="upload-data.php" class="menu-link"><i class="fas fa-upload"></i><span class="menu-label">Upload Data</span></a>
                </li>
                <li class="menu-item">
                    <a href="warranty-replacements.php" class="menu-link"><i class="fas fa-wrench"></i><span class="menu-label">Warranty Items</span></a>
                </li>
                <li class="menu-item">
                    <a href="settings.php" class="menu-link"><i class="fas fa-cog"></i><span class="menu-label">Settings</span></a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <p class="company-info">Addison Industrial</p>
            <p class="company-year">© 2025</p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">

        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-pie"></i>
                Sales Overview<?php echo renderDatasetIndicator($active_dataset); ?>
            </h1>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="icon"><i class="fas fa-boxes"></i></div>
                <div class="value"><?php echo number_format($unitsSold); ?></div>
                <div class="label">Units Sold</div>
            </div>
            <div class="summary-card">
                <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="value"><?php echo number_format($totalDeliveries); ?></div>
                <div class="label">Total Deliveries</div>
            </div>
            <div class="summary-card highlight">
                <div class="icon"><i class="fas fa-cube"></i></div>
                <div class="value"><?php echo number_format($uniqueProducts); ?></div>
                <div class="label">Unique Products</div>
            </div>
            <div class="summary-card highlight">
                <div class="icon"><i class="fas fa-percentage"></i></div>
                <div class="value"><?php echo $totalDeliveries > 0 ? number_format(round($unitsSold / $totalDeliveries * 100, 1), 1) : 0; ?>%</div>
                <div class="label">Sold Rate</div>
            </div>
        </div>

        <!-- Monthly Overview -->
        <h2 class="section-title">
            <i class="fas fa-calendar"></i>
            Monthly Overview
        </h2>

        <div class="charts-grid">
            <div class="chart-card">
                <h3><i class="fas fa-chart-bar"></i> Units Delivered per Month</h3>
                <div class="chart-container chart-expandable" onclick="openChartPreview('monthlyUnitsChart','Units Delivered per Month')" style="position:relative;">
                    <canvas id="monthlyUnitsChart"></canvas>
                    <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Top Products</h3>
                <div class="chart-container chart-expandable" onclick="openChartPreview('topProductsChart','Top Products')" style="position:relative;">
                    <canvas id="topProductsChart"></canvas>
                    <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                </div>
            </div>
        </div>

        <!-- Recent Deliveries -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-truck"></i> Recent Deliveries</h3>
            </div>
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Company</th>
                        <th>Delivery Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_sales)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:30px;color:#a0a0a0;">
                            No data yet. <a href="upload-data.php" style="color:#f4d03f;">Upload data</a> to get started.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recent_sales as $sale):
                        $ddate = '';
                        if (!empty($sale['delivery_date'])) {
                            $ddate = date('M j, Y', strtotime($sale['delivery_date']));
                        } elseif (!empty($sale['delivery_month'])) {
                            $ddate = $sale['delivery_month'] . ' ' . ($sale['delivery_day'] ?? '');
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sale['invoice_no'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(substr($sale['item_name'] ?? '-', 0, 40)); ?></td>
                        <td><?php echo intval($sale['quantity'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars(substr($sale['company_name'] ?? '-', 0, 30)); ?></td>
                        <td><?php echo htmlspecialchars($ddate); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script src="js/app.js" defer></script>
    <script>
        const monthLabels = <?php echo $monthLabels; ?>;
        const monthUnits  = <?php echo $monthUnits; ?>;
        const topLabels   = <?php echo $topLabels; ?>;
        const topQtys     = <?php echo $topQtys; ?>;

        const isLight = document.body.classList.contains('light-mode') || document.documentElement.classList.contains('light-mode');
        const tcol = isLight ? '#333' : '#c8d6e8';
        const gcol = isLight ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.06)';
        const vibrantPalette = ['#ffb703', '#fb8500', '#00c2ff', '#3a86ff', '#06d6a0', '#ff4d9d', '#8338ec', '#4cc9f0', '#ff6d00', '#2ec4b6'];

        // Monthly bar chart
        (function() {
            const ctx = document.getElementById('monthlyUnitsChart');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Units',
                        data: monthUnits,
                        backgroundColor: monthLabels.map(function(_, i) { return vibrantPalette[i % vibrantPalette.length]; }),
                        borderColor: '#ffffff',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: tcol }, grid: { color: gcol } },
                        x: { ticks: { color: tcol }, grid: { color: gcol } }
                    }
                }
            });
        })();

        // Top products doughnut
        (function() {
            const ctx = document.getElementById('topProductsChart');
            if (!ctx) return;
            if (!topLabels || !topLabels.length) {
                ctx.parentElement.insertAdjacentHTML('afterbegin', '<p style="color:#a0a0a0;text-align:center;padding:30px 0;font-size:13px;">No product data yet.</p>');
                ctx.style.display = 'none';
                return;
            }
            const shortLabels = topLabels.map(function(l){ return l.length > 22 ? l.slice(0,22)+'…' : l; });
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: shortLabels,
                    datasets: [{
                        data: topQtys,
                        backgroundColor: topQtys.map(function(_, i) { return vibrantPalette[i % vibrantPalette.length]; }),
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: tcol, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                title: function(items) { return topLabels[items[0].dataIndex] || items[0].label; }
                            }
                        }
                    }
                }
            });
        })();
    </script>

    <!-- ===== CHART PREVIEW MODAL ===== -->
    <div id="chartPreviewOverlay" onclick="closeChartPreview(event)" style="display:none;position:fixed;inset:0;z-index:9999;backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:16px;box-sizing:border-box;">
        <div id="chartPreviewBox" style="border-radius:16px;width:min(1200px,97vw);height:90vh;display:flex;flex-direction:column;box-shadow:0 32px 80px rgba(0,0,0,0.6);overflow:hidden;">
            <div id="chartPreviewHeader" style="display:flex;align-items:center;justify-content:space-between;padding:18px 26px;flex-shrink:0;">
                <h3 id="chartPreviewTitle" style="margin:0;font-size:18px;font-weight:700;"></h3>
                <button id="chartPreviewCloseBtn" onclick="closeChartPreviewBtn()" style="width:34px;height:34px;border-radius:9px;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;border:none;transition:background 0.2s;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding:10px 26px 26px;flex:1;min-height:0;position:relative;">
                <canvas id="chartPreviewCanvas" style="width:100% !important;height:100% !important;"></canvas>
            </div>
        </div>
    </div>
    <style>
    .chart-expandable{cursor:pointer;}
    .chart-expand-hint{position:absolute;top:8px;right:8px;background:rgba(244,208,63,0.15);border:1px solid rgba(244,208,63,0.3);color:#f4d03f;width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;opacity:0;transition:opacity 0.2s;pointer-events:none;}
    .chart-expandable:hover .chart-expand-hint{opacity:1;}
    .chart-expandable:hover canvas{opacity:0.88;}
    #chartPreviewOverlay.cp-dark{background:rgba(4,8,18,0.92);}
    #chartPreviewBox.cp-dark{background:#131c2b;border:1px solid #2a3c55;}
    #chartPreviewHeader.cp-dark{border-bottom:1px solid #2a3c55;}
    #chartPreviewTitle.cp-dark{color:#e2ecf8;}
    #chartPreviewCloseBtn.cp-dark{background:rgba(255,255,255,0.07);color:#a0b4c8;}
    #chartPreviewOverlay.cp-light{background:rgba(180,195,215,0.72);}
    #chartPreviewBox.cp-light{background:#ffffff;border:1px solid #d0daea;}
    #chartPreviewHeader.cp-light{border-bottom:1px solid #e0eaf4;}
    #chartPreviewTitle.cp-light{color:#1a2a3a;}
    #chartPreviewCloseBtn.cp-light{background:#f0f4fa;color:#3a4a5a;}
    </style>
    <script>
    function openChartPreview(canvasId,title){
        const src=document.getElementById(canvasId);if(!src)return;
        const sc=(typeof Chart!=='undefined')&&Chart.getChart?Chart.getChart(src):null;if(!sc)return;
        const isLight=document.body.classList.contains('light-mode');
        const tc=isLight?'cp-light':'cp-dark';
        const tickC=isLight?'#4a5a6a':'#8a9ab5',gridC=isLight?'rgba(0,0,0,0.07)':'rgba(255,255,255,0.06)',legC=isLight?'#2a3a4a':'#c0d0e0';
        ['chartPreviewOverlay','chartPreviewBox','chartPreviewHeader','chartPreviewTitle','chartPreviewCloseBtn'].forEach(function(id){const el=document.getElementById(id);el.classList.remove('cp-dark','cp-light');el.classList.add(tc);});
        document.getElementById('chartPreviewTitle').textContent=title;
        const ov=document.getElementById('chartPreviewOverlay');ov.style.display='flex';document.body.style.overflow='hidden';
        const pc=document.getElementById('chartPreviewCanvas');const ex=Chart.getChart(pc);if(ex)ex.destroy();
        try{
            const cfg={type:sc.config.type,data:JSON.parse(JSON.stringify(sc.config.data)),options:JSON.parse(JSON.stringify(sc.config.options||{}))};
            cfg.options.responsive=true;cfg.options.maintainAspectRatio=false;cfg.options.animation={duration:400};
            cfg.options.plugins=cfg.options.plugins||{};
            cfg.options.plugins.legend=cfg.options.plugins.legend||{};
            cfg.options.plugins.legend.labels=cfg.options.plugins.legend.labels||{};
            cfg.options.plugins.legend.labels.color=legC;cfg.options.plugins.legend.labels.font={size:14};
            if(cfg.options.plugins.title)cfg.options.plugins.title.color=legC;
            if(cfg.options.scales){Object.values(cfg.options.scales).forEach(function(s){s.ticks=s.ticks||{};s.ticks.color=tickC;s.ticks.font={size:13};s.grid=s.grid||{};s.grid.color=gridC;});}
            new Chart(pc,cfg);
        }catch(e){console.error('Chart preview error:',e);}
    }
    function closeChartPreviewBtn(){document.getElementById('chartPreviewOverlay').style.display='none';document.body.style.overflow='';const c=Chart.getChart(document.getElementById('chartPreviewCanvas'));if(c)c.destroy();}
    function closeChartPreview(e){if(e&&e.target!==document.getElementById('chartPreviewOverlay'))return;closeChartPreviewBtn();}
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeChartPreviewBtn();});
    </script>
</body>
</html>