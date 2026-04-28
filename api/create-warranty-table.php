<?php
/**
 * Create Warranty Replacements Table
 * 
 * This script creates the warranty_replacements table if it doesn't exist.
 * Called automatically during initial setup or can be run manually.
 */

require_once __DIR__ . '/../db_config.php';

if (!$conn) {
    die('Database connection failed');
}

if (!($conn instanceof mysqli)) {
    die('MySQL connection required');
}

try {
    $sql = "CREATE TABLE IF NOT EXISTS `warranty_replacements` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `delivery_record_id` INT(11) DEFAULT NULL COMMENT 'Reference to original delivery_records.id',
        `invoice_no` VARCHAR(100) DEFAULT NULL COMMENT 'Invoice number from source row',
        `delivery_month` VARCHAR(20) COMMENT 'Month of delivery',
        `delivery_day` INT(2) COMMENT 'Day of delivery',
        `delivery_year` INT(4) COMMENT 'Year of delivery',
        `record_date` DATE DEFAULT NULL,
        `delivery_date` DATE DEFAULT NULL,
        `item_code` VARCHAR(50) COMMENT 'Item/Product code',
        `item_name` VARCHAR(255) COMMENT 'Full item/product name',
        `company_name` VARCHAR(255) COMMENT 'Company/Client name',
        `sold_to` VARCHAR(255) DEFAULT NULL,
        `quantity` INT(11) NOT NULL DEFAULT 0,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Warranty Pending' COMMENT 'Warranty status',
        `uom` VARCHAR(20) DEFAULT NULL COMMENT 'Unit of measurement',
        `serial_no` VARCHAR(150) DEFAULT NULL,
        `transferred_to` VARCHAR(255) DEFAULT NULL COMMENT 'Where item is transferred',
        `notes` TEXT DEFAULT NULL,
        `warranty_flag` TINYINT(1) DEFAULT 1 COMMENT 'Flagged as warranty (1=yes, 0=no)',
        `warranty_date` DATE DEFAULT NULL COMMENT 'Date flagged as warranty',
        `red_text_detected` TINYINT(1) DEFAULT 1 COMMENT 'Row had red text during import',
        `dataset_name` VARCHAR(50) DEFAULT NULL,
        `highlight_color` VARCHAR(20) DEFAULT NULL,
        `cell_styles` LONGTEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        -- Indexes for better query performance
        KEY `idx_delivery_month` (`delivery_month`),
        KEY `idx_delivery_day` (`delivery_day`),
        KEY `idx_delivery_year` (`delivery_year`),
        KEY `idx_item_code` (`item_code`),
        KEY `idx_company_name` (`company_name`),
        KEY `idx_warranty_flag` (`warranty_flag`),
        KEY `idx_warranty_date` (`warranty_date`),
        KEY `idx_delivery_record_id` (`delivery_record_id`),
        KEY `idx_created_at` (`created_at`),

        -- Foreign key (optional, can be enabled if needed)
        CONSTRAINT `fk_warranty_delivery_record` FOREIGN KEY (`delivery_record_id`)
            REFERENCES `delivery_records` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warranty replacement records linked to delivery_records'";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('MySQL Error: ' . $conn->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'warranty_replacements table created or already exists'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error creating warranty_replacements table: ' . $e->getMessage()
    ]);
}

exit;
?>
