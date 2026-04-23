<?php
require __DIR__ . '/../../db_config.php';

echo "=== ALL TABLES IN DATABASE ===\n\n";

// Get all tables from MySQL/phpMyAdmin
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    while ($row = $tables->fetch_array()) {
        $tableName = $row[0];
        $count = $conn->query("SELECT COUNT(*) as cnt FROM `{$tableName}`");
        if ($count) {
            $cRow = $count->fetch_assoc();
            $cnt = $cRow['cnt'] ?? 0;
            echo "Table: $tableName - Records: $cnt\n";
        }
    }
}

$conn->close();
?>
