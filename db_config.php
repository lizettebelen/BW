<?php
// ── Database Configuration ────────────────────────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bw_gas_detector';

// Try MySQL first; fall back to SQLite when MySQL is unavailable
$conn = null;

// Enable MySQLi exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Try MySQL connection with proper exception handling
try {
    $mysql = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $mysql->set_charset('utf8mb4');
    $conn = $mysql;
} catch (Exception $e) {
    // Fall back to SQLite (no MySQL required)
    require_once __DIR__ . '/db_sqlite_compat.php';
    $sqlite_file = __DIR__ . '/bw_gas_detector.sqlite';
    $conn = new SqliteConn($sqlite_file);

    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode([
            'success'  => false,
            'message'  => 'Database connection failed: ' . $conn->connect_error
        ]));
    }

    // Bootstrap core tables the first time
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        name     VARCHAR(255) NOT NULL,
        email    VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS delivery_records (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_no     VARCHAR(50),
        serial_no      VARCHAR(100),
        delivery_month VARCHAR(20),
        delivery_day   INTEGER,
        delivery_year  INTEGER      NOT NULL DEFAULT 0,
        delivery_date  DATE,
        item_code      VARCHAR(50)  NOT NULL,
        item_name      VARCHAR(255),
        company_name   VARCHAR(255),
        quantity       INTEGER      NOT NULL DEFAULT 0,
        status         VARCHAR(50)  NOT NULL DEFAULT 'Delivered',
        notes          TEXT,
        created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )");

    // Upgrade: add columns that may be missing from older SQLite databases
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN invoice_no VARCHAR(50)");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN serial_no VARCHAR(100)");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN delivery_year INTEGER NOT NULL DEFAULT 0");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN delivery_date DATE");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN groupings VARCHAR(50)");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN uom VARCHAR(50)");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN sold_to_month VARCHAR(20)");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN sold_to_day INTEGER");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN box_code VARCHAR(50)");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN model_no VARCHAR(100)");
    @$conn->query("ALTER TABLE delivery_records ADD COLUMN description TEXT");
    
    // Add 2FA columns for users table
    @$conn->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(32) DEFAULT NULL");
    @$conn->query("ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER DEFAULT 0");
}

// Apply lightweight schema upgrades for Orders module on both MySQL and SQLite.
function safeSchemaUpgrade($conn, $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        // Ignore when column already exists or driver cannot apply identical migration.
    }
}

safeSchemaUpgrade($conn, "ALTER TABLE delivery_records ADD COLUMN order_customer VARCHAR(255)");
safeSchemaUpgrade($conn, "ALTER TABLE delivery_records ADD COLUMN order_date DATE");
safeSchemaUpgrade($conn, "ALTER TABLE delivery_records ADD COLUMN unit_price DECIMAL(12,2) DEFAULT 0");
safeSchemaUpgrade($conn, "ALTER TABLE delivery_records ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0");
safeSchemaUpgrade($conn, "ALTER TABLE delivery_records ADD COLUMN po_number VARCHAR(100)");
safeSchemaUpgrade($conn, "ALTER TABLE delivery_records ADD COLUMN po_status VARCHAR(50) DEFAULT 'No PO'");
?>
