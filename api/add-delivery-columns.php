<?php
/**
 * Add missing columns to delivery_records table:
 * - uom (Unit of Measure)
 * - groupings
 * - sold_to_month
 * - sold_to_day
 */

require_once __DIR__ . '/../db_config.php';

// Detect if MySQL or SQLite
$isMysql = ($conn instanceof mysqli);

$columns_to_add = [
    'uom'            => "ALTER TABLE delivery_records ADD COLUMN uom VARCHAR(50) DEFAULT NULL",
    'groupings'      => "ALTER TABLE delivery_records ADD COLUMN groupings VARCHAR(50) DEFAULT NULL",
    'sold_to_month'  => "ALTER TABLE delivery_records ADD COLUMN sold_to_month VARCHAR(20) DEFAULT NULL",
    'sold_to_day'    => "ALTER TABLE delivery_records ADD COLUMN sold_to_day INTEGER DEFAULT NULL"
];

$results = [];

foreach ($columns_to_add as $column => $sql) {
    // Check if column exists
    if ($isMysql) {
        $check = $conn->query("SHOW COLUMNS FROM delivery_records LIKE '$column'");
        $exists = ($check && $check->num_rows > 0);
    } else {
        // SQLite - use PRAGMA
        $check = $conn->query("PRAGMA table_info(delivery_records)");
        $exists = false;
        if ($check) {
            while ($row = $check->fetch_assoc()) {
                if (strtolower($row['name']) === strtolower($column)) {
                    $exists = true;
                    break;
                }
            }
        }
    }
    
    if ($exists) {
        $results[$column] = "Column already exists";
    } else {
        // Add the column
        try {
            if ($conn->query($sql)) {
                $results[$column] = "Added successfully";
            } else {
                $results[$column] = "Error: " . ($conn->error ?? 'Unknown error');
            }
        } catch (Exception $e) {
            $results[$column] = "Error: " . $e->getMessage();
        }
    }
}

// Show results
echo "=== Column Migration Results ===\n";
foreach ($results as $col => $result) {
    echo "$col: $result\n";
}

echo "\n=== Current Table Structure ===\n";
if ($isMysql) {
    $describe = $conn->query("DESCRIBE delivery_records");
    if ($describe) {
        while ($row = $describe->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    }
} else {
    $describe = $conn->query("PRAGMA table_info(delivery_records)");
    if ($describe) {
        while ($row = $describe->fetch_assoc()) {
            echo $row['name'] . " - " . $row['type'] . "\n";
        }
    }
}

echo "\nMigration complete.\n";
?>
