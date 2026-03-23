<?php
// Add box_code column to delivery_records table if it doesn't exist
require_once __DIR__ . '/../db_config.php';

if (!$conn) {
    die("Database connection failed");
}

$is_mysql = get_class($conn) === 'mysqli';

echo "Database type: " . ($is_mysql ? "MySQL" : "SQLite") . "\n";

// Try to add column
if ($is_mysql) {
    // MySQL syntax
    $result = @$conn->query("SHOW COLUMNS FROM delivery_records LIKE 'box_code'");
    $exists = $result && $result->num_rows > 0;
} else {
    // SQLite syntax
    $result = @$conn->query("PRAGMA table_info(delivery_records)");
    $exists = false;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['name'] === 'box_code') {
                $exists = true;
                break;
            }
        }
    }
}

if (!$exists) {
    echo "Adding box_code column...\n";
    
    // For both MySQL and SQLite, simple ADD COLUMN without AFTER position
    $sql = "ALTER TABLE delivery_records ADD COLUMN box_code VARCHAR(50)";
    
    if ($conn->query($sql)) {
        echo "✓ Column added successfully!\n";
        echo json_encode(['success' => true, 'message' => 'box_code column added']);
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
