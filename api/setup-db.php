<?php
/**
 * Database Setup Script
 * This script creates the database and tables if they don't exist
 */

header('Content-Type: application/json');

// Use the main database config which handles MySQL/SQLite fallback
require_once __DIR__ . '/../db_config.php';

// Check if connection is available (already established in db_config.php)
if (!$conn || $conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

// For MySQL connections, ensure the table exists with ALL required columns
if ($conn instanceof mysqli) {
    // Create delivery_records table if it doesn't exist (no UNIQUE constraint to allow re-imports)
    $sql_create_table = "CREATE TABLE IF NOT EXISTS `delivery_records` (
      `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `invoice_no` VARCHAR(50) NULL DEFAULT NULL,
      `serial_no` VARCHAR(100) NULL DEFAULT NULL,
      `delivery_month` VARCHAR(20) NOT NULL DEFAULT '',
      `delivery_day` INT(2) NOT NULL DEFAULT 0,
      `delivery_year` INT(4) NOT NULL DEFAULT 0,
      `delivery_date` DATE NULL DEFAULT NULL,
      `item_code` VARCHAR(50) NOT NULL DEFAULT '',
      `item_name` VARCHAR(255) NULL DEFAULT NULL,
      `company_name` VARCHAR(255) NOT NULL DEFAULT 'Andison Industrial',
      `quantity` INT(11) NOT NULL DEFAULT 0,
      `status` VARCHAR(50) NOT NULL DEFAULT 'Delivered',
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
        'delivery_date'  => "ALTER TABLE `delivery_records` ADD COLUMN `delivery_date` DATE NULL DEFAULT NULL AFTER `delivery_year`",
    ];
    foreach ($add_if_missing as $col => $sql) {
        if (!in_array($col, $existing)) {
            $conn->query($sql); // ignore error if already exists
        }
    }

    // Drop the old restrictive unique constraint if it exists
    $idxResult = $conn->query("SHOW INDEX FROM `delivery_records` WHERE Key_name = 'unique_delivery'");
    if ($idxResult && $idxResult->num_rows > 0) {
        $conn->query("ALTER TABLE `delivery_records` DROP INDEX `unique_delivery`");
    }
}
// SQLite tables are already created (with all columns) in db_config.php

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Database and tables setup successfully'
]);
?>
