<?php
require_once __DIR__ . '/../../db_config.php';

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

if ($result === false) {
    echo "SQL error: " . ($conn->error ?? 'Unknown error') . "\n";
    exit;
}

$rowCount = 0;
while ($row = $result->fetch_assoc()) {
    $rowCount++;
    $code = $row['item_code'] ?: 'N/A';
    $name = mb_substr((string) ($row['item_name'] ?? 'N/A'), 0, 30);
    $added = (int) ($row['units_added'] ?? 0);
    $delivered = (int) ($row['units_delivered'] ?? 0);
    $current = (int) ($row['current_stock'] ?? 0);

    printf("%-10s | %-30s | %13d | %21d | %13d\n", $code, $name, $added, $delivered, $current);
}

if ($rowCount === 0) {
    echo "No inventory rows found in delivery_records.\n";
    echo "Tip: import data first or run populate-direct.php to seed sample stock records.\n";
}
?>
