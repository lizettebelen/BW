<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
require_once '../db_config.php';

$allMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$isMysql   = ($conn instanceof mysqli);
$yearExpr  = $isMysql
    ? "CASE WHEN delivery_year > 0 THEN delivery_year WHEN delivery_date IS NOT NULL THEN YEAR(delivery_date) ELSE YEAR(created_at) END"
    : "CASE WHEN delivery_year > 0 THEN delivery_year WHEN delivery_date IS NOT NULL THEN CAST(strftime('%Y', delivery_date) AS INTEGER) ELSE CAST(strftime('%Y', created_at) AS INTEGER) END";

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$selectedMonth = isset($_GET['month']) ? trim($_GET['month']) : '';
$selectedDay = isset($_GET['day']) ? intval($_GET['day']) : 0;

// Sanitize month
if ($selectedMonth && !in_array($selectedMonth, $allMonths)) {
    $selectedMonth = '';
}

// Build WHERE clause
$whereConditions = ["({$yearExpr}) = {$selectedYear}"];

if ($selectedMonth) {
    $monthEscaped = $conn->real_escape_string($selectedMonth);
    $whereConditions[] = "delivery_month = '{$monthEscaped}'";
}

if ($selectedDay > 0) {
    $dayExpr = $isMysql
        ? "CAST(DAY(delivery_date) AS UNSIGNED)"
        : "CAST(strftime('%d', delivery_date) AS INTEGER)";
    $whereConditions[] = "{$dayExpr} = {$selectedDay}";
}

$whereClause = implode(' AND ', $whereConditions);

// Get available months for the selected year
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
$dayConditions = ["({$yearExpr}) = {$selectedYear}"];
if ($selectedMonth) {
    $monthEscaped = $conn->real_escape_string($selectedMonth);
    $dayConditions[] = "delivery_month = '{$monthEscaped}'";
}
$dayConditions[] = "delivery_date IS NOT NULL";
$dayWhereClause = implode(' AND ', $dayConditions);

$dayExpr = $isMysql
    ? "CAST(DAY(delivery_date) AS UNSIGNED)"
    : "CAST(strftime('%d', delivery_date) AS INTEGER)";

$dayResult = $conn->query("
    SELECT DISTINCT {$dayExpr} as day 
    FROM delivery_records 
    WHERE {$dayWhereClause}
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

// Monthly data for selected year/month
$monthlySales = array_fill_keys($allMonths, ['units' => 0, 'orders' => 0]);
$result = $conn->query("
    SELECT delivery_month,
           COUNT(*) as order_count,
           COALESCE(SUM(CASE WHEN company_name IS NOT NULL AND company_name != '' THEN quantity ELSE 0 END), 0) as total_units
    FROM delivery_records
    WHERE {$whereClause}
    GROUP BY delivery_month
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $m = $row['delivery_month'];
        if (array_key_exists($m, $monthlySales)) {
            $monthlySales[$m] = [
                'units'  => intval($row['total_units']),
                'orders' => intval($row['order_count'])
            ];
        }
    }
}

$yearlyTotal = ['units' => 0, 'orders' => 0];
foreach ($monthlySales as $d) {
    $yearlyTotal['units']  += $d['units'];
    $yearlyTotal['orders'] += $d['orders'];
}

echo json_encode([
    'year'          => $selectedYear,
    'month'         => $selectedMonth,
    'day'           => $selectedDay,
    'yearlyUnits'   => $yearlyTotal['units'],
    'yearlyOrders'  => $yearlyTotal['orders'],
    'availableMonths' => $availableMonths,
    'availableDays'   => $availableDays,
    'monthUnits'    => array_values(array_map(fn($m) => $monthlySales[$m]['units'],  $allMonths)),
    'monthOrders'   => array_values(array_map(fn($m) => $monthlySales[$m]['orders'], $allMonths)),
    'monthData'     => array_map(fn($m) => [
        'month'  => $m,
        'units'  => $monthlySales[$m]['units'],
        'orders' => $monthlySales[$m]['orders'],
    ], $allMonths),
]);
