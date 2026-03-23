-- BW Gas Detector Database Schema
-- This file contains the required database table structure for the Excel import feature

-- Database: bw_gas_detector
-- Table: delivery_records

CREATE TABLE IF NOT EXISTS `delivery_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `delivery_month` VARCHAR(20) NOT NULL COMMENT 'Month of delivery (e.g., January, February)',
  `delivery_day` INT(2) NOT NULL COMMENT 'Day of delivery (1-31)',
  `delivery_year` INT(4) DEFAULT NULL COMMENT 'Year of delivery (e.g., 2025, 2026)',
  `item_code` VARCHAR(50) NOT NULL COMMENT 'Item/Product code (e.g., MCX3-BC1)',
  `item_name` VARCHAR(255) NOT NULL COMMENT 'Full item/product name',
  `company_name` VARCHAR(255) NOT NULL COMMENT 'Company/Client name',
  `quantity` INT(11) NOT NULL DEFAULT 0 COMMENT 'Number of units delivered',
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending' COMMENT 'Delivery status (Pending, In Transit, Delivered, Cancelled)',
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

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `bw_gas_detector` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `bw_gas_detector`;

-- Insert sample data (optional)
INSERT INTO delivery_records (delivery_month, delivery_day, item_code, item_name, company_name, quantity, status, notes)
VALUES 
('January', 1, 'MCX3-BC1', 'BW Gas Detector - Model 3 BC1', 'Addison Industrial', 10, 'Delivered', 'Sample delivery'),
('January', 5, 'MCX3-FC1', 'BW Gas Detector - Model 3 FC1', 'Tech Solutions Ltd', 15, 'Delivered', NULL),
('February', 3, 'MCX3-MPCB', 'BW Gas Detector - Model 3 MPCB', 'Global Industries', 8, 'In Transit', 'Expected delivery by Feb 10')
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP;

-- Display table structure
DESCRIBE delivery_records;
