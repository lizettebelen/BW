-- BW Gas Detector Database Schema
-- This file contains the required database table structure for the Excel import feature

-- Database: bw_gas_detector
CREATE DATABASE IF NOT EXISTS `bw_gas_detector` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bw_gas_detector`;

-- Table: delivery_records

CREATE TABLE IF NOT EXISTS `delivery_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `delivery_month` VARCHAR(20) NOT NULL COMMENT 'Month of delivery (e.g., January, February)',
  `delivery_day` INT(2) NOT NULL COMMENT 'Day of delivery (1-31)',
  `delivery_year` INT(4) DEFAULT NULL COMMENT 'Year of delivery (e.g., 2025, 2026)',
  `record_date` DATE DEFAULT NULL COMMENT 'Original Excel Date column',
  `delivery_date` DATE DEFAULT NULL COMMENT 'Date Delivered column',
  `item_code` VARCHAR(50) NOT NULL COMMENT 'Item/Product code (e.g., MCX3-BC1)',
  `item_name` VARCHAR(255) NOT NULL COMMENT 'Full item/product name',
  `company_name` VARCHAR(255) NOT NULL COMMENT 'Company/Client name',
  `sold_to` VARCHAR(255) DEFAULT NULL COMMENT 'Sold To company from uploaded sheets',
  `quantity` INT(11) NOT NULL DEFAULT 0 COMMENT 'Number of units delivered',
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending' COMMENT 'Delivery status (Pending, In Transit, Delivered, Cancelled)',
  `highlight_color` VARCHAR(20) DEFAULT NULL COMMENT 'Imported Excel highlight color',
  `cell_styles` LONGTEXT DEFAULT NULL COMMENT 'Per-cell imported Excel colors as JSON',
  `notes` TEXT COMMENT 'Additional notes or comments about the delivery',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last update timestamp',
  
  -- Indexes for better query performance
  KEY `idx_delivery_month` (`delivery_month`),
  KEY `idx_delivery_day` (`delivery_day`),
  KEY `idx_delivery_year` (`delivery_year`),
  KEY `idx_item_code` (`item_code`),
  KEY `idx_company_name` (`company_name`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  
  -- Unique constraint to prevent duplicate entries
  UNIQUE KEY `unique_delivery` (`delivery_month`, `delivery_day`, `delivery_year`, `item_code`, `company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores delivery records for BW Gas Detector products';

-- Logical views for phpMyAdmin visibility by module/category
CREATE OR REPLACE VIEW `vw_inventory` AS
SELECT *
FROM `delivery_records`
WHERE `company_name` = 'Stock Addition';

CREATE OR REPLACE VIEW `vw_purchase_orders` AS
SELECT *
FROM `delivery_records`
WHERE `company_name` = 'Orders';

CREATE OR REPLACE VIEW `vw_andison_manila` AS
SELECT *
FROM `delivery_records`
WHERE LOWER(TRIM(COALESCE(`company_name`, ''))) IN ('andison manila', 'to andison manila')
   OR LOWER(TRIM(COALESCE(`sold_to`, ''))) IN ('andison manila', 'to andison manila');

CREATE OR REPLACE VIEW `vw_sales` AS
SELECT *
FROM `delivery_records`
WHERE `quantity` > 0
  AND `company_name` NOT IN ('Stock Addition', 'Orders')
  AND LOWER(TRIM(COALESCE(`company_name`, ''))) NOT IN ('andison manila', 'to andison manila');

CREATE OR REPLACE VIEW `vw_inquiry` AS
SELECT *
FROM `delivery_records`
WHERE `company_name` = 'Orders'
  AND (COALESCE(`po_status`, '') = '' OR `po_status` IN ('No PO', 'Pending'));

CREATE OR REPLACE VIEW `vw_delivery` AS
SELECT *
FROM `delivery_records`
WHERE `company_name` != 'Orders'
  AND LOWER(TRIM(COALESCE(`company_name`, ''))) NOT IN ('andison manila', 'to andison manila')
  AND LOWER(TRIM(COALESCE(`sold_to`, ''))) NOT IN ('andison manila', 'to andison manila');

CREATE OR REPLACE VIEW `vw_datasets` AS
SELECT
  COALESCE(NULLIF(TRIM(`dataset_name`), ''), 'UNASSIGNED') AS dataset_name,
  COUNT(*) AS total_records,
  COALESCE(SUM(`quantity`), 0) AS total_quantity,
  MIN(`created_at`) AS first_record_at,
  MAX(`created_at`) AS last_record_at
FROM `delivery_records`
GROUP BY COALESCE(NULLIF(TRIM(`dataset_name`), ''), 'UNASSIGNED');

-- Alias views that match module names in phpMyAdmin list
CREATE OR REPLACE VIEW `inventory` AS
SELECT * FROM `vw_inventory`;

CREATE OR REPLACE VIEW `purchase_order` AS
SELECT * FROM `vw_purchase_orders`;

CREATE OR REPLACE VIEW `andison_manila` AS
SELECT * FROM `vw_andison_manila`;

CREATE OR REPLACE VIEW `sales` AS
SELECT * FROM `vw_sales`;

CREATE OR REPLACE VIEW `inquiry` AS
SELECT * FROM `vw_inquiry`;

CREATE OR REPLACE VIEW `delivery` AS
SELECT * FROM `vw_delivery`;

CREATE OR REPLACE VIEW `datasets` AS
SELECT * FROM `vw_datasets`;

CREATE OR REPLACE VIEW `warranty` AS
SELECT * FROM `warranty_replacements`;

-- Insert sample data (optional)
INSERT INTO delivery_records (delivery_month, delivery_day, item_code, item_name, company_name, sold_to, quantity, status, highlight_color, cell_styles, notes)
VALUES 
('January', 1, 'MCX3-BC1', 'BW Gas Detector - Model 3 BC1', 'Addison Industrial', 'Addison Industrial', 10, 'Delivered', '#FFF2CC', '{"company_name":"#FFF2CC"}', 'Sample delivery'),
('January', 5, 'MCX3-FC1', 'BW Gas Detector - Model 3 FC1', 'Tech Solutions Ltd', 'Tech Solutions Ltd', 15, 'Delivered', '#D9EAF7', '{"company_name":"#D9EAF7"}', NULL),
('February', 3, 'MCX3-MPCB', 'BW Gas Detector - Model 3 MPCB', 'Global Industries', 'Global Industries', 8, 'In Transit', NULL, NULL, 'Expected delivery by Feb 10')
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP;
