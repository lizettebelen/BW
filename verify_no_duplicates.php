<?php
require_once 'db_config.php';

// Test the fixed query - should show no duplicates now
$sql = "
    SELECT 
        item_code,
        MAX(item_name) as item_name,
        COUNT(CASE WHEN company_name != 'Stock Addition' THEN 1 END) as delivery_count,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_added,
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_delivered,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as current_stock
    FROM delivery_records
    GROUP BY item_code
    ORDER BY item_name ASC
    LIMIT 10
";

$result = $conn->query($sql);

echo "=== INVENTORY ITEMS (No Duplicates) ===\n\n";
echo "Item Code | Item Name | Added | Delivered | Current Stock\n";
echo str_repeat("-", 80) . "\n";

while ($row = $result->fetch_assoc()) {
    $code = str_pad($row['item_code'], 9);
    $name = substr($row['item_name'], 0, 35);
    printf("%s | %-35s | %5d | %9d | %13d\n", 
        $code, 
        $name, 
        $row['units_added'],
        $row['units_delivered'],
        $row['current_stock']
    );
}
?>
