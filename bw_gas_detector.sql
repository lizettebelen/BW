-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2026 at 07:33 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bw_gas_detector`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `andison_manila`
-- (See below for the actual view)
--
CREATE TABLE `andison_manila` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `datasets`
-- (See below for the actual view)
--
CREATE TABLE `datasets` (
`dataset_name` varchar(50)
,`total_records` bigint(21)
,`total_quantity` decimal(32,0)
,`first_record_at` timestamp
,`last_record_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `delivery`
-- (See below for the actual view)
--
CREATE TABLE `delivery` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_highlight_memory`
--

CREATE TABLE `delivery_highlight_memory` (
  `id` int(11) NOT NULL,
  `dataset_name` varchar(50) NOT NULL,
  `invoice_no` varchar(100) DEFAULT '',
  `item_code` varchar(100) DEFAULT '',
  `serial_no` varchar(150) DEFAULT '',
  `sold_to` varchar(255) DEFAULT '',
  `delivery_date` varchar(50) DEFAULT '',
  `highlight_color` varchar(20) DEFAULT NULL,
  `cell_styles` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_records`
--

CREATE TABLE `delivery_records` (
  `id` int(11) NOT NULL,
  `delivery_month` varchar(20) NOT NULL COMMENT 'Month of delivery (e.g., January, February)',
  `delivery_day` int(2) NOT NULL COMMENT 'Day of delivery (1-31)',
  `delivery_year` int(4) DEFAULT NULL COMMENT 'Year of delivery (e.g., 2025, 2026)',
  `record_date` date DEFAULT NULL COMMENT 'Original Excel Date column',
  `delivery_date` date DEFAULT NULL COMMENT 'Date Delivered column',
  `item_code` varchar(50) NOT NULL COMMENT 'Item/Product code (e.g., MCX3-BC1)',
  `item_name` varchar(255) NOT NULL COMMENT 'Full item/product name',
  `company_name` varchar(255) NOT NULL COMMENT 'Company/Client name',
  `sold_to` varchar(255) DEFAULT NULL COMMENT 'Sold To company from uploaded sheets',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of units delivered',
  `status` varchar(50) NOT NULL DEFAULT 'Pending' COMMENT 'Delivery status (Pending, In Transit, Delivered, Cancelled)',
  `highlight_color` varchar(20) DEFAULT NULL COMMENT 'Imported Excel highlight color',
  `cell_styles` longtext DEFAULT NULL COMMENT 'Per-cell imported Excel colors as JSON',
  `notes` text DEFAULT NULL COMMENT 'Additional notes or comments about the delivery',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record last update timestamp',
  `dataset_name` varchar(50) DEFAULT NULL,
  `order_customer` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `po_number` varchar(100) DEFAULT NULL,
  `po_status` varchar(50) DEFAULT 'No PO',
  `invoice_no` varchar(100) DEFAULT NULL,
  `serial_no` varchar(150) DEFAULT NULL,
  `groupings` varchar(50) DEFAULT NULL,
  `transferred_to` varchar(255) DEFAULT NULL,
  `box_code` varchar(50) DEFAULT NULL,
  `model_no` varchar(100) DEFAULT NULL,
  `uom` varchar(50) DEFAULT NULL,
  `sold_to_month` varchar(20) DEFAULT NULL,
  `sold_to_day` int(11) DEFAULT NULL,
  `box` varchar(255) DEFAULT NULL,
  `items` varchar(255) DEFAULT NULL,
  `inventory` varchar(255) DEFAULT NULL,
  `owner_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores delivery records for BW Gas Detector products';

--
-- Triggers `delivery_records`
--
DELIMITER $$
CREATE TRIGGER `trg_delivery_records_set_owner` BEFORE INSERT ON `delivery_records` FOR EACH ROW SET NEW.owner_user_id = COALESCE(NEW.owner_user_id, @app_user_id)
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `inquiry`
-- (See below for the actual view)
--
CREATE TABLE `inquiry` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `inventory`
-- (See below for the actual view)
--
CREATE TABLE `inventory` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `purchase_order`
-- (See below for the actual view)
--
CREATE TABLE `purchase_order` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `sales`
-- (See below for the actual view)
--
CREATE TABLE `sales` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` varchar(32) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_picture` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `settings` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_andison_manila`
-- (See below for the actual view)
--
CREATE TABLE `vw_andison_manila` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_datasets`
-- (See below for the actual view)
--
CREATE TABLE `vw_datasets` (
`dataset_name` varchar(50)
,`total_records` bigint(21)
,`total_quantity` decimal(32,0)
,`first_record_at` timestamp
,`last_record_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_delivery`
-- (See below for the actual view)
--
CREATE TABLE `vw_delivery` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_inquiry`
-- (See below for the actual view)
--
CREATE TABLE `vw_inquiry` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_inventory`
-- (See below for the actual view)
--
CREATE TABLE `vw_inventory` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_purchase_orders`
-- (See below for the actual view)
--
CREATE TABLE `vw_purchase_orders` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_sales`
-- (See below for the actual view)
--
CREATE TABLE `vw_sales` (
`id` int(11)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`dataset_name` varchar(50)
,`order_customer` varchar(255)
,`order_date` date
,`unit_price` decimal(12,2)
,`total_amount` decimal(12,2)
,`po_number` varchar(100)
,`po_status` varchar(50)
,`invoice_no` varchar(100)
,`serial_no` varchar(150)
,`groupings` varchar(50)
,`transferred_to` varchar(255)
,`box_code` varchar(50)
,`model_no` varchar(100)
,`uom` varchar(50)
,`sold_to_month` varchar(20)
,`sold_to_day` int(11)
,`box` varchar(255)
,`items` varchar(255)
,`inventory` varchar(255)
,`owner_user_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `warranty`
-- (See below for the actual view)
--
CREATE TABLE `warranty` (
`id` int(11)
,`delivery_record_id` int(11)
,`invoice_no` varchar(100)
,`delivery_month` varchar(20)
,`delivery_day` int(2)
,`delivery_year` int(4)
,`record_date` date
,`delivery_date` date
,`item_code` varchar(50)
,`item_name` varchar(255)
,`company_name` varchar(255)
,`sold_to` varchar(255)
,`quantity` int(11)
,`status` varchar(50)
,`uom` varchar(20)
,`serial_no` varchar(150)
,`transferred_to` varchar(255)
,`notes` text
,`warranty_flag` tinyint(1)
,`warranty_date` date
,`red_text_detected` tinyint(1)
,`dataset_name` varchar(50)
,`highlight_color` varchar(20)
,`cell_styles` longtext
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `warranty_replacements`
--

CREATE TABLE `warranty_replacements` (
  `id` int(11) NOT NULL,
  `delivery_record_id` int(11) DEFAULT NULL COMMENT 'Reference to original delivery_records.id',
  `invoice_no` varchar(100) DEFAULT NULL COMMENT 'Invoice number from source row',
  `delivery_month` varchar(20) DEFAULT NULL COMMENT 'Month of delivery',
  `delivery_day` int(2) DEFAULT NULL COMMENT 'Day of delivery',
  `delivery_year` int(4) DEFAULT NULL COMMENT 'Year of delivery',
  `record_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `item_code` varchar(50) DEFAULT NULL COMMENT 'Item/Product code',
  `item_name` varchar(255) DEFAULT NULL COMMENT 'Full item/product name',
  `company_name` varchar(255) DEFAULT NULL COMMENT 'Company/Client name',
  `sold_to` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `status` varchar(50) NOT NULL DEFAULT 'Warranty Pending' COMMENT 'Warranty status',
  `uom` varchar(20) DEFAULT NULL COMMENT 'Unit of measurement',
  `serial_no` varchar(150) DEFAULT NULL,
  `transferred_to` varchar(255) DEFAULT NULL COMMENT 'Where item is transferred',
  `notes` text DEFAULT NULL,
  `warranty_flag` tinyint(1) DEFAULT 1 COMMENT 'Flagged as warranty (1=yes, 0=no)',
  `warranty_date` date DEFAULT NULL COMMENT 'Date flagged as warranty',
  `red_text_detected` tinyint(1) DEFAULT 1 COMMENT 'Row had red text during import',
  `dataset_name` varchar(50) DEFAULT NULL,
  `highlight_color` varchar(20) DEFAULT NULL,
  `cell_styles` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warranty replacement records linked to delivery_records';

-- --------------------------------------------------------

--
-- Structure for view `andison_manila`
--
DROP TABLE IF EXISTS `andison_manila`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `andison_manila`  AS SELECT `vw_andison_manila`.`id` AS `id`, `vw_andison_manila`.`delivery_month` AS `delivery_month`, `vw_andison_manila`.`delivery_day` AS `delivery_day`, `vw_andison_manila`.`delivery_year` AS `delivery_year`, `vw_andison_manila`.`record_date` AS `record_date`, `vw_andison_manila`.`delivery_date` AS `delivery_date`, `vw_andison_manila`.`item_code` AS `item_code`, `vw_andison_manila`.`item_name` AS `item_name`, `vw_andison_manila`.`company_name` AS `company_name`, `vw_andison_manila`.`sold_to` AS `sold_to`, `vw_andison_manila`.`quantity` AS `quantity`, `vw_andison_manila`.`status` AS `status`, `vw_andison_manila`.`highlight_color` AS `highlight_color`, `vw_andison_manila`.`cell_styles` AS `cell_styles`, `vw_andison_manila`.`notes` AS `notes`, `vw_andison_manila`.`created_at` AS `created_at`, `vw_andison_manila`.`updated_at` AS `updated_at`, `vw_andison_manila`.`dataset_name` AS `dataset_name`, `vw_andison_manila`.`order_customer` AS `order_customer`, `vw_andison_manila`.`order_date` AS `order_date`, `vw_andison_manila`.`unit_price` AS `unit_price`, `vw_andison_manila`.`total_amount` AS `total_amount`, `vw_andison_manila`.`po_number` AS `po_number`, `vw_andison_manila`.`po_status` AS `po_status`, `vw_andison_manila`.`invoice_no` AS `invoice_no`, `vw_andison_manila`.`serial_no` AS `serial_no`, `vw_andison_manila`.`groupings` AS `groupings`, `vw_andison_manila`.`transferred_to` AS `transferred_to`, `vw_andison_manila`.`box_code` AS `box_code`, `vw_andison_manila`.`model_no` AS `model_no`, `vw_andison_manila`.`uom` AS `uom`, `vw_andison_manila`.`sold_to_month` AS `sold_to_month`, `vw_andison_manila`.`sold_to_day` AS `sold_to_day`, `vw_andison_manila`.`box` AS `box`, `vw_andison_manila`.`items` AS `items`, `vw_andison_manila`.`inventory` AS `inventory`, `vw_andison_manila`.`owner_user_id` AS `owner_user_id` FROM `vw_andison_manila` ;

-- --------------------------------------------------------

--
-- Structure for view `datasets`
--
DROP TABLE IF EXISTS `datasets`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `datasets`  AS SELECT `vw_datasets`.`dataset_name` AS `dataset_name`, `vw_datasets`.`total_records` AS `total_records`, `vw_datasets`.`total_quantity` AS `total_quantity`, `vw_datasets`.`first_record_at` AS `first_record_at`, `vw_datasets`.`last_record_at` AS `last_record_at` FROM `vw_datasets` ;

-- --------------------------------------------------------

--
-- Structure for view `delivery`
--
DROP TABLE IF EXISTS `delivery`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `delivery`  AS SELECT `vw_delivery`.`id` AS `id`, `vw_delivery`.`delivery_month` AS `delivery_month`, `vw_delivery`.`delivery_day` AS `delivery_day`, `vw_delivery`.`delivery_year` AS `delivery_year`, `vw_delivery`.`record_date` AS `record_date`, `vw_delivery`.`delivery_date` AS `delivery_date`, `vw_delivery`.`item_code` AS `item_code`, `vw_delivery`.`item_name` AS `item_name`, `vw_delivery`.`company_name` AS `company_name`, `vw_delivery`.`sold_to` AS `sold_to`, `vw_delivery`.`quantity` AS `quantity`, `vw_delivery`.`status` AS `status`, `vw_delivery`.`highlight_color` AS `highlight_color`, `vw_delivery`.`cell_styles` AS `cell_styles`, `vw_delivery`.`notes` AS `notes`, `vw_delivery`.`created_at` AS `created_at`, `vw_delivery`.`updated_at` AS `updated_at`, `vw_delivery`.`dataset_name` AS `dataset_name`, `vw_delivery`.`order_customer` AS `order_customer`, `vw_delivery`.`order_date` AS `order_date`, `vw_delivery`.`unit_price` AS `unit_price`, `vw_delivery`.`total_amount` AS `total_amount`, `vw_delivery`.`po_number` AS `po_number`, `vw_delivery`.`po_status` AS `po_status`, `vw_delivery`.`invoice_no` AS `invoice_no`, `vw_delivery`.`serial_no` AS `serial_no`, `vw_delivery`.`groupings` AS `groupings`, `vw_delivery`.`transferred_to` AS `transferred_to`, `vw_delivery`.`box_code` AS `box_code`, `vw_delivery`.`model_no` AS `model_no`, `vw_delivery`.`uom` AS `uom`, `vw_delivery`.`sold_to_month` AS `sold_to_month`, `vw_delivery`.`sold_to_day` AS `sold_to_day`, `vw_delivery`.`box` AS `box`, `vw_delivery`.`items` AS `items`, `vw_delivery`.`inventory` AS `inventory`, `vw_delivery`.`owner_user_id` AS `owner_user_id` FROM `vw_delivery` ;

-- --------------------------------------------------------

--
-- Structure for view `inquiry`
--
DROP TABLE IF EXISTS `inquiry`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `inquiry`  AS SELECT `vw_inquiry`.`id` AS `id`, `vw_inquiry`.`delivery_month` AS `delivery_month`, `vw_inquiry`.`delivery_day` AS `delivery_day`, `vw_inquiry`.`delivery_year` AS `delivery_year`, `vw_inquiry`.`record_date` AS `record_date`, `vw_inquiry`.`delivery_date` AS `delivery_date`, `vw_inquiry`.`item_code` AS `item_code`, `vw_inquiry`.`item_name` AS `item_name`, `vw_inquiry`.`company_name` AS `company_name`, `vw_inquiry`.`sold_to` AS `sold_to`, `vw_inquiry`.`quantity` AS `quantity`, `vw_inquiry`.`status` AS `status`, `vw_inquiry`.`highlight_color` AS `highlight_color`, `vw_inquiry`.`cell_styles` AS `cell_styles`, `vw_inquiry`.`notes` AS `notes`, `vw_inquiry`.`created_at` AS `created_at`, `vw_inquiry`.`updated_at` AS `updated_at`, `vw_inquiry`.`dataset_name` AS `dataset_name`, `vw_inquiry`.`order_customer` AS `order_customer`, `vw_inquiry`.`order_date` AS `order_date`, `vw_inquiry`.`unit_price` AS `unit_price`, `vw_inquiry`.`total_amount` AS `total_amount`, `vw_inquiry`.`po_number` AS `po_number`, `vw_inquiry`.`po_status` AS `po_status`, `vw_inquiry`.`invoice_no` AS `invoice_no`, `vw_inquiry`.`serial_no` AS `serial_no`, `vw_inquiry`.`groupings` AS `groupings`, `vw_inquiry`.`transferred_to` AS `transferred_to`, `vw_inquiry`.`box_code` AS `box_code`, `vw_inquiry`.`model_no` AS `model_no`, `vw_inquiry`.`uom` AS `uom`, `vw_inquiry`.`sold_to_month` AS `sold_to_month`, `vw_inquiry`.`sold_to_day` AS `sold_to_day`, `vw_inquiry`.`box` AS `box`, `vw_inquiry`.`items` AS `items`, `vw_inquiry`.`inventory` AS `inventory`, `vw_inquiry`.`owner_user_id` AS `owner_user_id` FROM `vw_inquiry` ;

-- --------------------------------------------------------

--
-- Structure for view `inventory`
--
DROP TABLE IF EXISTS `inventory`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `inventory`  AS SELECT `vw_inventory`.`id` AS `id`, `vw_inventory`.`delivery_month` AS `delivery_month`, `vw_inventory`.`delivery_day` AS `delivery_day`, `vw_inventory`.`delivery_year` AS `delivery_year`, `vw_inventory`.`record_date` AS `record_date`, `vw_inventory`.`delivery_date` AS `delivery_date`, `vw_inventory`.`item_code` AS `item_code`, `vw_inventory`.`item_name` AS `item_name`, `vw_inventory`.`company_name` AS `company_name`, `vw_inventory`.`sold_to` AS `sold_to`, `vw_inventory`.`quantity` AS `quantity`, `vw_inventory`.`status` AS `status`, `vw_inventory`.`highlight_color` AS `highlight_color`, `vw_inventory`.`cell_styles` AS `cell_styles`, `vw_inventory`.`notes` AS `notes`, `vw_inventory`.`created_at` AS `created_at`, `vw_inventory`.`updated_at` AS `updated_at`, `vw_inventory`.`dataset_name` AS `dataset_name`, `vw_inventory`.`order_customer` AS `order_customer`, `vw_inventory`.`order_date` AS `order_date`, `vw_inventory`.`unit_price` AS `unit_price`, `vw_inventory`.`total_amount` AS `total_amount`, `vw_inventory`.`po_number` AS `po_number`, `vw_inventory`.`po_status` AS `po_status`, `vw_inventory`.`invoice_no` AS `invoice_no`, `vw_inventory`.`serial_no` AS `serial_no`, `vw_inventory`.`groupings` AS `groupings`, `vw_inventory`.`transferred_to` AS `transferred_to`, `vw_inventory`.`box_code` AS `box_code`, `vw_inventory`.`model_no` AS `model_no`, `vw_inventory`.`uom` AS `uom`, `vw_inventory`.`sold_to_month` AS `sold_to_month`, `vw_inventory`.`sold_to_day` AS `sold_to_day`, `vw_inventory`.`box` AS `box`, `vw_inventory`.`items` AS `items`, `vw_inventory`.`inventory` AS `inventory`, `vw_inventory`.`owner_user_id` AS `owner_user_id` FROM `vw_inventory` ;

-- --------------------------------------------------------

--
-- Structure for view `purchase_order`
--
DROP TABLE IF EXISTS `purchase_order`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `purchase_order`  AS SELECT `vw_purchase_orders`.`id` AS `id`, `vw_purchase_orders`.`delivery_month` AS `delivery_month`, `vw_purchase_orders`.`delivery_day` AS `delivery_day`, `vw_purchase_orders`.`delivery_year` AS `delivery_year`, `vw_purchase_orders`.`record_date` AS `record_date`, `vw_purchase_orders`.`delivery_date` AS `delivery_date`, `vw_purchase_orders`.`item_code` AS `item_code`, `vw_purchase_orders`.`item_name` AS `item_name`, `vw_purchase_orders`.`company_name` AS `company_name`, `vw_purchase_orders`.`sold_to` AS `sold_to`, `vw_purchase_orders`.`quantity` AS `quantity`, `vw_purchase_orders`.`status` AS `status`, `vw_purchase_orders`.`highlight_color` AS `highlight_color`, `vw_purchase_orders`.`cell_styles` AS `cell_styles`, `vw_purchase_orders`.`notes` AS `notes`, `vw_purchase_orders`.`created_at` AS `created_at`, `vw_purchase_orders`.`updated_at` AS `updated_at`, `vw_purchase_orders`.`dataset_name` AS `dataset_name`, `vw_purchase_orders`.`order_customer` AS `order_customer`, `vw_purchase_orders`.`order_date` AS `order_date`, `vw_purchase_orders`.`unit_price` AS `unit_price`, `vw_purchase_orders`.`total_amount` AS `total_amount`, `vw_purchase_orders`.`po_number` AS `po_number`, `vw_purchase_orders`.`po_status` AS `po_status`, `vw_purchase_orders`.`invoice_no` AS `invoice_no`, `vw_purchase_orders`.`serial_no` AS `serial_no`, `vw_purchase_orders`.`groupings` AS `groupings`, `vw_purchase_orders`.`transferred_to` AS `transferred_to`, `vw_purchase_orders`.`box_code` AS `box_code`, `vw_purchase_orders`.`model_no` AS `model_no`, `vw_purchase_orders`.`uom` AS `uom`, `vw_purchase_orders`.`sold_to_month` AS `sold_to_month`, `vw_purchase_orders`.`sold_to_day` AS `sold_to_day`, `vw_purchase_orders`.`box` AS `box`, `vw_purchase_orders`.`items` AS `items`, `vw_purchase_orders`.`inventory` AS `inventory`, `vw_purchase_orders`.`owner_user_id` AS `owner_user_id` FROM `vw_purchase_orders` ;

-- --------------------------------------------------------

--
-- Structure for view `sales`
--
DROP TABLE IF EXISTS `sales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sales`  AS SELECT `vw_sales`.`id` AS `id`, `vw_sales`.`delivery_month` AS `delivery_month`, `vw_sales`.`delivery_day` AS `delivery_day`, `vw_sales`.`delivery_year` AS `delivery_year`, `vw_sales`.`record_date` AS `record_date`, `vw_sales`.`delivery_date` AS `delivery_date`, `vw_sales`.`item_code` AS `item_code`, `vw_sales`.`item_name` AS `item_name`, `vw_sales`.`company_name` AS `company_name`, `vw_sales`.`sold_to` AS `sold_to`, `vw_sales`.`quantity` AS `quantity`, `vw_sales`.`status` AS `status`, `vw_sales`.`highlight_color` AS `highlight_color`, `vw_sales`.`cell_styles` AS `cell_styles`, `vw_sales`.`notes` AS `notes`, `vw_sales`.`created_at` AS `created_at`, `vw_sales`.`updated_at` AS `updated_at`, `vw_sales`.`dataset_name` AS `dataset_name`, `vw_sales`.`order_customer` AS `order_customer`, `vw_sales`.`order_date` AS `order_date`, `vw_sales`.`unit_price` AS `unit_price`, `vw_sales`.`total_amount` AS `total_amount`, `vw_sales`.`po_number` AS `po_number`, `vw_sales`.`po_status` AS `po_status`, `vw_sales`.`invoice_no` AS `invoice_no`, `vw_sales`.`serial_no` AS `serial_no`, `vw_sales`.`groupings` AS `groupings`, `vw_sales`.`transferred_to` AS `transferred_to`, `vw_sales`.`box_code` AS `box_code`, `vw_sales`.`model_no` AS `model_no`, `vw_sales`.`uom` AS `uom`, `vw_sales`.`sold_to_month` AS `sold_to_month`, `vw_sales`.`sold_to_day` AS `sold_to_day`, `vw_sales`.`box` AS `box`, `vw_sales`.`items` AS `items`, `vw_sales`.`inventory` AS `inventory`, `vw_sales`.`owner_user_id` AS `owner_user_id` FROM `vw_sales` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_andison_manila`
--
DROP TABLE IF EXISTS `vw_andison_manila`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_andison_manila`  AS SELECT `delivery_records`.`id` AS `id`, `delivery_records`.`delivery_month` AS `delivery_month`, `delivery_records`.`delivery_day` AS `delivery_day`, `delivery_records`.`delivery_year` AS `delivery_year`, `delivery_records`.`record_date` AS `record_date`, `delivery_records`.`delivery_date` AS `delivery_date`, `delivery_records`.`item_code` AS `item_code`, `delivery_records`.`item_name` AS `item_name`, `delivery_records`.`company_name` AS `company_name`, `delivery_records`.`sold_to` AS `sold_to`, `delivery_records`.`quantity` AS `quantity`, `delivery_records`.`status` AS `status`, `delivery_records`.`highlight_color` AS `highlight_color`, `delivery_records`.`cell_styles` AS `cell_styles`, `delivery_records`.`notes` AS `notes`, `delivery_records`.`created_at` AS `created_at`, `delivery_records`.`updated_at` AS `updated_at`, `delivery_records`.`dataset_name` AS `dataset_name`, `delivery_records`.`order_customer` AS `order_customer`, `delivery_records`.`order_date` AS `order_date`, `delivery_records`.`unit_price` AS `unit_price`, `delivery_records`.`total_amount` AS `total_amount`, `delivery_records`.`po_number` AS `po_number`, `delivery_records`.`po_status` AS `po_status`, `delivery_records`.`invoice_no` AS `invoice_no`, `delivery_records`.`serial_no` AS `serial_no`, `delivery_records`.`groupings` AS `groupings`, `delivery_records`.`transferred_to` AS `transferred_to`, `delivery_records`.`box_code` AS `box_code`, `delivery_records`.`model_no` AS `model_no`, `delivery_records`.`uom` AS `uom`, `delivery_records`.`sold_to_month` AS `sold_to_month`, `delivery_records`.`sold_to_day` AS `sold_to_day`, `delivery_records`.`box` AS `box`, `delivery_records`.`items` AS `items`, `delivery_records`.`inventory` AS `inventory`, `delivery_records`.`owner_user_id` AS `owner_user_id` FROM `delivery_records` WHERE lcase(trim(coalesce(`delivery_records`.`company_name`,''))) in ('andison manila','to andison manila') OR lcase(trim(coalesce(`delivery_records`.`sold_to`,''))) in ('andison manila','to andison manila') ;

-- --------------------------------------------------------

--
-- Structure for view `vw_datasets`
--
DROP TABLE IF EXISTS `vw_datasets`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_datasets`  AS SELECT coalesce(nullif(trim(`delivery_records`.`dataset_name`),''),'UNASSIGNED') AS `dataset_name`, count(0) AS `total_records`, coalesce(sum(`delivery_records`.`quantity`),0) AS `total_quantity`, min(`delivery_records`.`created_at`) AS `first_record_at`, max(`delivery_records`.`created_at`) AS `last_record_at` FROM `delivery_records` GROUP BY coalesce(nullif(trim(`delivery_records`.`dataset_name`),''),'UNASSIGNED') ;

-- --------------------------------------------------------

--
-- Structure for view `vw_delivery`
--
DROP TABLE IF EXISTS `vw_delivery`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_delivery`  AS SELECT `delivery_records`.`id` AS `id`, `delivery_records`.`delivery_month` AS `delivery_month`, `delivery_records`.`delivery_day` AS `delivery_day`, `delivery_records`.`delivery_year` AS `delivery_year`, `delivery_records`.`record_date` AS `record_date`, `delivery_records`.`delivery_date` AS `delivery_date`, `delivery_records`.`item_code` AS `item_code`, `delivery_records`.`item_name` AS `item_name`, `delivery_records`.`company_name` AS `company_name`, `delivery_records`.`sold_to` AS `sold_to`, `delivery_records`.`quantity` AS `quantity`, `delivery_records`.`status` AS `status`, `delivery_records`.`highlight_color` AS `highlight_color`, `delivery_records`.`cell_styles` AS `cell_styles`, `delivery_records`.`notes` AS `notes`, `delivery_records`.`created_at` AS `created_at`, `delivery_records`.`updated_at` AS `updated_at`, `delivery_records`.`dataset_name` AS `dataset_name`, `delivery_records`.`order_customer` AS `order_customer`, `delivery_records`.`order_date` AS `order_date`, `delivery_records`.`unit_price` AS `unit_price`, `delivery_records`.`total_amount` AS `total_amount`, `delivery_records`.`po_number` AS `po_number`, `delivery_records`.`po_status` AS `po_status`, `delivery_records`.`invoice_no` AS `invoice_no`, `delivery_records`.`serial_no` AS `serial_no`, `delivery_records`.`groupings` AS `groupings`, `delivery_records`.`transferred_to` AS `transferred_to`, `delivery_records`.`box_code` AS `box_code`, `delivery_records`.`model_no` AS `model_no`, `delivery_records`.`uom` AS `uom`, `delivery_records`.`sold_to_month` AS `sold_to_month`, `delivery_records`.`sold_to_day` AS `sold_to_day`, `delivery_records`.`box` AS `box`, `delivery_records`.`items` AS `items`, `delivery_records`.`inventory` AS `inventory`, `delivery_records`.`owner_user_id` AS `owner_user_id` FROM `delivery_records` WHERE `delivery_records`.`company_name` <> 'Orders' AND lcase(trim(coalesce(`delivery_records`.`company_name`,''))) not in ('andison manila','to andison manila') AND lcase(trim(coalesce(`delivery_records`.`sold_to`,''))) not in ('andison manila','to andison manila') ;

-- --------------------------------------------------------

--
-- Structure for view `vw_inquiry`
--
DROP TABLE IF EXISTS `vw_inquiry`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_inquiry`  AS SELECT `delivery_records`.`id` AS `id`, `delivery_records`.`delivery_month` AS `delivery_month`, `delivery_records`.`delivery_day` AS `delivery_day`, `delivery_records`.`delivery_year` AS `delivery_year`, `delivery_records`.`record_date` AS `record_date`, `delivery_records`.`delivery_date` AS `delivery_date`, `delivery_records`.`item_code` AS `item_code`, `delivery_records`.`item_name` AS `item_name`, `delivery_records`.`company_name` AS `company_name`, `delivery_records`.`sold_to` AS `sold_to`, `delivery_records`.`quantity` AS `quantity`, `delivery_records`.`status` AS `status`, `delivery_records`.`highlight_color` AS `highlight_color`, `delivery_records`.`cell_styles` AS `cell_styles`, `delivery_records`.`notes` AS `notes`, `delivery_records`.`created_at` AS `created_at`, `delivery_records`.`updated_at` AS `updated_at`, `delivery_records`.`dataset_name` AS `dataset_name`, `delivery_records`.`order_customer` AS `order_customer`, `delivery_records`.`order_date` AS `order_date`, `delivery_records`.`unit_price` AS `unit_price`, `delivery_records`.`total_amount` AS `total_amount`, `delivery_records`.`po_number` AS `po_number`, `delivery_records`.`po_status` AS `po_status`, `delivery_records`.`invoice_no` AS `invoice_no`, `delivery_records`.`serial_no` AS `serial_no`, `delivery_records`.`groupings` AS `groupings`, `delivery_records`.`transferred_to` AS `transferred_to`, `delivery_records`.`box_code` AS `box_code`, `delivery_records`.`model_no` AS `model_no`, `delivery_records`.`uom` AS `uom`, `delivery_records`.`sold_to_month` AS `sold_to_month`, `delivery_records`.`sold_to_day` AS `sold_to_day`, `delivery_records`.`box` AS `box`, `delivery_records`.`items` AS `items`, `delivery_records`.`inventory` AS `inventory`, `delivery_records`.`owner_user_id` AS `owner_user_id` FROM `delivery_records` WHERE `delivery_records`.`company_name` = 'Orders' AND (coalesce(`delivery_records`.`po_status`,'') = '' OR `delivery_records`.`po_status` in ('No PO','Pending')) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_inventory`
--
DROP TABLE IF EXISTS `vw_inventory`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_inventory`  AS SELECT `delivery_records`.`id` AS `id`, `delivery_records`.`delivery_month` AS `delivery_month`, `delivery_records`.`delivery_day` AS `delivery_day`, `delivery_records`.`delivery_year` AS `delivery_year`, `delivery_records`.`record_date` AS `record_date`, `delivery_records`.`delivery_date` AS `delivery_date`, `delivery_records`.`item_code` AS `item_code`, `delivery_records`.`item_name` AS `item_name`, `delivery_records`.`company_name` AS `company_name`, `delivery_records`.`sold_to` AS `sold_to`, `delivery_records`.`quantity` AS `quantity`, `delivery_records`.`status` AS `status`, `delivery_records`.`highlight_color` AS `highlight_color`, `delivery_records`.`cell_styles` AS `cell_styles`, `delivery_records`.`notes` AS `notes`, `delivery_records`.`created_at` AS `created_at`, `delivery_records`.`updated_at` AS `updated_at`, `delivery_records`.`dataset_name` AS `dataset_name`, `delivery_records`.`order_customer` AS `order_customer`, `delivery_records`.`order_date` AS `order_date`, `delivery_records`.`unit_price` AS `unit_price`, `delivery_records`.`total_amount` AS `total_amount`, `delivery_records`.`po_number` AS `po_number`, `delivery_records`.`po_status` AS `po_status`, `delivery_records`.`invoice_no` AS `invoice_no`, `delivery_records`.`serial_no` AS `serial_no`, `delivery_records`.`groupings` AS `groupings`, `delivery_records`.`transferred_to` AS `transferred_to`, `delivery_records`.`box_code` AS `box_code`, `delivery_records`.`model_no` AS `model_no`, `delivery_records`.`uom` AS `uom`, `delivery_records`.`sold_to_month` AS `sold_to_month`, `delivery_records`.`sold_to_day` AS `sold_to_day`, `delivery_records`.`box` AS `box`, `delivery_records`.`items` AS `items`, `delivery_records`.`inventory` AS `inventory`, `delivery_records`.`owner_user_id` AS `owner_user_id` FROM `delivery_records` WHERE `delivery_records`.`company_name` = 'Stock Addition' ;

-- --------------------------------------------------------

--
-- Structure for view `vw_purchase_orders`
--
DROP TABLE IF EXISTS `vw_purchase_orders`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_purchase_orders`  AS SELECT `delivery_records`.`id` AS `id`, `delivery_records`.`delivery_month` AS `delivery_month`, `delivery_records`.`delivery_day` AS `delivery_day`, `delivery_records`.`delivery_year` AS `delivery_year`, `delivery_records`.`record_date` AS `record_date`, `delivery_records`.`delivery_date` AS `delivery_date`, `delivery_records`.`item_code` AS `item_code`, `delivery_records`.`item_name` AS `item_name`, `delivery_records`.`company_name` AS `company_name`, `delivery_records`.`sold_to` AS `sold_to`, `delivery_records`.`quantity` AS `quantity`, `delivery_records`.`status` AS `status`, `delivery_records`.`highlight_color` AS `highlight_color`, `delivery_records`.`cell_styles` AS `cell_styles`, `delivery_records`.`notes` AS `notes`, `delivery_records`.`created_at` AS `created_at`, `delivery_records`.`updated_at` AS `updated_at`, `delivery_records`.`dataset_name` AS `dataset_name`, `delivery_records`.`order_customer` AS `order_customer`, `delivery_records`.`order_date` AS `order_date`, `delivery_records`.`unit_price` AS `unit_price`, `delivery_records`.`total_amount` AS `total_amount`, `delivery_records`.`po_number` AS `po_number`, `delivery_records`.`po_status` AS `po_status`, `delivery_records`.`invoice_no` AS `invoice_no`, `delivery_records`.`serial_no` AS `serial_no`, `delivery_records`.`groupings` AS `groupings`, `delivery_records`.`transferred_to` AS `transferred_to`, `delivery_records`.`box_code` AS `box_code`, `delivery_records`.`model_no` AS `model_no`, `delivery_records`.`uom` AS `uom`, `delivery_records`.`sold_to_month` AS `sold_to_month`, `delivery_records`.`sold_to_day` AS `sold_to_day`, `delivery_records`.`box` AS `box`, `delivery_records`.`items` AS `items`, `delivery_records`.`inventory` AS `inventory`, `delivery_records`.`owner_user_id` AS `owner_user_id` FROM `delivery_records` WHERE `delivery_records`.`company_name` = 'Orders' ;

-- --------------------------------------------------------

--
-- Structure for view `vw_sales`
--
DROP TABLE IF EXISTS `vw_sales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_sales`  AS SELECT `delivery_records`.`id` AS `id`, `delivery_records`.`delivery_month` AS `delivery_month`, `delivery_records`.`delivery_day` AS `delivery_day`, `delivery_records`.`delivery_year` AS `delivery_year`, `delivery_records`.`record_date` AS `record_date`, `delivery_records`.`delivery_date` AS `delivery_date`, `delivery_records`.`item_code` AS `item_code`, `delivery_records`.`item_name` AS `item_name`, `delivery_records`.`company_name` AS `company_name`, `delivery_records`.`sold_to` AS `sold_to`, `delivery_records`.`quantity` AS `quantity`, `delivery_records`.`status` AS `status`, `delivery_records`.`highlight_color` AS `highlight_color`, `delivery_records`.`cell_styles` AS `cell_styles`, `delivery_records`.`notes` AS `notes`, `delivery_records`.`created_at` AS `created_at`, `delivery_records`.`updated_at` AS `updated_at`, `delivery_records`.`dataset_name` AS `dataset_name`, `delivery_records`.`order_customer` AS `order_customer`, `delivery_records`.`order_date` AS `order_date`, `delivery_records`.`unit_price` AS `unit_price`, `delivery_records`.`total_amount` AS `total_amount`, `delivery_records`.`po_number` AS `po_number`, `delivery_records`.`po_status` AS `po_status`, `delivery_records`.`invoice_no` AS `invoice_no`, `delivery_records`.`serial_no` AS `serial_no`, `delivery_records`.`groupings` AS `groupings`, `delivery_records`.`transferred_to` AS `transferred_to`, `delivery_records`.`box_code` AS `box_code`, `delivery_records`.`model_no` AS `model_no`, `delivery_records`.`uom` AS `uom`, `delivery_records`.`sold_to_month` AS `sold_to_month`, `delivery_records`.`sold_to_day` AS `sold_to_day`, `delivery_records`.`box` AS `box`, `delivery_records`.`items` AS `items`, `delivery_records`.`inventory` AS `inventory`, `delivery_records`.`owner_user_id` AS `owner_user_id` FROM `delivery_records` WHERE `delivery_records`.`quantity` > 0 AND `delivery_records`.`company_name` not in ('Stock Addition','Orders') AND lcase(trim(coalesce(`delivery_records`.`company_name`,''))) not in ('andison manila','to andison manila') ;

-- --------------------------------------------------------

--
-- Structure for view `warranty`
--
DROP TABLE IF EXISTS `warranty`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `warranty`  AS SELECT `warranty_replacements`.`id` AS `id`, `warranty_replacements`.`delivery_record_id` AS `delivery_record_id`, `warranty_replacements`.`invoice_no` AS `invoice_no`, `warranty_replacements`.`delivery_month` AS `delivery_month`, `warranty_replacements`.`delivery_day` AS `delivery_day`, `warranty_replacements`.`delivery_year` AS `delivery_year`, `warranty_replacements`.`record_date` AS `record_date`, `warranty_replacements`.`delivery_date` AS `delivery_date`, `warranty_replacements`.`item_code` AS `item_code`, `warranty_replacements`.`item_name` AS `item_name`, `warranty_replacements`.`company_name` AS `company_name`, `warranty_replacements`.`sold_to` AS `sold_to`, `warranty_replacements`.`quantity` AS `quantity`, `warranty_replacements`.`status` AS `status`, `warranty_replacements`.`uom` AS `uom`, `warranty_replacements`.`serial_no` AS `serial_no`, `warranty_replacements`.`transferred_to` AS `transferred_to`, `warranty_replacements`.`notes` AS `notes`, `warranty_replacements`.`warranty_flag` AS `warranty_flag`, `warranty_replacements`.`warranty_date` AS `warranty_date`, `warranty_replacements`.`red_text_detected` AS `red_text_detected`, `warranty_replacements`.`dataset_name` AS `dataset_name`, `warranty_replacements`.`highlight_color` AS `highlight_color`, `warranty_replacements`.`cell_styles` AS `cell_styles`, `warranty_replacements`.`created_at` AS `created_at`, `warranty_replacements`.`updated_at` AS `updated_at` FROM `warranty_replacements` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `delivery_highlight_memory`
--
ALTER TABLE `delivery_highlight_memory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_dataset_record` (`dataset_name`,`invoice_no`,`item_code`,`serial_no`,`sold_to`,`delivery_date`);

--
-- Indexes for table `delivery_records`
--
ALTER TABLE `delivery_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_month` (`delivery_month`),
  ADD KEY `idx_delivery_day` (`delivery_day`),
  ADD KEY `idx_delivery_year` (`delivery_year`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_company_name` (`company_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_owner_user_id` (`owner_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `warranty_replacements`
--
ALTER TABLE `warranty_replacements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_month` (`delivery_month`),
  ADD KEY `idx_delivery_day` (`delivery_day`),
  ADD KEY `idx_delivery_year` (`delivery_year`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_company_name` (`company_name`),
  ADD KEY `idx_warranty_flag` (`warranty_flag`),
  ADD KEY `idx_warranty_date` (`warranty_date`),
  ADD KEY `idx_delivery_record_id` (`delivery_record_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `delivery_highlight_memory`
--
ALTER TABLE `delivery_highlight_memory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_records`
--
ALTER TABLE `delivery_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5475;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warranty_replacements`
--
ALTER TABLE `warranty_replacements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1372;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `warranty_replacements`
--
ALTER TABLE `warranty_replacements`
  ADD CONSTRAINT `fk_warranty_delivery_record` FOREIGN KEY (`delivery_record_id`) REFERENCES `delivery_records` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
