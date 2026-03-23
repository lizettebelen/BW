<?php
require_once 'db_config.php';

// Simple test to see items and their stock calculations
$sql = "
    SELECT 
        item_code,
        item_name,
        COUNT(CASE WHEN company_name != 'Stock Addition' THEN 1 END) as delivery_count,
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_delivered,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_added,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as current_stock
    FROM delivery_records
    GROUP BY item_code, item_name
    ORDER BY item_name ASC
    LIMIT 10
";

$result = $conn->query($sql);

echo "=== INVENTORY STOCK CALCULATION ===\n\n";
echo "Item Code | Item Name | Added (Stock) | Delivered (Companies) | Current Stock\n";
echo str_repeat("-", 100) . "\n";

while ($row = $result->fetch_assoc()) {
    $code = $row['item_code'];
    $name = mb_substr($row['item_name'], 0, 30);
    $added = $row['units_added'];
    $delivered = $row['units_delivered'];
    $current = $row['current_stock'];
    
    printf("%-10s | %-30s | %13d | %21d | %13d\n", $code, $name, $added, $delivered, $current);
}
?>
