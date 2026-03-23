<?php
require_once 'db_config.php';

echo "Testing inventory query...\n\n";

// Test the exact query from inventory.php
$searchQuery = ""; // Empty search like in the page

$sql = "
    SELECT 
        item_code,
        item_name,
        COUNT(CASE WHEN company_name != 'Stock Addition' THEN 1 END) as delivery_count,
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_delivered,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_added,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as current_stock,
        MAX(CASE WHEN company_name != 'Stock Addition' THEN delivery_year || '-' || printf('%02d', delivery_month) || '-' || printf('%02d', delivery_day) END) as last_delivery_date,
        MAX(CASE WHEN company_name != 'Stock Addition' THEN created_at END) as last_delivery_timestamp
    FROM delivery_records
    GROUP BY item_code, item_name
    ORDER BY item_name ASC
";

$result = $conn->query($sql);

if ($result) {
    $count = $result->num_rows;
    echo "✓ Query successful! Found $count items\n\n";
    
    echo "Sample items:\n";
    $i = 0;
    while ($row = $result->fetch_assoc() && $i < 5) {
        echo "Item: {$row['item_code']} - {$row['item_name']}\n";
        echo "  Added to stock: {$row['units_added']} units\n";
        echo "  Delivered to companies: {$row['units_delivered']} units\n";
        echo "  Current stock: {$row['current_stock']} units\n";
        echo "  Deliveries count: {$row['delivery_count']}\n";
        echo "  Last delivery: {$row['last_delivery_date']}\n\n";
        $i++;
    }
} else {
    echo "✗ Query failed: " . $conn->error;
}
?>
