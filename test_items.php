<?php
require_once 'db_config.php';

echo "=== Testing $allItems Population ===\n\n";

// Simulate the exact code from delivery-records.php
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
$allItems = [];
$itemResult = $conn->query("
    SELECT DISTINCT item_code, item_name
    FROM delivery_records
    WHERE item_code IS NOT NULL 
      AND item_code != ''
      AND item_code NOT REGEXP '^\s*$'
      AND item_name IS NOT NULL
      AND item_name != ''
    ORDER BY item_code ASC
");

echo "First query result count: " . ($itemResult ? $itemResult->num_rows : 0) . "\n";

if ($itemResult) {
    while ($row = $itemResult->fetch_assoc()) {
        $code = trim($row['item_code']);
        $name = trim($row['item_name']);
        
        // Skip if item_code is just a month name
        if (!in_array($code, $monthNames) && !empty($name)) {
            $allItems[] = [
                'code' => $code,
                'name' => $name
            ];
        }
    }
}

echo "After first query - $allItems count: " . count($allItems) . "\n\n";

// If no items found, fetch from inventory
if (empty($allItems)) {
    echo "First query was empty, executing fallback query...\n\n";
    $inventoryResult = $conn->query("
        SELECT DISTINCT item_code, item_name
        FROM delivery_records
        WHERE company_name = 'Stock Addition'
          AND item_code IS NOT NULL 
          AND item_code != ''
          AND item_code NOT REGEXP '^\s*$'
          AND item_name IS NOT NULL
          AND item_name != ''
        ORDER BY item_code ASC
    ");
    
    echo "Fallback query result count: " . ($inventoryResult ? $inventoryResult->num_rows : 0) . "\n";
    
    if ($inventoryResult) {
        while ($row = $inventoryResult->fetch_assoc()) {
            $code = trim($row['item_code']);
            $name = trim($row['item_name']);
            
            if (!in_array($code, $monthNames) && !empty($name)) {
                $allItems[] = [
                    'code' => $code,
                    'name' => $name
                ];
            }
        }
    }
}

echo "Final $allItems count: " . count($allItems) . "\n\n";
echo "Sample items:\n";
for ($i = 0; $i < 10 && $i < count($allItems); $i++) {
    echo ($i+1) . ". " . $allItems[$i]['code'] . " | " . $allItems[$i]['name'] . "\n";
}
?>
