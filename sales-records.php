<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

// Include database configuration
require_once 'db_config.php';

// Get selected dataset from URL parameter
$selected_dataset = isset($_GET['dataset']) ? trim($_GET['dataset']) : 'all';

// Build dataset filter clause for queries
$dataset_filter = "";
if ($selected_dataset !== 'all' && $selected_dataset !== '') {
    $safe_dataset = $conn->real_escape_string($selected_dataset);
    $dataset_filter = " AND dataset_name = '$safe_dataset'";
}

// Get current year
$currentYear = date('Y');

// All months
$allMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Detect DB type for compatible year expressions
$isMysql = ($conn instanceof mysqli);

// Year expression: prefer stored delivery_year; fall back to extracting from delivery_date/created_at
$yearExpr = $isMysql
    ? "CASE WHEN delivery_year > 0 THEN delivery_year WHEN delivery_date IS NOT NULL THEN YEAR(delivery_date) ELSE YEAR(created_at) END"
    : "CASE WHEN delivery_year > 0 THEN delivery_year WHEN delivery_date IS NOT NULL THEN CAST(strftime('%Y', delivery_date) AS INTEGER) ELSE CAST(strftime('%Y', created_at) AS INTEGER) END";

// Sales value expression: use total_amount if available, otherwise quantity * unit_price
$salesExpr = "CASE
    WHEN total_amount IS NOT NULL AND total_amount > 0 THEN total_amount
    WHEN unit_price IS NOT NULL AND unit_price > 0 THEN (quantity * unit_price)
    ELSE 0
END";

// Get available years from data (ordered by record count DESC, then year DESC)
$availableYears = [];
$yearCountResult = $conn->query("SELECT ({$yearExpr}) as year, COUNT(*) as cnt FROM delivery_records WHERE ({$yearExpr}) > 0$dataset_filter GROUP BY ({$yearExpr}) ORDER BY cnt DESC, year DESC");
if ($yearCountResult) {
    $firstYear = null;
    while ($row = $yearCountResult->fetch_assoc()) {
        if (intval($row['year']) > 0) {
            if ($firstYear === null) $firstYear = intval($row['year']); // Year with most records
            $availableYears[] = intval($row['year']);
        }
    }
    // Sort years DESC for dropdown display
    rsort($availableYears);
}

// Default to the year with most data; fall back to current year if no data
$defaultYear = isset($firstYear) ? $firstYear : $currentYear;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $defaultYear;
$selectedMonth = isset($_GET['month']) ? sanitize_input($_GET['month']) : '';
$selectedDay = isset($_GET['day']) ? intval($_GET['day']) : 0;

// Always include current year in the dropdown (even if no data yet)
if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    sort($availableYears);
    $availableYears = array_reverse($availableYears);
}

// Function to sanitize input
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Get available months for selected year
$availableMonths = [];
$monthResult = $conn->query("
    SELECT DISTINCT delivery_month 
    FROM delivery_records 
    WHERE ({$yearExpr}) = {$selectedYear} 
    AND delivery_month IS NOT NULL 
    AND delivery_month != ''
    ORDER BY CASE delivery_month
        WHEN 'January' THEN 1 WHEN 'February' THEN 2 WHEN 'March' THEN 3
        WHEN 'April' THEN 4 WHEN 'May' THEN 5 WHEN 'June' THEN 6
        WHEN 'July' THEN 7 WHEN 'August' THEN 8 WHEN 'September' THEN 9
        WHEN 'October' THEN 10 WHEN 'November' THEN 11 WHEN 'December' THEN 12
    END
");
if ($monthResult) {
    while ($row = $monthResult->fetch_assoc()) {
        $availableMonths[] = $row['delivery_month'];
    }
}

// Get available days for selected year and month
$availableDays = [];
if ($selectedMonth) {
    $monthFilter = "AND delivery_month = '" . $conn->real_escape_string($selectedMonth) . "'";
} else {
    $monthFilter = '';
}

$dayExpr = $isMysql
    ? "CAST(DAY(delivery_date) AS UNSIGNED)"
    : "CAST(strftime('%d', delivery_date) AS INTEGER)";

$dayResult = $conn->query("
    SELECT DISTINCT {$dayExpr} as day 
    FROM delivery_records 
    WHERE ({$yearExpr}) = {$selectedYear} 
    {$monthFilter}
    AND delivery_date IS NOT NULL
    ORDER BY {$dayExpr}
");
if ($dayResult) {
    while ($row = $dayResult->fetch_assoc()) {
        $day = intval($row['day']);
        if ($day > 0) {
            $availableDays[] = $day;
        }
    }
}

// Monthly Sales Data for Selected Year
$monthlySales = array_fill_keys($allMonths, ['units' => 0, 'orders' => 0, 'sales' => 0.0]);
$result = $conn->query("
    SELECT delivery_month,
           COUNT(*) as order_count,
           COALESCE(SUM(CASE WHEN company_name IS NOT NULL AND company_name != '' THEN quantity ELSE 0 END), 0) as total_units,
           COALESCE(SUM({$salesExpr}), 0) as total_sales
    FROM delivery_records
    WHERE ({$yearExpr}) = {$selectedYear}{$dataset_filter}
    GROUP BY delivery_month
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $month = $row['delivery_month'];
        if (array_key_exists($month, $monthlySales)) {
            $monthlySales[$month] = [
                'units'  => intval($row['total_units']),
                'orders' => intval($row['order_count']),
                'sales'  => floatval($row['total_sales'])
            ];
        }
    }
}

// Yearly Sales Data (All Years)
$yearlySales = [];
$result = $conn->query("
    SELECT ({$yearExpr}) as year,
           COUNT(*) as order_count,
           COALESCE(SUM(CASE WHEN company_name IS NOT NULL AND company_name != '' THEN quantity ELSE 0 END), 0) as total_units,
           COALESCE(SUM({$salesExpr}), 0) as total_sales
    FROM delivery_records
    WHERE ({$yearExpr}) > 0{$dataset_filter}
    GROUP BY ({$yearExpr})
    ORDER BY year DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yearlySales[] = [
            'year'   => intval($row['year']),
            'units'  => intval($row['total_units']),
            'orders' => intval($row['order_count']),
            'sales'  => floatval($row['total_sales'])
        ];
    }
}

