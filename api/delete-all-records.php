<?php
require_once '../db_config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

function tableExists($conn, $tableName) {
    if ($conn instanceof mysqli) {
        $safe = $conn->real_escape_string($tableName);
        $check = $conn->query("SHOW TABLES LIKE '$safe'");
        return ($check && $check->num_rows > 0);
    }

    $safe = $conn->real_escape_string($tableName);
    $check = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$safe' LIMIT 1");
    return ($check && $check->num_rows > 0);
}

function countTableRows($conn, $tableName) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM $tableName");
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return isset($row['total']) ? intval($row['total']) : 0;
}

try {
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Purge only business/data tables. Keep auth/config tables like users/settings.
    $targetTables = [
        'warranty_replacements',
        'delivery_records',
        'delivery_highlight_memory',
        'inventory',
        'stock_additions',
        'orders',
        'order_items',
        'security_alerts',
        'login_attempts'
    ];

    $deletedTotals = [];
    $existingTargets = [];

    foreach ($targetTables as $tableName) {
        if (tableExists($conn, $tableName)) {
            $existingTargets[] = $tableName;
        }
    }

    if (empty($existingTargets)) {
        echo json_encode([
            'success' => true,
            'message' => 'No data tables found to clear',
            'deleted_count' => 0,
            'deleted_tables' => []
        ]);
        exit;
    }

    $conn->begin_transaction();
    try {
        foreach ($existingTargets as $tableName) {
            $deletedTotals[$tableName] = countTableRows($conn, $tableName);
            if ($conn->query("DELETE FROM $tableName") === false) {
                throw new Exception("Failed to clear table: $tableName");
            }
        }

        // Reset SQLite auto-increment counters for cleaned tables.
        if (!($conn instanceof mysqli) && tableExists($conn, 'sqlite_sequence')) {
            foreach ($existingTargets as $tableName) {
                @$conn->query("DELETE FROM sqlite_sequence WHERE name = '$tableName'");
            }
        }

        $conn->commit();
    } catch (Exception $inner) {
        $conn->rollback();
        throw $inner;
    }

    $totalDeleted = array_sum($deletedTotals);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'All selected system data cleared successfully',
        'deleted_count' => $totalDeleted,
        'deleted_tables' => $deletedTotals
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if ($conn && method_exists($conn, 'close')) {
    $conn->close();
}
?>

