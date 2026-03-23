<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

require_once 'db_config.php';

echo "<h2>📦 Populating Inventory with Groupings</h2>";
echo "<p>Database: " . ($conn instanceof mysqli ? "MySQL" : "SQLite") . "</p>";
echo "<hr>";

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
    ['code' => '5B-XT-RPUMP-KI', 'name' => 'Replacement Pump (includes pump and 4R+ manifold', 'group' => 'Group B - Multi Gas', 'qty' => 6],
    ['code' => '6 / 9-BWS-SS', 'name' => 'BW Single Sensor Module', 'group' => 'Group B - Multi Gas', 'qty' => 32],
    ['code' => '6 / 9-GA-PFMAX', 'name' => 'GasAlert Personal Fully Featured Max Unit', 'group' => 'Group B - Multi Gas', 'qty' => 24],
    ['code' => '6 / 9-GAXT-SS', 'name' => 'GasAlertMax XT Single Sensor', 'group' => 'Group B - Multi Gas', 'qty' => 27],
    ['code' => '6 / 9-MC2-SS-KI', 'name' => 'MC2 Single Sensor Kit', 'group' => 'Group B - Multi Gas', 'qty' => 19],
    ['code' => '6 / 9-QT-SS-KI', 'name' => 'Quattro Single Sensor Kit', 'group' => 'Group B - Multi Gas', 'qty' => 23],
    ['code' => '6 / 9-XT-RF-H50', 'name' => 'XT Radio Frequency Module H50', 'group' => 'Group B - Multi Gas', 'qty' => 10],
    ['code' => '6 / 9-XT-SS-K2', 'name' => 'XT Single Sensor Kit 2', 'group' => 'Group B - Multi Gas', 'qty' => 31],
    ['code' => '6-MC-SS-AF-KI', 'name' => 'MC Single Sensor with Auxiliary Filter Kit', 'group' => 'Group B - Multi Gas', 'qty' => 26],
    ['code' => '7-MC2-FPCBI', 'name' => 'MC2 Flex PCB Interface', 'group' => 'Group B - Multi Gas', 'qty' => 7],
    ['code' => '8 / I0-SR-X2V', 'name' => 'Single Gas to Multi Gas Converter X2V', 'group' => 'Group B - Multi Gas', 'qty' => 12],
];

$successCount = 0;
$errorCount = 0;

foreach ($dummyData as $item) {
    $code = $item['code'];
    $name = $item['name'];
    $group = $item['group'];
    $qty = $item['qty'];
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $delivery_month = date('F');
    $delivery_day = date('j');
    $delivery_year = date('Y');
    
    // Check if item already exists
    $checkSql = "SELECT id FROM delivery_records WHERE item_code = ? AND company_name = 'Stock Addition' LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $code);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        // Insert new item
        $insertSql = "INSERT INTO delivery_records 
                      (delivery_month, delivery_day, delivery_year, item_code, item_name, company_name, quantity, groupings, status, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, 'Stock Addition', ?, ?, 'Pending', ?, ?)";
        
        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            echo "<p style='color: red;'>❌ Prepare failed for {$code}: " . $conn->error . "</p>";
            $errorCount++;
        } else {
            $insertStmt->bind_param("siissiiss", $delivery_month, $delivery_day, $delivery_year, $code, $name, $qty, $group, $now, $now);
            
            if ($insertStmt->execute()) {
                echo "<p style='color: green;'>✅ Added: {$code} - {$name} ({$group}) - Qty: {$qty}</p>";
                $successCount++;
            } else {
                echo "<p style='color: red;'>❌ Error adding {$code}: " . $insertStmt->error . "</p>";
                $errorCount++;
            }
            $insertStmt->close();
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Already exists: {$code}</p>";
    }
    
    $checkStmt->close();
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p><strong>✅ Successfully added:</strong> {$successCount} items</p>";
echo "<p><strong>❌ Errors:</strong> {$errorCount} items</p>";
echo "<p><a href='inventory.php'>Back to Inventory</a></p>";
?>
