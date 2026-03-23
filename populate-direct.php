<?php
// No session check - for setup/testing only
require_once 'db_config.php';

echo "<h2>📦 Populating Inventory with Groupings (Direct)</h2>";
echo "<p>Status: Running...</p>";
echo "<hr>";

// First, clear old test data if needed - OPTIONAL
// Uncomment to delete old items:
// $conn->query("DELETE FROM delivery_records WHERE company_name = 'Stock Addition' AND item_code LIKE 'I%'");

// Dummy data with proper groupings
$dummyData = [
    // Group A - Single Gas
    ['code' => 'I-MCXL-BCI', 'name' => 'Replacement Back Enclosure for GasalertMicroClip XL', 'group' => 'Group A - Single Gas', 'qty' => 25],
    ['code' => 'I1-QT-AF-KI', 'name' => 'Auxiliary Filter (LCD Protector) - includes 5 filters', 'group' => 'Group A - Single Gas', 'qty' => 30],
    ['code' => 'I1-QT-LCD-KI', 'name' => 'Replacement LCD Kit for GasAlert Quattro', 'group' => 'Group A - Single Gas', 'qty' => 15],
    ['code' => 'I1-QT-TC-I', 'name' => 'Test Cap and Hose for Quattro', 'group' => 'Group A - Single Gas', 'qty' => 20],
    ['code' => 'I2-GA-BALERT', 'name' => 'Concussion-proof boot for GasAlert Extreme', 'group' => 'Group A - Single Gas', 'qty' => 18],
    ['code' => 'I2-GA-PA-I-NA', 'name' => 'Replacement Power Adaptor GA-PA-I-EU', 'group' => 'Group A - Single Gas', 'qty' => 12],
    ['code' => 'I3-MC-AF-KI', 'name' => 'Auxiliary Filter Kit (with filter adaptor and 5 filters)', 'group' => 'Group A - Single Gas', 'qty' => 22],
    ['code' => 'I3-MC-LCD-KI', 'name' => 'Replacement LCD Kit for Microclip XT/XL', 'group' => 'Group A - Single Gas', 'qty' => 16],
    ['code' => 'I4-XT-MPCB2', 'name' => 'Replacement Main PCB with screws for GasAlertMax XT II', 'group' => 'Group A - Single Gas', 'qty' => 14],
    ['code' => 'I5-HU-BC', 'name' => 'Replacement Back Enclosure', 'group' => 'Group A - Single Gas', 'qty' => 19],
    ['code' => 'I5-HU-FC-Y', 'name' => 'Replacement Front Enclosure with LCD gasket (Yellow)', 'group' => 'Group A - Single Gas', 'qty' => 21],
    ['code' => 'I5-HU-FPCB', 'name' => 'Replacement Flex PCB', 'group' => 'Group A - Single Gas', 'qty' => 13],
    ['code' => 'I5-HU-IN', 'name' => 'Replacement Pump Inlet with Screw', 'group' => 'Group A - Single Gas', 'qty' => 17],
    
    // Group B - Multi Gas
    ['code' => 'I1-QT-PCB-KI', 'name' => 'Quattro Main PCB Kit', 'group' => 'Group B - Multi Gas', 'qty' => 11],
    ['code' => 'I1-QT-SCREW-KI', 'name' => 'Replacement screw kit for GasAlertQuattro (kit of 40 screws)', 'group' => 'Group B - Multi Gas', 'qty' => 28],
    ['code' => 'I3-MC2-MPCBI', 'name' => 'Main PCB w/ Battery & Screw for MC2-XT (Legacy)', 'group' => 'Group B - Multi Gas', 'qty' => 8],
    ['code' => 'I3-MCXL-MPCBI', 'name' => 'Main PCB with Battery and Screw for MicroClip XT', 'group' => 'Group B - Multi Gas', 'qty' => 9],
    ['code' => 'I5B-XT-RPUMP-KI', 'name' => 'Replacement Pump (includes pump and 4R+ manifold)', 'group' => 'Group B - Multi Gas', 'qty' => 6],
    ['code' => 'I6-BWS-SS', 'name' => 'BW Single Sensor Module - Multi Gas', 'group' => 'Group B - Multi Gas', 'qty' => 32],
    ['code' => 'I6-GA-PFMAX', 'name' => 'GasAlert Personal Fully Featured Max Unit - Multi', 'group' => 'Group B - Multi Gas', 'qty' => 24],
    ['code' => 'I6-GAXT-SS', 'name' => 'GasAlertMax XT Single Sensor - Multi Gas', 'group' => 'Group B - Multi Gas', 'qty' => 27],
    ['code' => 'I6-MC2-SS-KI', 'name' => 'MC2 Single Sensor Kit - Multi Gas', 'group' => 'Group B - Multi Gas', 'qty' => 19],
    ['code' => 'I6-QT-SS-KI', 'name' => 'Quattro Single Sensor Kit - Multi Gas', 'group' => 'Group B - Multi Gas', 'qty' => 23],
    ['code' => 'I6-XT-RF-H50', 'name' => 'XT Radio Frequency Module H50 - Multi Gas', 'group' => 'Group B - Multi Gas', 'qty' => 10],
    ['code' => 'I6-XT-SS-K2', 'name' => 'XT Single Sensor Kit 2 - Multi Gas', 'group' => 'Group B - Multi Gas', 'qty' => 31],
    ['code' => 'I7-MC-SS-AF-KI', 'name' => 'MC Single Sensor with Auxiliary Filter Kit - Multi', 'group' => 'Group B - Multi Gas', 'qty' => 26],
    ['code' => 'I8-MC2-FPCBI', 'name' => 'MC2 Flex PCB Interface - Multi Gas', 'group' => 'Group B - Multi Gas', 'qty' => 7],
    ['code' => 'I9-SR-X2V', 'name' => 'Single Gas to Multi Gas Converter X2V', 'group' => 'Group B - Multi Gas', 'qty' => 12],
];

