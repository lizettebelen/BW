<?php
require_once __DIR__ . '/../db_config.php';

if (!$conn) {
    die("Database connection failed");
}

$is_mysql = get_class($conn) === 'mysqli';

echo "Database type: " . ($is_mysql ? "MySQL" : "SQLite") . "\n";

// Check if column exists
if ($is_mysql) {
    $result = @$conn->query("SHOW COLUMNS FROM delivery_records LIKE 'model_no'");
    $exists = $result && $result->num_rows > 0;
} else {
    $result = @$conn->query("PRAGMA table_info(delivery_records)");
    $exists = false;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (($row['name'] ?? $row[1]) === 'model_no') {
                $exists = true;
                break;
            }
        }
    }
}

if (!$exists) {
    echo "Adding model_no column...\n";
    
    $sql = "ALTER TABLE delivery_records ADD COLUMN model_no VARCHAR(100)";
    
    if ($conn->query($sql)) {
        echo "✓ Column added successfully!\n";
        echo json_encode(['success' => true, 'message' => 'model_no column added']);
    } else {
        echo "✗ Error: " . $conn->error . "\n";
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo "Column already exists\n";
    echo json_encode(['success' => true, 'message' => 'Column already exists']);
}

if (method_exists($conn, 'close')) {
    @$conn->close();
}
?>
