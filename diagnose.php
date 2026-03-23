<?php
require_once 'db_config.php';

echo "<h2>🔍 System Diagnostic</h2>";
echo "<hr>";

// Check database type
if ($conn instanceof mysqli) {
    echo "<p><strong>✓ Database Type:</strong> MySQL</p>";
    echo "<p><strong>Database Name:</strong> " . $conn->get_server_info() . "</p>";
} else {
    echo "<p><strong>✓ Database Type:</strong> SQLite</p>";
}

echo "<hr>";

// Test 1: Count all records
$testSQL = "SELECT COUNT(*) as cnt FROM delivery_records";
$result = $conn->query($testSQL);
if ($result) {
    if ($conn instanceof mysqli) {
        $row = $result->fetch_assoc();
        $totalCount = $row['cnt'];
    } else {
        $row = $result->fetch_assoc();
        $totalCount = $row['cnt'];
    }
    echo "<p><strong>✓ Total records:</strong> {$totalCount}</p>";
} else {
    echo "<p><strong>❌ Error counting records:</strong> " . ($conn instanceof mysqli ? $conn->error : $conn->error) . "</p>";
}

// Test 2: Count Stock Addition records
$testSQL = "SELECT COUNT(*) as cnt FROM delivery_records WHERE company_name = 'Stock Addition'";
$result = $conn->query($testSQL);
if ($result) {
    if ($conn instanceof mysqli) {
        $row = $result->fetch_assoc();
    } else {
        $row = $result->fetch_assoc();
    }
    echo "<p><strong>✓ Stock Addition items:</strong> {$row['cnt']}</p>";
} else {
    echo "<p><strong>❌ Error:</strong> " . ($conn instanceof mysqli ? $conn->error : $conn->error) . "</p>";
}

// Test 3: Check for groupings column
$testSQL = "SELECT * FROM delivery_records LIMIT 1";
$result = $conn->query($testSQL);
if ($result && $result->num_rows > 0) {
    if ($conn instanceof mysqli) {
        $row = $result->fetch_assoc();
    } else {
        $row = $result->fetch_assoc();
    }
    
    if (isset($row['groupings'])) {
        echo "<p><strong>✓ Groupings column:</strong> EXISTS</p>";
    } else {
        echo "<p><strong>⚠️ Groupings column:</strong> MISSING</p>";
    }
}

echo "<hr>";

// Test 4: Try inserting a test item manually
echo "<h3>Test Insert:</h3>";

$testCode = "TEST-" . time();
$testName = "Test Item - Quattro Multi Gas";
$testGroup = "Group B - Multi Gas";
$testQty = 5;
$now = date('Y-m-d H:i:s');
$delivery_month = date('F');
$delivery_day = intval(date('j'));
$delivery_year = intval(date('Y'));

$insertSQL = "INSERT INTO delivery_records 
              (delivery_month, delivery_day, delivery_year, item_code, item_name, company_name, quantity, groupings, status, created_at, updated_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($conn instanceof mysqli) {
    $stmt = $conn->prepare($insertSQL);
    if ($stmt) {
        $company = 'Stock Addition';
        $status = 'Pending';
        $stmt->bind_param(
            "siissssisss",
            $delivery_month,
            $delivery_day,
            $delivery_year,
            $testCode,
            $testName,
            $company,
            $testQty,
            $testGroup,
            $status,
            $now,
            $now
        );
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'><strong>✓ Test insert:</strong> SUCCESS</p>";
            echo "<p>Inserted: {$testCode} | {$testName}</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Insert error:</strong> " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'><strong>❌ Prepare error:</strong> " . $conn->error . "</p>";
    }
} else {
    // SQLite
    $result = $conn->query($insertSQL);
    if ($result) {
        echo "<p style='color: green;'><strong>✓ Test insert:</strong> SUCCESS (SQLite)</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Insert error:</strong> " . $conn->error . "</p>";
    }
}

echo "<hr>";

// Test 5: Verify test item can be retrieved
$verifySQL = "SELECT * FROM delivery_records WHERE company_name = 'Stock Addition' ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($verifySQL);
echo "<h3>Latest Stock Addition Items:</h3>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='width:100%;'>";
    echo "<tr><th>Code</th><th>Name</th><th>Group</th><th>Qty</th><th>Created</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $code = htmlspecialchars($row['item_code']);
        $name = htmlspecialchars(substr($row['item_name'], 0, 50));
        $group = htmlspecialchars($row['groupings'] ?? 'NULL');
        $qty = $row['quantity'];
        $created = substr($row['created_at'], 0, 10);
        
        echo "<tr>";
        echo "<td>{$code}</td>";
        echo "<td>{$name}...</td>";
        echo "<td>{$group}</td>";
        echo "<td>{$qty}</td>";
        echo "<td>{$created}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No Stock Addition items found</p>";
}

echo "<hr>";
echo "<p><a href='populate-direct.php'>→ Run Populate Script</a> | <a href='models.php'>→ Check Models Page</a></p>";
?>
