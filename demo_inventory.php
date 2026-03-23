<?php
require_once 'db_config.php';

$sql = "
    SELECT 
        item_code,
        item_name,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_added,
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as units_delivered,
        COALESCE(SUM(CASE WHEN company_name = 'Stock Addition' THEN quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN company_name != 'Stock Addition' THEN quantity ELSE 0 END), 0) as current_stock,
        COUNT(CASE WHEN company_name != 'Stock Addition' THEN 1 END) as num_deliveries
    FROM delivery_records
    GROUP BY item_code, item_name
    ORDER BY item_name
    LIMIT 5
";

$result = $conn->query($sql);

echo "=== INVENTORY TRACKING SYSTEM ===\n";
echo "Demonstrating: Added Stock - Delivered Quantity = Current Stock\n\n";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Item: " . $row['item_code'] . " - " . $row['item_name'] . "\n";
        echo "  Stock Added (via Add Stock): " . $row['units_added'] . " units\n";
        echo "  Delivered to Companies: " . $row['units_delivered'] . " units (" . $row['num_deliveries'] . " deliveries)\n";
        echo "  Current Stock: " . $row['current_stock'] . " units\n";
        echo "  Calculation: {$row['units_added']} - {$row['units_delivered']} = {$row['current_stock']}\n\n";
    }
} else {
    echo "No items found or query failed.\n";
}
?>
