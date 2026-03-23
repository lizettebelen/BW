<?php
session_start();
require_once 'db_config.php';
require_once 'dataset-indicator.php';

// Dataset selection from GET or SESSION
$selected_dataset = isset($_GET['dataset']) ? trim($_GET['dataset']) : (isset($_SESSION['active_dataset']) ? $_SESSION['active_dataset'] : 'all');
if (isset($_GET['dataset'])) {
    $_SESSION['active_dataset'] = $selected_dataset;
}

// Build dataset filter
$dataset_filter = "";
if ($selected_dataset !== 'all' && $selected_dataset !== '') {
    $safe_dataset = $conn->real_escape_string($selected_dataset);
    $dataset_filter = " AND dataset_name = '$safe_dataset'";
}

// Check if there are duplicate item codes in the query results
$sql = "
    SELECT 
        item_code,
        item_name,
        COUNT(*) as times_appearing
    FROM (
        SELECT 
            item_code,
            item_name,
            COUNT(CASE WHEN company_name != 'Stock Addition' THEN 1 END) as delivery_count
        FROM delivery_records
        WHERE 1=1$dataset_filter
        GROUP BY item_code, item_name
    )
    GROUP BY item_code
    HAVING COUNT(*) > 1
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "❌ DUPLICATES FOUND:\n\n";
    while ($row = $result->fetch_assoc()) {
        echo "Item Code: {$row['item_code']}\n";
        echo "  Appears {$row['times_appearing']} times in results\n\n";
    }
} else {
    echo "✓ No duplicates in base query\n";
    echo "The issue might be in how results are being displayed or stored.\n";
}

// Check if there are multiple item names for same code
echo "\n=== Checking for variant item names ===\n";
$sql2 = "SELECT DISTINCT item_code, item_name FROM delivery_records WHERE 1=1$dataset_filter ORDER BY item_code";
$result2 = $conn->query($sql2);
$currentCode = '';
$codeCount = [];
while ($row = $result2->fetch_assoc()) {
    if ($currentCode !== $row['item_code']) {
        if ($currentCode !== '') {
            if ($codeCount[$currentCode] > 1) {
                echo "\n⚠️  {$currentCode} has {$codeCount[$currentCode]} different names:\n";
                // Get all names for this code
                $safe_code = $conn->real_escape_string($row['item_code']);
                $nameResult = $conn->query("SELECT DISTINCT item_name FROM delivery_records WHERE item_code = '$safe_code'$dataset_filter");
                while ($nameRow = $nameResult->fetch_assoc()) {
                    echo "   - {$nameRow['item_name']}\n";
                }
            }
        }
        $currentCode = $row['item_code'];
        $codeCount[$currentCode] = 0;
    }
    $codeCount[$currentCode]++;
}

// Display page header with dataset indicator
echo "=== Duplicate Item Checker ===\n";
echo renderDatasetIndicator($selected_dataset) . "\n";
?>
