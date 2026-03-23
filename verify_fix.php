<?php
require_once 'db_config.php';

$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
$allItems = [];
$itemResult = $conn->query("
    SELECT DISTINCT item_code, item_name
    FROM delivery_records
    WHERE item_code IS NOT NULL 
      AND item_code != ''
      AND item_name IS NOT NULL
      AND item_name != ''
    ORDER BY item_code ASC
");

if ($itemResult) {
    while ($row = $itemResult->fetch_assoc()) {
        $code = trim($row['item_code']);
        $name = trim($row['item_name']);
        
        if (!in_array($code, $monthNames) && !empty($code) && !empty($name)) {
            $allItems[] = ['code' => $code, 'name' => $name];
        }
    }
}

if (empty($allItems)) {
    $inventoryResult = $conn->query("
        SELECT DISTINCT item_code, item_name
        FROM delivery_records
        WHERE company_name = 'Stock Addition'
          AND item_code IS NOT NULL 
          AND item_code != ''
          AND item_name IS NOT NULL
          AND item_name != ''
        ORDER BY item_code ASC
    ");
    
    if ($inventoryResult) {
        while ($row = $inventoryResult->fetch_assoc()) {
            $code = trim($row['item_code']);
            $name = trim($row['item_name']);
            
            if (!in_array($code, $monthNames) && !empty($code) && !empty($name)) {
                $allItems[] = ['code' => $code, 'name' => $name];
            }
        }
    }
}

echo "Total items for dropdown: " . count($allItems) . "\n\n";
echo "First 15 items:\n";
for ($i = 0; $i < 15 && $i < count($allItems); $i++) {
    echo ($i+1) . ". " . $allItems[$i]['code'] . " | " . $allItems[$i]['name'] . "\n";
}
?>
