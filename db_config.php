<?php
// ── Database Configuration ────────────────────────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bw_gas_detector';

if (!defined('APP_REQUIRED_DB_NAME')) {
    define('APP_REQUIRED_DB_NAME', 'bw_gas_detector');
}

$conn = null;

// Use MySQL so the database is managed in phpMyAdmin.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

    $activeDbResult = $conn->query('SELECT DATABASE() AS db_name');
    $activeDbRow = $activeDbResult ? $activeDbResult->fetch_assoc() : null;
    $activeDbName = isset($activeDbRow['db_name']) ? (string) $activeDbRow['db_name'] : '';

    if ($activeDbName !== APP_REQUIRED_DB_NAME) {
        throw new RuntimeException('Connected database mismatch. Expected ' . APP_REQUIRED_DB_NAME . ', got ' . ($activeDbName ?: 'NULL') . '.');
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $currentUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    if ($currentUserId && $currentUserId > 0) {
        $conn->query('SET @app_user_id = ' . $currentUserId);
    } else {
        $conn->query('SET @app_user_id = NULL');
    }

    function safeSchemaUpgrade(mysqli $conn, string $sql): void {
        try {
            $conn->query($sql);
        } catch (Exception $e) {
            // Ignore when the column already exists or the migration is not needed.
        }
    }

    $recordsTable = 'delivery_records';

    // Keep the MySQL table aligned with the fields used across the app.
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN invoice_no VARCHAR(100) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN serial_no VARCHAR(150) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN delivery_month VARCHAR(20) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN delivery_day INT DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN delivery_year INT DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN record_date DATE DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN delivery_date DATE DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN order_customer VARCHAR(255) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN transferred_to VARCHAR(255) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN order_date DATE DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN box_code VARCHAR(50) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN model_no VARCHAR(100) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN uom VARCHAR(50) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN sold_to_month VARCHAR(20) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN sold_to_day INT DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN unit_price DECIMAL(12,2) DEFAULT 0");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN po_number VARCHAR(100) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN po_status VARCHAR(50) DEFAULT 'No PO'");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN groupings VARCHAR(50) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN dataset_name VARCHAR(50) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD COLUMN owner_user_id INT DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE {$recordsTable} ADD INDEX idx_owner_user_id (owner_user_id)");

    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        two_factor_secret VARCHAR(32) DEFAULT NULL,
        two_factor_enabled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    safeSchemaUpgrade($conn, "ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(32) DEFAULT NULL");
    safeSchemaUpgrade($conn, "ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0");
        safeSchemaUpgrade($conn, "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(500) DEFAULT NULL");

    // Keep owner field sticky for inserts made through the scoped view.
    $conn->query('DROP TRIGGER IF EXISTS trg_delivery_records_set_owner');
    $conn->query("CREATE TRIGGER trg_delivery_records_set_owner
        BEFORE INSERT ON {$recordsTable}
        FOR EACH ROW
        SET NEW.owner_user_id = COALESCE(NEW.owner_user_id, @app_user_id)");
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'MySQL bw_gas_detector connection failed. Create/import the bw_gas_detector database in phpMyAdmin first.',
        'error'   => $e->getMessage(),
    ]));
}
?>
