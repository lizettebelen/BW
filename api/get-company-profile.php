<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db_config.php';

header('Content-Type: application/json');

if (!isset($_GET['company'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Company name required']);
    exit;
}

$companyName = $conn->real_escape_string($_GET['company']);

try {
    // Get company summary
    $summaryResult = $conn->query("
        SELECT 
            company_name,
            COUNT(*) as total_orders,
            SUM(quantity) as total_units,
            COUNT(DISTINCT item_code) as unique_products,
            MIN(delivery_date) as first_delivery,
            MAX(delivery_date) as last_delivery
        FROM delivery_records 
        WHERE company_name = '$companyName'
        GROUP BY company_name
    ");
    
    $summary = $summaryResult->fetch_assoc();
    
    if (!$summary) {
        http_response_code(404);
        echo json_encode(['error' => 'Company not found']);
        exit;
    }
    
    // Get products breakdown
    $productsResult = $conn->query("
        SELECT 
            item_code as model,
            SUM(quantity) as total_qty,
            COUNT(*) as order_count,
            MAX(delivery_date) as last_ordered
        FROM delivery_records 
        WHERE company_name = '$companyName'
        GROUP BY item_code
        ORDER BY total_qty DESC
    ");
    
    $products = [];
    while ($row = $productsResult->fetch_assoc()) {
        $products[] = $row;
    }
    
    // Get recent deliveries (last 10)
    $deliveriesResult = $conn->query("
        SELECT 
            delivery_date,
            item_code as model,
            quantity as qty,
            groupings
        FROM delivery_records 
        WHERE company_name = '$companyName'
        ORDER BY delivery_date DESC
        LIMIT 10
    ");
    
    $deliveries = [];
    while ($row = $deliveriesResult->fetch_assoc()) {
        $deliveries[] = $row;
    }
    
    // Get yearly breakdown
    $yearlyResult = $conn->query("
        SELECT 
            delivery_year as year,
            SUM(quantity) as units,
            COUNT(*) as orders
        FROM delivery_records 
        WHERE company_name = '$companyName' AND delivery_year > 0
        GROUP BY delivery_year
        ORDER BY year DESC
    ");
    
    $yearly = [];
    while ($row = $yearlyResult->fetch_assoc()) {
        $yearly[] = $row;
    }
    
    // Get monthly trend (last 12 months)
    $monthlyResult = $conn->query("
        SELECT 
            delivery_year || '-' || delivery_month as month,
            SUM(quantity) as units
        FROM delivery_records 
        WHERE company_name = '$companyName'
        GROUP BY delivery_year, delivery_month
        ORDER BY delivery_year DESC, delivery_month DESC
        LIMIT 12
    ");
    
    $monthly = [];
    while ($row = $monthlyResult->fetch_assoc()) {
        $monthly[] = $row;
    }
    $monthly = array_reverse($monthly);
    
    echo json_encode([
        'summary' => $summary,
        'products' => $products,
        'deliveries' => $deliveries,
        'yearly' => $yearly,
        'monthly' => $monthly
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
