<?php
require_once 'db_config.php';

// Test what the last delivery query returns
$sql = "
    SELECT 
        item_code,
        delivery_year,
        delivery_month,
        delivery_day,
        MAX(delivery_year) || '-' || printf('%02d', delivery_month) || '-' || printf('%02d', delivery_day) as last_delivery_formatted,
        created_at
    FROM delivery_records
    WHERE company_name != 'Stock Addition'
    GROUP BY item_code
    LIMIT 3
";

$result = $conn->query($sql);

echo "Testing last_delivery_date calculation:\n\n";
while ($row = $result->fetch_assoc()) {
    echo "Item: {$row['item_code']}\n";
    echo "  Raw year: {$row['delivery_year']}\n";
    echo "  Raw month: {$row['delivery_month']}\n";
    echo "  Raw day: {$row['delivery_day']}\n";
    echo "  Formatted: {$row['last_delivery_formatted']}\n";
    echo "  Created_at: {$row['created_at']}\n\n";
}
?>
