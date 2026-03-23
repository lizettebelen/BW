<?php
require 'db_config.php';

echo "=== ALL TABLES IN DATABASE ===\n\n";

// Get all tables
if (is_object($conn) && method_exists($conn, 'query')) {
    // For SQLite
    $tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
    if ($tables) {
        while ($row = $tables->fetch_assoc()) {
            $tableName = $row['name'];
            
            // Get row count
            $count = $conn->query("SELECT COUNT(*) as cnt FROM $tableName");
            if ($count) {
                $cRow = $count->fetch_assoc();
                $cnt = $cRow['cnt'] ?? 0;
                echo "Table: $tableName - Records: $cnt\n";
            }
        }
    }
} else {
    // For MySQL
    $tables = $conn->query("SHOW TABLES");
    if ($tables) {
        while ($row = $tables->fetch_assoc()) {
            $tableName = $row['Tables_in_' . $conn->select_db];
            $count = $conn->query("SELECT COUNT(*) as cnt FROM $tableName");
            if ($count) {
                $cRow = $count->fetch_assoc();
                $cnt = $cRow['cnt'] ?? 0;
                echo "Table: $tableName - Records: $cnt\n";
            }
        }
    }
}

$conn->close();
?>
