<?php
/**
 * Database Setup Script
 * This script creates the database and tables if they don't exist
 */

header('Content-Type: application/json');

// Use the main MySQL database config (bw_gas_detector)
require_once __DIR__ . '/../db_config.php';

// Check if connection is available (already established in db_config.php)
if (!$conn || $conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

if (!($conn instanceof mysqli)) {
    die(json_encode([
        'success' => false,
        'message' => 'MySQL connection required. Active database must be bw_gas_detector.'
    ]));
}

// Ensure the table exists with ALL required columns
if ($conn instanceof mysqli) {
    // Create delivery_records table if it doesn't exist (no UNIQUE constraint to allow re-imports)
    $sql_create_table = "CREATE TABLE IF NOT EXISTS `delivery_records` (
      `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `invoice_no` VARCHAR(50) NULL DEFAULT NULL,
      `serial_no` VARCHAR(100) NULL DEFAULT NULL,
      `delivery_month` VARCHAR(20) NOT NULL DEFAULT '',
      `delivery_day` INT(2) NOT NULL DEFAULT 0,
      `delivery_year` INT(4) NOT NULL DEFAULT 0,
    `record_date` DATE NULL DEFAULT NULL,
      `delivery_date` DATE NULL DEFAULT NULL,
      `item_code` VARCHAR(50) NOT NULL DEFAULT '',
      `item_name` VARCHAR(255) NULL DEFAULT NULL,
      `company_name` VARCHAR(255) NOT NULL DEFAULT 'Andison Industrial',
    `sold_to` VARCHAR(255) NULL DEFAULT NULL,
      `quantity` INT(11) NOT NULL DEFAULT 0,
      `status` VARCHAR(50) NOT NULL DEFAULT 'Delivered',
    `highlight_color` VARCHAR(20) NULL DEFAULT NULL,
    `cell_styles` LONGTEXT NULL DEFAULT NULL,
      `notes` TEXT NULL DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_delivery_month` (`delivery_month`),
      KEY `idx_delivery_year` (`delivery_year`),
      KEY `idx_item_code` (`item_code`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql_create_table)) {
        die(json_encode([
            'success' => false,
            'message' => 'Error creating table: ' . $conn->error
        ]));
    }

    // Add any missing columns to existing tables (MySQL 5.7+ compatible)
    $existing = [];
    $cols_result = $conn->query("SHOW COLUMNS FROM `delivery_records`");
    if ($cols_result) {
        while ($r = $cols_result->fetch_assoc()) {
            $existing[] = $r['Field'];
        }
    }
    $add_if_missing = [
        'invoice_no'     => "ALTER TABLE `delivery_records` ADD COLUMN `invoice_no` VARCHAR(50) NULL DEFAULT NULL AFTER `id`",
        'serial_no'      => "ALTER TABLE `delivery_records` ADD COLUMN `serial_no` VARCHAR(100) NULL DEFAULT NULL AFTER `invoice_no`",
        'delivery_year'  => "ALTER TABLE `delivery_records` ADD COLUMN `delivery_year` INT(4) NOT NULL DEFAULT 0 AFTER `delivery_day`",
        'record_date'    => "ALTER TABLE `delivery_records` ADD COLUMN `record_date` DATE NULL DEFAULT NULL AFTER `delivery_year`",
        'delivery_date'  => "ALTER TABLE `delivery_records` ADD COLUMN `delivery_date` DATE NULL DEFAULT NULL AFTER `delivery_year`",
        'sold_to'        => "ALTER TABLE `delivery_records` ADD COLUMN `sold_to` VARCHAR(255) NULL DEFAULT NULL AFTER `company_name`",
        'highlight_color' => "ALTER TABLE `delivery_records` ADD COLUMN `highlight_color` VARCHAR(20) NULL DEFAULT NULL AFTER `status`",
        'cell_styles'    => "ALTER TABLE `delivery_records` ADD COLUMN `cell_styles` LONGTEXT NULL DEFAULT NULL AFTER `highlight_color`",
    ];
    foreach ($add_if_missing as $col => $sql) {
        if (!in_array($col, $existing)) {
            $conn->query($sql); // ignore error if already exists
        }
    }

    // Create logical category views so they appear separately in phpMyAdmin.
    $conn->query("CREATE OR REPLACE VIEW `vw_inventory` AS
        SELECT *
        FROM `delivery_records`
        WHERE `company_name` = 'Stock Addition'");

    $conn->query("CREATE OR REPLACE VIEW `vw_purchase_orders` AS
        SELECT *
        FROM `delivery_records`
        WHERE `company_name` = 'Orders'");

    $conn->query("CREATE OR REPLACE VIEW `vw_andison_manila` AS
        SELECT *
        FROM `delivery_records`
        WHERE LOWER(TRIM(COALESCE(`company_name`, ''))) IN ('andison manila', 'to andison manila')
           OR LOWER(TRIM(COALESCE(`sold_to`, ''))) IN ('andison manila', 'to andison manila')");

    $conn->query("CREATE OR REPLACE VIEW `vw_sales` AS
        SELECT *
        FROM `delivery_records`
        WHERE `quantity` > 0
          AND `company_name` NOT IN ('Stock Addition', 'Orders')
          AND LOWER(TRIM(COALESCE(`company_name`, ''))) NOT IN ('andison manila', 'to andison manila')");

    $conn->query("CREATE OR REPLACE VIEW `vw_inquiry` AS
        SELECT *
        FROM `delivery_records`
        WHERE `company_name` = 'Orders'
          AND (COALESCE(`po_status`, '') = '' OR `po_status` IN ('No PO', 'Pending'))");

    $conn->query("CREATE OR REPLACE VIEW `vw_delivery` AS
        SELECT *
        FROM `delivery_records`
        WHERE `company_name` != 'Orders'
          AND LOWER(TRIM(COALESCE(`company_name`, ''))) NOT IN ('andison manila', 'to andison manila')
          AND LOWER(TRIM(COALESCE(`sold_to`, ''))) NOT IN ('andison manila', 'to andison manila')");

    $conn->query("CREATE OR REPLACE VIEW `vw_datasets` AS
        SELECT
            COALESCE(NULLIF(TRIM(`dataset_name`), ''), 'UNASSIGNED') AS dataset_name,
            COUNT(*) AS total_records,
            COALESCE(SUM(`quantity`), 0) AS total_quantity,
            MIN(`created_at`) AS first_record_at,
            MAX(`created_at`) AS last_record_at
        FROM `delivery_records`
        GROUP BY COALESCE(NULLIF(TRIM(`dataset_name`), ''), 'UNASSIGNED')");

    // Alias views using module names expected in phpMyAdmin list.
    $conn->query("CREATE OR REPLACE VIEW `inventory` AS
        SELECT * FROM `vw_inventory`");

    $conn->query("CREATE OR REPLACE VIEW `purchase_order` AS
        SELECT * FROM `vw_purchase_orders`");

    $conn->query("CREATE OR REPLACE VIEW `andison_manila` AS
        SELECT * FROM `vw_andison_manila`");

    $conn->query("CREATE OR REPLACE VIEW `sales` AS
        SELECT * FROM `vw_sales`");

    $conn->query("CREATE OR REPLACE VIEW `inquiry` AS
        SELECT * FROM `vw_inquiry`");

    $conn->query("CREATE OR REPLACE VIEW `delivery` AS
        SELECT * FROM `vw_delivery`");

    $conn->query("CREATE OR REPLACE VIEW `datasets` AS
        SELECT * FROM `vw_datasets`");

    // Warranty table is maintained separately; expose it via module-friendly alias.
    $hasWarrantyTable = $conn->query("SHOW TABLES LIKE 'warranty_replacements'");
    if ($hasWarrantyTable && $hasWarrantyTable->num_rows > 0) {
        $conn->query("CREATE OR REPLACE VIEW `warranty` AS
            SELECT * FROM `warranty_replacements`");
    }

    // Drop the old restrictive unique constraint if it exists
    $idxResult = $conn->query("SHOW INDEX FROM `delivery_records` WHERE Key_name = 'unique_delivery'");
    if ($idxResult && $idxResult->num_rows > 0) {
        $conn->query("ALTER TABLE `delivery_records` DROP INDEX `unique_delivery`");
    }
}
// Return success
echo json_encode([
    'success' => true,
    'message' => 'Database and tables setup successfully'
]);
?>