$successCount = 0;
$errorCount = 0;
$skipCount = 0;

foreach ($dummyData as $item) {
    $code = $item['code'];
    $name = $item['name'];
    $group = $item['group'];
    $qty = $item['qty'];
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $delivery_month = date('F');
    $delivery_day = intval(date('j'));
    $delivery_year = intval(date('Y'));
    
    // Check if item already exists
    $checkSql = "SELECT id FROM delivery_records WHERE item_code = ? AND company_name = 'Stock Addition' LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        echo "<p style='color: red;'>❌ Prepare check failed: " . ($conn instanceof mysqli ? $conn->error : $conn->error) . "</p>";
        $errorCount++;
        continue;
    }
    
    $checkStmt->bind_param("s", $code);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo "<p style='color: orange;'>⏭️  Already exists: {$code}</p>";
        $skipCount++;
        $checkStmt->close();
        continue;
    }
    
    $checkStmt->close();
    
    // Insert new item
    $insertSql = "INSERT INTO delivery_records 
                  (delivery_month, delivery_day, delivery_year, item_code, item_name, company_name, quantity, groupings, status, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        echo "<p style='color: red;'>❌ Prepare insert failed for {$code}: " . $conn->error . "</p>";
        $errorCount++;
        continue;
    }
    
    $company = 'Stock Addition';
    $status = 'Pending';
    
    $insertStmt->bind_param(
        "siissssisss",
        $delivery_month,
        $delivery_day,
        $delivery_year,
        $code,
        $name,
        $company,
        $qty,
        $group,
        $status,
        $now,
        $now
    );
    
    if ($insertStmt->execute()) {
        echo "<p style='color: green;'>✅ Added: {$code} - {$name} ({$group}) - Qty: {$qty}</p>";
        $successCount++;
    } else {
        echo "<p style='color: red;'>❌ Insert failed for {$code}: " . $insertStmt->error . "</p>";
        $errorCount++;
    }
    
    $insertStmt->close();
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p><strong style='color:green;'>✅ Successfully added:</strong> {$successCount} items</p>";
echo "<p><strong style='color:orange;'>⏭️  Already existed:</strong> {$skipCount} items</p>";
echo "<p><strong style='color:red;'>❌ Errors:</strong> {$errorCount} items</p>";
echo "<p><strong style='color:blue;'>Total in database:</strong> ";

$totalResult = $conn->query("SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'");
$totalRow = $totalResult->fetch_assoc();
echo $totalRow['cnt'] . " items</p>";

echo "<p><a href='inventory.php'>→ Go to Inventory</a> | <a href='models.php'>→ Go to Models</a></p>";
?>
