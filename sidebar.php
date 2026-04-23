<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isStandalone = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__;
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath = rtrim(dirname($scriptName), '/');
$basePath = $basePath === '/' ? '' : $basePath;

$menuItems = [
    ['label' => 'Dashboard', 'href' => 'index.php', 'icon' => 'fas fa-chart-line', 'pages' => ['index.php']],
    ['label' => 'Sales Overview', 'href' => 'sales-overview.php', 'icon' => 'fas fa-chart-pie', 'pages' => ['sales-overview.php']],
    ['label' => 'Sales Records', 'href' => 'sales-records.php', 'icon' => 'fas fa-calendar-alt', 'pages' => ['sales-records.php']],
    ['label' => 'Inquiry', 'href' => 'inquiry.php', 'icon' => 'fas fa-file-invoice', 'pages' => ['inquiry.php', 'orders.php', 'order-details.php']],
    ['label' => 'Delivery Records', 'href' => 'delivery-records.php', 'icon' => 'fas fa-truck', 'pages' => ['delivery-records.php']],
    ['label' => 'Inventory', 'href' => 'inventory.php', 'icon' => 'fas fa-boxes', 'pages' => ['inventory.php']],
    ['label' => 'Andison Manila', 'href' => 'andison-manila.php', 'icon' => 'fas fa-truck-fast', 'pages' => ['andison-manila.php']],
    ['label' => 'Client Companies', 'href' => 'client-companies.php', 'icon' => 'fas fa-building', 'pages' => ['client-companies.php']],
    ['label' => 'Models', 'href' => 'models.php', 'icon' => 'fas fa-cube', 'pages' => ['models.php']],
    ['label' => 'Analytics', 'href' => 'analytics.php', 'icon' => 'fas fa-chart-bar', 'pages' => ['analytics.php']],
    ['label' => 'Reports', 'href' => 'reports.php', 'icon' => 'fas fa-file-alt', 'pages' => ['reports.php']],
    ['label' => 'Upload Data', 'href' => 'upload-data.php', 'icon' => 'fas fa-upload', 'pages' => ['upload-data.php']],
    ['label' => 'Warranty Items', 'href' => 'warranty-replacements.php', 'icon' => 'fas fa-wrench', 'pages' => ['warranty-replacements.php']],
    ['label' => 'Settings', 'href' => 'settings.php', 'icon' => 'fas fa-cog', 'pages' => ['settings.php', 'profile.php', 'help.php']],
];

if ($isStandalone):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Preview</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { margin: 0; }
        .sidebar { top: 0; height: 100vh; }
    </style>
</head>
<body>
<?php
endif;
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <ul class="sidebar-menu">
            <?php foreach ($menuItems as $item): ?>
                <?php $activeClass = in_array($currentPage, $item['pages'], true) ? ' active' : ''; ?>
                <li class="menu-item<?php echo $activeClass; ?>">
                    <a href="<?php echo htmlspecialchars($basePath . '/' . ltrim($item['href'], '/'), ENT_QUOTES); ?>" class="menu-link">
                        <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES); ?>"></i>
                        <span class="menu-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <p class="sidebar-company-info">Andison Industrial</p>
        <p class="sidebar-company-year">© 2025</p>
    </div>
</aside>
<?php if ($isStandalone): ?>
</body>
</html>
<?php endif; ?>