// Calculate totals for selected year
$yearlyTotal = ['units' => 0, 'orders' => 0, 'sales' => 0.0];
foreach ($monthlySales as $data) {
    $yearlyTotal['units'] += $data['units'];
    $yearlyTotal['orders'] += $data['orders'];
    $yearlyTotal['sales'] += $data['sales'];
}

// Overall totals (all time)
$allTimeTotal = ['units' => 0, 'orders' => 0, 'sales' => 0.0];
foreach ($yearlySales as $data) {
    $allTimeTotal['units'] += $data['units'];
    $allTimeTotal['orders'] += $data['orders'];
    $allTimeTotal['sales'] += $data['sales'];
}

// Prepare data for JavaScript
$monthLabels = json_encode($allMonths);
$monthUnits = json_encode(array_values(array_map(function($m) use ($monthlySales) { return $monthlySales[$m]['units']; }, $allMonths)));
$monthOrders = json_encode(array_values(array_map(function($m) use ($monthlySales) { return $monthlySales[$m]['orders']; }, $allMonths)));

$yearLabels = json_encode(array_column($yearlySales, 'year'));
$yearUnits = json_encode(array_column($yearlySales, 'units'));
$yearOrders = json_encode(array_column($yearlySales, 'orders'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){if(localStorage.getItem('theme')!=='dark'){document.documentElement.classList.add('light-mode');document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('light-mode')})}})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - BW Gas Detector</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        /* Prevent horizontal overflow */
        html, body {
            overflow-x: hidden;
        }

        /* Ensure content fills available space evenly */
        .summary-cards,
        .charts-grid,
        .table-container,
        .section-title,
        .page-header {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: #f4d03f;
        }

        /* Filters Container */
        .filters-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .year-selector {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .month-selector {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .day-selector {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .year-selector label {
            color: #a0a0a0;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .year-selector .select-wrapper {
            position: relative;
            display: inline-block;
        }

        .year-selector .select-wrapper::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #f4d03f;
            font-size: 11px;
            transition: transform 0.3s ease, color 0.3s ease;
        }

        .year-selector .select-wrapper:hover::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .year-selector select {
            padding: 12px 45px 12px 18px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            border: 2px solid rgba(244, 208, 63, 0.3);
            background: linear-gradient(145deg, rgba(30, 42, 56, 0.95), rgba(20, 30, 45, 0.98));
            color: #fff;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            min-width: 130px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            box-shadow: 
                0 4px 15px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 0 0 rgba(244, 208, 63, 0);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }

        .year-selector select:hover {
            border-color: rgba(244, 208, 63, 0.6);
            box-shadow: 
                0 6px 20px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 20px rgba(244, 208, 63, 0.15);
            transform: translateY(-2px);
        }

        .year-selector select:focus {
            outline: none;
            border-color: #f4d03f;
            box-shadow: 
                0 6px 25px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 30px rgba(244, 208, 63, 0.25),
                0 0 0 3px rgba(244, 208, 63, 0.1);
        }

        .year-selector select option {
            background: #1e2a38;
            color: #fff;
            padding: 12px;
            font-weight: 500;
        }

        .year-selector select option:hover,
        .year-selector select option:checked {
            background: linear-gradient(135deg, #2a3f5f, #1e2a38);
        }

        .year-selector label i {
            color: #f4d03f;
            margin-right: 6px;
            font-size: 13px;
        }

        /* Month Selector Styles */
        .month-selector label {
            color: #a0a0a0;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .month-selector .select-wrapper {
            position: relative;
            display: inline-block;
        }

        .month-selector .select-wrapper::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #f4d03f;
            font-size: 11px;
            transition: transform 0.3s ease, color 0.3s ease;
        }

        .month-selector .select-wrapper:hover::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .month-selector select {
            padding: 12px 45px 12px 18px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            border: 2px solid rgba(244, 208, 63, 0.3);
            background: linear-gradient(145deg, rgba(30, 42, 56, 0.95), rgba(20, 30, 45, 0.98));
            color: #fff;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            min-width: 130px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            box-shadow: 
                0 4px 15px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 0 0 rgba(244, 208, 63, 0);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }

        .month-selector select:hover {
            border-color: rgba(244, 208, 63, 0.6);
            box-shadow: 
                0 6px 20px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 20px rgba(244, 208, 63, 0.15);
            transform: translateY(-2px);
        }

        .month-selector select:focus {
            outline: none;
            border-color: #f4d03f;
            box-shadow: 
                0 6px 25px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 30px rgba(244, 208, 63, 0.25),
                0 0 0 3px rgba(244, 208, 63, 0.1);
        }

        .month-selector select option {
            background: #1e2a38;
            color: #fff;
            padding: 12px;
            font-weight: 500;
        }

        .month-selector select option:hover,
        .month-selector select option:checked {
            background: linear-gradient(135deg, #2a3f5f, #1e2a38);
        }

        .month-selector label i {
            color: #f4d03f;
            margin-right: 6px;
            font-size: 13px;
        }

        /* Day Selector Styles */
        .day-selector label {
            color: #a0a0a0;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .day-selector .select-wrapper {
            position: relative;
            display: inline-block;
        }

        .day-selector .select-wrapper::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #f4d03f;
            font-size: 11px;
            transition: transform 0.3s ease, color 0.3s ease;
        }

        .day-selector .select-wrapper:hover::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .day-selector select {
            padding: 12px 45px 12px 18px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            border: 2px solid rgba(244, 208, 63, 0.3);
            background: linear-gradient(145deg, rgba(30, 42, 56, 0.95), rgba(20, 30, 45, 0.98));
            color: #fff;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            min-width: 100px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            box-shadow: 
                0 4px 15px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 0 0 rgba(244, 208, 63, 0);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }

        .day-selector select:hover {
            border-color: rgba(244, 208, 63, 0.6);
            box-shadow: 
                0 6px 20px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 20px rgba(244, 208, 63, 0.15);
            transform: translateY(-2px);
        }

        .day-selector select:focus {
            outline: none;
            border-color: #f4d03f;
            box-shadow: 
                0 6px 25px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 30px rgba(244, 208, 63, 0.25),
                0 0 0 3px rgba(244, 208, 63, 0.1);
        }

        .day-selector select option {
            background: #1e2a38;
            color: #fff;
            padding: 12px;
            font-weight: 500;
        }

        .day-selector select option:hover,
        .day-selector select option:checked {
            background: linear-gradient(135deg, #2a3f5f, #1e2a38);
        }

        .day-selector label i {
            color: #f4d03f;
            margin-right: 6px;
            font-size: 13px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
            align-items: stretch;
            grid-auto-rows: 1fr;
        }

        .summary-cards .summary-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            min-width: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 210px;
            height: 100%;
        }

        .summary-cards .summary-card:hover {
            transform: translateY(-3px);
            border-color: #f4d03f;
        }

        .summary-cards .summary-card.highlight {
            background: linear-gradient(135deg, #2f5fa7 0%, #00d9ff 100%);
        }

        .summary-cards .summary-card .icon {
            font-size: 36px;
            margin-bottom: 12px;
            color: #f4d03f;
        }

        .summary-cards .summary-card.highlight .icon {
            color: #fff;
        }

        .summary-cards .summary-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
            max-width: 100%;
        }

        .summary-cards .summary-card .value.money-value {
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: normal;
            line-height: 1.15;
            font-size: clamp(18px, 1.2vw, 26px);
            letter-spacing: 0.2px;
        }

        .summary-cards .summary-card .label {
            font-size: 13px;
            color: #a0a0a0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            max-width: 100%;
            overflow-wrap: anywhere;
        }

        .summary-cards .summary-card.highlight .label {
            color: rgba(255, 255, 255, 0.8);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #fff;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f4d03f;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #f4d03f;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 25px;
            margin-bottom: 30px;
            width: 100%;
        }

        .chart-card {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            min-width: 0; /* Prevent overflow */
            overflow: hidden;
            flex: 1;
        }

        .chart-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-card h3 i {
            color: #f4d03f;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .chart-container canvas {
            max-width: 100%;
        }

        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .sales-table thead th {
            background: rgba(47, 95, 167, 0.3);
            padding: 14px 18px;
            text-align: left;
            font-weight: 600;
            color: #fff;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f4d03f;
        }

        .sales-table tbody td {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
            font-size: 14px;
        }

        .sales-table tbody tr:hover {
            background: rgba(47, 95, 167, 0.15);
        }

        .sales-table tbody tr.total-row {
            background: rgba(244, 208, 63, 0.1);
            font-weight: 700;
        }

        .sales-table tbody tr.total-row td {
            color: #f4d03f;
            border-top: 2px solid #f4d03f;
        }

        .table-container {
            background: linear-gradient(135deg, #1e2a38 0%, #2a3f5f 100%);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            overflow-x: auto;
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .table-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h3 i {
            color: #f4d03f;
        }

        .badge-month {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-current {
            background: rgba(244, 208, 63, 0.2);
            color: #f4d03f;
        }

        .badge-high {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        /* Light Mode Styles */
        html.light-mode .page-title,
        body.light-mode .page-title,
        html.light-mode .section-title,
        body.light-mode .section-title,
        html.light-mode .chart-card h3,
        body.light-mode .chart-card h3,
        html.light-mode .table-header h3,
        body.light-mode .table-header h3 {
            color: #1a3a5c;
        }

        html.light-mode .summary-card,
        body.light-mode .summary-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid #c5ddf0;
        }

        html.light-mode .summary-card .value,
        body.light-mode .summary-card .value {
            color: #1a3a5c;
        }

        html.light-mode .summary-card .label,
        body.light-mode .summary-card .label {
            color: #5a6a7a;
        }

        html.light-mode .chart-card,
        body.light-mode .chart-card,
        html.light-mode .table-container,
        body.light-mode .table-container {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid #c5ddf0;
        }

        html.light-mode .sales-table thead th,
        body.light-mode .sales-table thead th {
            background: rgba(30, 136, 229, 0.1);
            color: #1a3a5c;
            border-bottom: 2px solid #1e88e5;
        }

        html.light-mode .sales-table tbody td,
        body.light-mode .sales-table tbody td {
            color: #333;
            border-bottom: 1px solid #e0e0e0;
        }

        html.light-mode .sales-table tbody tr:hover,
        body.light-mode .sales-table tbody tr:hover {
            background: rgba(30, 136, 229, 0.05);
        }

        html.light-mode .sales-table tbody tr.total-row,
        body.light-mode .sales-table tbody tr.total-row {
            background: rgba(30, 136, 229, 0.1);
        }

        html.light-mode .sales-table tbody tr.total-row td,
        body.light-mode .sales-table tbody tr.total-row td {
            color: #1e88e5;
            border-top: 2px solid #1e88e5;
        }

        html.light-mode .year-selector select,
        body.light-mode .year-selector select {
            background: linear-gradient(145deg, #ffffff, #f0f7ff);
            border: 2px solid rgba(30, 136, 229, 0.3);
            color: #1a3a5c;
            box-shadow: 
                0 4px 15px rgba(30, 136, 229, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                0 0 0 0 rgba(30, 136, 229, 0);
        }

        html.light-mode .year-selector select:hover,
        body.light-mode .year-selector select:hover {
            border-color: rgba(30, 136, 229, 0.5);
            box-shadow: 
                0 6px 20px rgba(30, 136, 229, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                0 0 20px rgba(30, 136, 229, 0.1);
        }

        html.light-mode .year-selector select:focus,
        body.light-mode .year-selector select:focus {
            border-color: #1e88e5;
            box-shadow: 
                0 6px 25px rgba(30, 136, 229, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                0 0 30px rgba(30, 136, 229, 0.15),
                0 0 0 3px rgba(30, 136, 229, 0.1);
        }

        html.light-mode .year-selector .select-wrapper::after,
        body.light-mode .year-selector .select-wrapper::after {
            color: #1e88e5;
        }

        html.light-mode .year-selector select option,
        body.light-mode .year-selector select option {
            background: #ffffff;
            color: #1a3a5c;
        }

        html.light-mode .year-selector label,
        body.light-mode .year-selector label {
            color: #3a6a8a;
        }

        html.light-mode .year-selector label i,
        body.light-mode .year-selector label i {
            color: #1e88e5;
        }

        html.light-mode .section-title,
        body.light-mode .section-title {
            border-bottom: 2px solid #1e88e5;
        }

        html.light-mode .section-title i,
        body.light-mode .section-title i,
        html.light-mode .chart-card h3 i,
        body.light-mode .chart-card h3 i,
        html.light-mode .table-header h3 i,
        body.light-mode .table-header h3 i {
            color: #1e88e5;
        }

        html.light-mode .summary-card .icon,
        body.light-mode .summary-card .icon {
            color: #1e88e5;
        }

        html.light-mode .summary-cards .summary-card.highlight,
        body.light-mode .summary-cards .summary-card.highlight {
            background: linear-gradient(135deg, #2f5fa7 0%, #00b8df 100%) !important;
            border-color: rgba(0, 88, 128, 0.28) !important;
            box-shadow: 0 8px 18px rgba(47, 95, 167, 0.18);
        }

        html.light-mode .summary-cards .summary-card.highlight .icon,
        body.light-mode .summary-cards .summary-card.highlight .icon {
            color: #ffffff !important;
        }

        html.light-mode .summary-cards .summary-card.highlight .value,
        body.light-mode .summary-cards .summary-card.highlight .value {
            color: #ffffff !important;
        }

        html.light-mode .summary-cards .summary-card.highlight .label,
        body.light-mode .summary-cards .summary-card.highlight .label {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        html.light-mode .badge-current,
        body.light-mode .badge-current {
            background: rgba(30, 136, 229, 0.15);
            color: #1e88e5;
        }

        /* ============================================
           RESPONSIVE STYLES
           ============================================ */

        /* Large desktops and smaller */
        @media (max-width: 1200px) {
            .summary-cards {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        /* Tablets and smaller */
        @media (max-width: 992px) {
            .charts-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .filters-container {
                width: 100%;
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }

            .filters-container > div {
                width: 100%;
            }

            .filters-container > div .select-wrapper {
                display: block;
            }

            .filters-container > div select {
                width: 100%;
                box-sizing: border-box;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .summary-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 15px;
            }
            
            .summary-card {
                padding: 20px;
            }
            
            .summary-card .value {
                font-size: 26px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .sales-table thead th,
            .sales-table tbody td {
                padding: 12px 14px;
                font-size: 13px;
            }
        }

        /* Small tablets and large phones */
        @media (max-width: 768px) {
            .page-title {
                font-size: 20px;
            }
            
            .summary-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
            
            .summary-card {
                padding: 15px;
            }
            
            .summary-card .icon {
                font-size: 28px;
                margin-bottom: 8px;
            }
            
            .summary-card .value {
                font-size: 22px;
            }
            
            .summary-card .label {
                font-size: 11px;
            }
            
            .chart-card {
                padding: 15px;
            }
            
            .chart-container {
                height: 220px;
            }
            
            .table-container {
                padding: 15px;
            }
            
            .section-title {
                font-size: 18px;
            }
            
            .year-selector select {
                padding: 10px 35px 10px 14px;
                font-size: 14px;
                min-width: 110px;
            }
        }

        /* Mobile phones */
        @media (max-width: 576px) {
            .page-header {
                margin-bottom: 20px;
            }
            
            .page-title {
                font-size: 18px;
                gap: 8px;
            }
            
            .summary-cards {
                grid-template-columns: minmax(0, 1fr);
                gap: 10px;
            }
            
            .summary-card {
                padding: 15px;
                display: block;
                text-align: center;
            }
            
            .summary-card .icon {
                font-size: 24px;
                margin-bottom: 8px;
            }
            
            .summary-card .value {
                font-size: 20px;
                margin-bottom: 2px;
            }

            .summary-cards .summary-card .value.money-value {
                font-size: 18px;
                white-space: nowrap;
                overflow-wrap: normal;
            }
            
            .summary-card .label {
                font-size: 10px;
            }
            
            .chart-card h3 {
                font-size: 14px;
            }
            
            .chart-container {
                height: 200px;
            }
            
            .section-title {
                font-size: 16px;
                margin: 20px 0 15px;
            }
            
            .sales-table thead th,
            .sales-table tbody td {
                padding: 10px 8px;
                font-size: 12px;
            }
            
            .table-header h3 {
                font-size: 14px;
            }
            
            .badge-month {
                padding: 4px 8px;
                font-size: 10px;
            }
        }

        /* Extra small phones */
        @media (max-width: 400px) {
            .summary-card {
                padding: 12px;
            }
            
            .summary-card .value {
                font-size: 18px;
            }
            
            .year-selector {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .year-selector select {
                width: 100%;
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
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <!-- SIDEBAR -->
        <?php require __DIR__ . '/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content" id="mainContent">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-calendar-alt"></i>
                    Sales Records
                </h1>
                <div class="filters-container">
                    <div class="year-selector">
                        <label for="yearSelect"><i class="fas fa-calendar-alt"></i> Year:</label>
                        <div class="select-wrapper">
                            <select id="yearSelect" onchange="updateFilters()">
                                <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="month-selector">
                        <label for="monthSelect"><i class="fas fa-calendar"></i> Month:</label>
                        <div class="select-wrapper">
                            <select id="monthSelect" onchange="updateFilters()">
                                <option value="">All Months</option>
                                <?php foreach ($availableMonths as $month): ?>
                                <option value="<?php echo $month; ?>" <?php echo $month == $selectedMonth ? 'selected' : ''; ?>>
                                    <?php echo $month; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="day-selector">
                        <label for="daySelect"><i class="fas fa-calendar"></i> Day:</label>
                        <div class="select-wrapper">
                            <select id="daySelect" onchange="updateFilters()">
                                <option value="">All Days</option>
                                <?php foreach ($availableDays as $day): ?>
                                <option value="<?php echo $day; ?>" <?php echo $day == $selectedDay ? 'selected' : ''; ?>>
                                    Day <?php echo $day; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dataset Indicator Banner -->
            <div style="background: linear-gradient(90deg, #2a3f5f 0%, #1e2a38 100%); border-left: 4px solid #f4d03f; padding: 12px 16px; margin-bottom: 20px; border-radius: 6px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-database" style="color: #f4d03f; font-size: 14px;"></i>
                <span style="color: #8a9ab5; font-size: 12px;">Current Dataset:</span>
                <strong style="color: #fff; font-size: 13px;"><?php echo $selected_dataset === 'all' ? 'ALL DATA' : htmlspecialchars(strtoupper($selected_dataset)); ?></strong>
                <?php if ($selected_dataset !== 'all'): ?>
                <a href="sales-records.php" style="margin-left: auto; color: #f4d03f; font-size: 12px; text-decoration: none; opacity: 0.8; transition: opacity .2s;" title="View all datasets">
                    <i class="fas fa-times-circle"></i> Clear
                </a>
                <?php endif; ?>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card highlight">
                    <div class="icon"><i class="fas fa-boxes"></i></div>
                    <div class="value"><?php echo number_format($allTimeTotal['units']); ?></div>
                    <div class="label">All-Time Units</div>
                </div>
                <div class="summary-card highlight">
                    <div class="icon"><i class="fas fa-file-invoice"></i></div>
                    <div class="value"><?php echo number_format($allTimeTotal['orders']); ?></div>
                    <div class="label">All-Time Orders</div>
                </div>
                <div class="summary-card highlight">
                    <div class="icon"><i class="fas fa-sack-dollar"></i></div>
                    <div class="value money-value">PHP <?php echo number_format($allTimeTotal['sales'], 2); ?></div>
                    <div class="label">All-Time Sales Amount</div>
                </div>
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="value" id="statYearlyUnits"><?php echo number_format($yearlyTotal['units']); ?></div>
                    <div class="label" id="labelUnitsYear">Units in <?php echo $selectedYear; ?></div>
                </div>
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="value" id="statYearlyOrders"><?php echo number_format($yearlyTotal['orders']); ?></div>
                    <div class="label" id="labelOrdersYear">Orders in <?php echo $selectedYear; ?></div>
                </div>
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-peso-sign"></i></div>
                    <div class="value money-value" id="statYearlySales">PHP <?php echo number_format($yearlyTotal['sales'], 2); ?></div>
                    <div class="label">Sales Amount in <?php echo $selectedYear; ?></div>
                </div>
            </div>

            <!-- Monthly Sales Section -->
            <h2 class="section-title" id="sectionMonthlyTitle">
                <i class="fas fa-calendar"></i>
                Monthly Sales - <?php echo $selectedYear; ?>
            </h2>

            <div class="charts-grid">
                <!-- Monthly Units Chart -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-bar"></i> Units Delivered per Month</h3>
                    <div class="chart-container chart-expandable" onclick="openChartPreview('monthlyUnitsChart','Units Delivered per Month')" style="position:relative;">
                        <canvas id="monthlyUnitsChart"></canvas>
                        <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                    </div>
                </div>

                <!-- Monthly Orders Chart -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-line"></i> Orders per Month</h3>
                    <div class="chart-container chart-expandable" onclick="openChartPreview('monthlyOrdersChart','Orders per Month')" style="position:relative;">
                        <canvas id="monthlyOrdersChart"></canvas>
                        <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                    </div>
                </div>
            </div>

            <!-- Monthly Sales Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 id="tableMonthlyHeader"><i class="fas fa-table"></i> Monthly Sales Breakdown - <?php echo $selectedYear; ?></h3>
                </div>
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Units Delivered</th>
                            <th>Orders</th>
                            <th>Sales Amount</th>
                            <th>Avg per Order</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tableMonthlyBody">
                        <?php 
                        $currentMonth = date('F');
                        $maxUnits = max(array_column($monthlySales, 'units'));
                        foreach ($allMonths as $month): 
                            $data = $monthlySales[$month];
                            $avg = $data['orders'] > 0 ? round($data['units'] / $data['orders'], 1) : 0;
                            $isCurrent = ($month === $currentMonth && $selectedYear == $currentYear);
                            $isHigh = ($data['units'] === $maxUnits && $maxUnits > 0);
                        ?>
                        <tr>
                            <td><strong><?php echo $month; ?></strong></td>
                            <td><?php echo number_format($data['units']); ?></td>
                            <td><?php echo number_format($data['orders']); ?></td>
                            <td>PHP <?php echo number_format($data['sales'], 2); ?></td>
                            <td><?php echo $avg; ?></td>
                            <td>
                                <?php if ($isCurrent): ?>
                                    <span class="badge-month badge-current"><i class="fas fa-clock"></i> Current</span>
                                <?php elseif ($isHigh): ?>
                                    <span class="badge-month badge-high"><i class="fas fa-star"></i> Highest</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td><strong>TOTAL</strong></td>
                            <td><?php echo number_format($yearlyTotal['units']); ?></td>
                            <td><?php echo number_format($yearlyTotal['orders']); ?></td>
                            <td>PHP <?php echo number_format($yearlyTotal['sales'], 2); ?></td>
                            <td><?php echo $yearlyTotal['orders'] > 0 ? round($yearlyTotal['units'] / $yearlyTotal['orders'], 1) : 0; ?></td>
                            <td>-</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Yearly Sales Section -->
            <h2 class="section-title">
                <i class="fas fa-chart-pie"></i>
                Yearly Sales Summary
            </h2>

            <div class="charts-grid">
                <!-- Yearly Chart -->
                <div class="chart-card">
                    <h3><i class="fas fa-chart-bar"></i> Yearly Comparison</h3>
                    <div class="chart-container chart-expandable" onclick="openChartPreview('yearlyChart','Yearly Comparison')" style="position:relative;">
                        <canvas id="yearlyChart"></canvas>
                        <span class="chart-expand-hint"><i class="fas fa-expand-alt"></i></span>
                    </div>
                </div>

                <!-- Yearly Table in Card -->
                <div class="chart-card">
                    <h3><i class="fas fa-list-alt"></i> Year-by-Year Data</h3>
                    <table class="sales-table" style="margin-top: 0;">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Units</th>
                                <th>Orders</th>
                                <th>Sales Amount</th>
                                <th>Avg/Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yearlySales as $data): 
                                $avg = $data['orders'] > 0 ? round($data['units'] / $data['orders'], 1) : 0;
                            ?>
                            <tr <?php echo $data['year'] == $selectedYear ? 'style="background: rgba(244, 208, 63, 0.1);"' : ''; ?>>
                                <td><strong><?php echo $data['year']; ?></strong></td>
                                <td><?php echo number_format($data['units']); ?></td>
                                <td><?php echo number_format($data['orders']); ?></td>
                                <td>PHP <?php echo number_format($data['sales'], 2); ?></td>
                                <td><?php echo $avg; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($yearlySales) > 1): ?>
                            <tr class="total-row">
                                <td><strong>ALL TIME</strong></td>
                                <td><?php echo number_format($allTimeTotal['units']); ?></td>
                                <td><?php echo number_format($allTimeTotal['orders']); ?></td>
                                <td>PHP <?php echo number_format($allTimeTotal['sales'], 2); ?></td>
                                <td><?php echo $allTimeTotal['orders'] > 0 ? round($allTimeTotal['units'] / $allTimeTotal['orders'], 1) : 0; ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="js/app.js" defer></script>
    <script>
        const PHP_CURRENT_MONTH = <?php echo json_encode(date('F')); ?>;
        const PHP_CURRENT_YEAR  = <?php echo intval($currentYear); ?>;
        const ALL_MONTHS = <?php echo $monthLabels; ?>;

        // Chart colors
        const isLightMode = document.body.classList.contains('light-mode') || document.documentElement.classList.contains('light-mode');
        const isDarkMode = !isLightMode;
        const chartColors = {
            primary: '#ffb703',
            secondary: '#3a86ff',
            success: '#06d6a0',
            info: '#00c2ff',
            accent: '#fb8500',
            violet: '#8338ec',
            gridColor: isDarkMode ? 'rgba(255, 255, 255, 0.12)' : 'rgba(15, 23, 42, 0.18)',
            textColor: isDarkMode ? '#e0e0e0' : '#1f2937'
        };

        const monthBarPalette = ['#ffb703', '#fb8500', '#00c2ff', '#3a86ff', '#06d6a0', '#ff4d9d', '#8338ec', '#4cc9f0', '#ff6d00', '#2ec4b6', '#5e60ce', '#f72585'];

        // Monthly Units Chart
        const monthlyUnitsCtx = document.getElementById('monthlyUnitsChart').getContext('2d');
        const monthlyUnitsChart = new Chart(monthlyUnitsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo $monthLabels; ?>,
                datasets: [{
                    label: 'Units Delivered',
                    data: <?php echo $monthUnits; ?>,
                    backgroundColor: monthBarPalette,
                    borderColor: '#ffffff',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: chartColors.gridColor },
                        ticks: { color: chartColors.textColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: chartColors.textColor, maxRotation: 45 }
                    }
                }
            }
        });

        // Monthly Orders Chart
        const monthlyOrdersCtx = document.getElementById('monthlyOrdersChart').getContext('2d');
        const monthlyOrdersChart = new Chart(monthlyOrdersCtx, {
            type: 'line',
            data: {
                labels: <?php echo $monthLabels; ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?php echo $monthOrders; ?>,
                    borderColor: chartColors.info,
                    backgroundColor: 'rgba(0, 194, 255, 0.24)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: chartColors.accent,
                    pointBorderColor: '#ffffff',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: chartColors.gridColor },
                        ticks: { color: chartColors.textColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: chartColors.textColor, maxRotation: 45 }
                    }
                }
            }
        });

        // Yearly Chart
        const yearlyCtx = document.getElementById('yearlyChart').getContext('2d');
        new Chart(yearlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo $yearLabels; ?>,
                datasets: [{
                    label: 'Units',
                    data: <?php echo $yearUnits; ?>,
                    backgroundColor: 'rgba(58, 134, 255, 0.9)',
                    borderColor: chartColors.secondary,
                    borderWidth: 2,
                    borderRadius: 6
                }, {
                    label: 'Orders',
                    data: <?php echo $yearOrders; ?>,
                    backgroundColor: 'rgba(6, 214, 160, 0.9)',
                    borderColor: chartColors.success,
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: chartColors.textColor }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: chartColors.gridColor },
                        ticks: { color: chartColors.textColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: chartColors.textColor }
                    }
                }
            }
        });

        // Filter update function for year, month, and day
        function updateFilters() {
            const year = document.getElementById('yearSelect').value;
            const month = document.getElementById('monthSelect').value;
            const day = document.getElementById('daySelect').value;

            // Build URL with parameters
            let url = 'sales-records.php?year=' + year;
            if (month) url += '&month=' + encodeURIComponent(month);
            if (day) url += '&day=' + day;

            // Update URL without reload
            history.replaceState(null, '', url);

            // Show loading shimmer on stat values
            ['statYearlyUnits', 'statYearlyOrders'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.style.opacity = '0.4'; }
            });

            // Build API URL
            let apiUrl = 'api/sales-data.php?year=' + year;
            if (month) apiUrl += '&month=' + encodeURIComponent(month);
            if (day) apiUrl += '&day=' + day;

            fetch(apiUrl)
                .then(r => r.json())
                .then(data => {
                    // Update available months when year changes
                    if (!month || month === '') {
                        const monthSelect = document.getElementById('monthSelect');
                        const currentMonth = monthSelect.value;
                        const oldHTML = monthSelect.innerHTML;
                        
                        // Only update if fetch returned months
                        if (data.availableMonths && data.availableMonths.length > 0) {
                            let monthHTML = '<option value="">All Months</option>';
                            data.availableMonths.forEach(m => {
                                monthHTML += `<option value="${m}">${m}</option>`;
                            });
                            monthSelect.innerHTML = monthHTML;
                        }
                    }

                    // Update available days when month changes (year always updates)
                    const daySelect = document.getElementById('daySelect');
                    if (data.availableDays) {
                        let dayHTML = '<option value="">All Days</option>';
                        data.availableDays.forEach(d => {
                            dayHTML += `<option value="${d}">Day ${d}</option>`;
                        });
                        daySelect.innerHTML = dayHTML;
                        daySelect.value = day || '';
                    }

                    // Update stat cards
                    document.getElementById('statYearlyUnits').textContent  = data.yearlyUnits.toLocaleString();
                    document.getElementById('statYearlyOrders').textContent = data.yearlyOrders.toLocaleString();
                    
                    // Update labels with filter info
                    let labelSuffix = ' in ' + data.year;
                    if (month) labelSuffix += ' - ' + month;
                    if (day) labelSuffix += ', Day ' + day;
                    
                    document.getElementById('labelUnitsYear').textContent   = 'Units' + labelSuffix;
                    document.getElementById('labelOrdersYear').textContent  = 'Orders' + labelSuffix;
                    document.getElementById('sectionMonthlyTitle').innerHTML =
                        '<i class="fas fa-calendar"></i> Monthly Sales - ' + data.year;
                    document.getElementById('tableMonthlyHeader').innerHTML =
                        '<i class="fas fa-table"></i> Monthly Sales Breakdown - ' + data.year;

                    // Restore opacity
                    ['statYearlyUnits', 'statYearlyOrders'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) { el.style.opacity = '1'; }
                    });

                    // Rebuild monthly table
                    const tbody = document.getElementById('tableMonthlyBody');
                    const yr = parseInt(year);
                    let maxUnits = 0;
                    data.monthData.forEach(r => { if (r.units > maxUnits) maxUnits = r.units; });

                    let totalUnits = 0, totalOrders = 0;
                    let rows = '';
                    data.monthData.forEach(r => {
                        totalUnits  += r.units;
                        totalOrders += r.orders;
                        const avg = r.orders > 0 ? (r.units / r.orders).toFixed(1) : 0;
                        const isCurrent = (r.month === PHP_CURRENT_MONTH && yr === PHP_CURRENT_YEAR);
                        const isHigh    = (r.units === maxUnits && maxUnits > 0);
                        let badge = '-';
                        if (isCurrent) badge = '<span class="badge-month badge-current"><i class="fas fa-clock"></i> Current</span>';
                        else if (isHigh) badge = '<span class="badge-month badge-high"><i class="fas fa-star"></i> Highest</span>';
                        rows += `<tr><td><strong>${r.month}</strong></td><td>${r.units.toLocaleString()}</td><td>${r.orders.toLocaleString()}</td><td>${avg}</td><td>${badge}</td></tr>`;
                    });
                    const totalAvg = totalOrders > 0 ? (totalUnits / totalOrders).toFixed(1) : 0;
                    rows += `<tr class="total-row"><td><strong>TOTAL</strong></td><td>${totalUnits.toLocaleString()}</td><td>${totalOrders.toLocaleString()}</td><td>${totalAvg}</td><td>-</td></tr>`;
                    tbody.innerHTML = rows;

                    // Update charts
                    monthlyUnitsChart.data.datasets[0].data  = data.monthUnits;
                    monthlyUnitsChart.update();

                    monthlyOrdersChart.data.datasets[0].data = data.monthOrders;
                    monthlyOrdersChart.update();
                })
                .catch((error) => {
                    console.error('Error:', error);
                    // Fallback to full reload on error
                    window.location.href = url;
                });
        }

        // Year selector — live update via AJAX (legacy function for backward compatibility)
        function changeYear(year) {
            document.getElementById('yearSelect').value = year;
            document.getElementById('monthSelect').value = '';
            document.getElementById('daySelect').value = '';
            updateFilters();
        }

        // Resize charts when sidebar toggles
        function resizeCharts() {
            setTimeout(() => {
                monthlyUnitsChart.resize();
                monthlyOrdersChart.resize();
            }, 350); // Wait for CSS transition to complete
        }

        // Listen for sidebar toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', resizeCharts);
        }

        // Also resize on window resize
        window.addEventListener('resize', function() {
            monthlyUnitsChart.resize();
            monthlyOrdersChart.resize();
        });
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

