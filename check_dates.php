<?php
require_once 'db_config.php';

// Check what dates are actually in the database
$result = $conn->query("
    SELECT DISTINCT 
        delivery_year, 
        delivery_month, 
        delivery_day,
        COUNT(*) as count
    FROM delivery_records
    WHERE company_name != 'Stock Addition'
    GROUP BY delivery_year, delivery_month, delivery_day
    ORDER BY delivery_year DESC, delivery_month DESC, delivery_day DESC
    LIMIT 10
");

echo "=== ACTUAL DATES IN DATABASE ===\n\n";
while ($row = $result->fetch_assoc()) {
    echo "{$row['delivery_year']}-{$row['delivery_month']}-{$row['delivery_day']}: {$row['count']} records\n";
}

// Also check the date range
echo "\n=== DATE RANGE ===\n";
$result = $conn->query("
    SELECT 
        MIN(delivery_year) as min_year,
        MAX(delivery_year) as max_year
    FROM delivery_records
    WHERE company_name != 'Stock Addition'
");

$row = $result->fetch_assoc();
echo "From: {$row['min_year']} to {$row['max_year']}\n";
?>
