-- Migration: Add Vehicle Movement Management System
-- This migration adds all the necessary tables and fields for the "Movimentazione Mezzi" feature
-- Date: 2025-12-27

-- =============================================
-- STEP 1: Add license type field to vehicles table
-- =============================================

ALTER TABLE `vehicles` 
ADD COLUMN `license_type` VARCHAR(50) DEFAULT NULL COMMENT 'Required license types: A, B, C, D, E, Nautica or combinations (e.g., "B,E")' 
AFTER `status`;

-- =============================================
-- STEP 2: Create vehicle checklists table
-- =============================================

CREATE TABLE IF NOT EXISTS `vehicle_checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL COMMENT 'Name of the checklist item',
  `item_type` enum('boolean', 'numeric', 'text') DEFAULT 'boolean' COMMENT 'Type of item: boolean (checkbox), numeric (quantity), text (notes)',
  `check_timing` enum('departure', 'return', 'both') NOT NULL COMMENT 'When to check: at departure, return, or both',
  `is_required` tinyint(1) DEFAULT 0 COMMENT 'Is this item required?',
  `display_order` int(11) DEFAULT 0 COMMENT 'Order in which items are displayed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- STEP 3: Create vehicle movements table
-- =============================================

CREATE TABLE IF NOT EXISTS `vehicle_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `departure_datetime` datetime NOT NULL,
  `departure_km` decimal(10,2) DEFAULT NULL COMMENT 'Odometer at departure',
  `departure_fuel_level` enum('empty', '1/4', '1/2', '3/4', 'full') DEFAULT NULL COMMENT 'Fuel level at departure',
  `service_type` varchar(255) DEFAULT NULL COMMENT 'Type of service/mission',
  `destination` varchar(255) DEFAULT NULL COMMENT 'Mission destination',
  `authorized_by` varchar(255) DEFAULT NULL COMMENT 'Name of person who authorized the departure',
  `departure_notes` text COMMENT 'Notes from departure checklist',
  `departure_anomaly_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag if anomaly reported at departure',
  `departure_anomaly_email_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether anomaly email was sent',
  `return_datetime` datetime DEFAULT NULL,
  `return_km` decimal(10,2) DEFAULT NULL COMMENT 'Odometer at return',
  `return_fuel_level` enum('empty', '1/4', '1/2', '3/4', 'full') DEFAULT NULL COMMENT 'Fuel level at return',
  `return_notes` text COMMENT 'Notes from return checklist',
  `return_anomaly_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag if anomaly reported at return',
  `return_anomaly_email_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether anomaly email was sent at return',
  `traffic_violation_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag for possible traffic violations',
  `traffic_violation_email_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether traffic violation email was sent',
  `status` enum('in_mission', 'completed', 'completed_no_return') DEFAULT 'in_mission' COMMENT 'Movement status',
  `trip_duration_minutes` int(11) DEFAULT NULL COMMENT 'Trip duration in minutes (calculated)',
  `trip_km` decimal(10,2) DEFAULT NULL COMMENT 'Total kilometers traveled (calculated)',
  `created_by_member_id` int(11) NOT NULL COMMENT 'Member who created the departure',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `created_by_member_id` (`created_by_member_id`),
  KEY `departure_datetime` (`departure_datetime`),
  KEY `status` (`status`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by_member_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- STEP 4: Create vehicle movement drivers table
-- =============================================

CREATE TABLE IF NOT EXISTS `vehicle_movement_drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `driver_type` enum('departure', 'return') NOT NULL COMMENT 'Driver for departure or return',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `movement_id` (`movement_id`),
  KEY `member_id` (`member_id`),
  UNIQUE KEY `movement_member_type` (`movement_id`, `member_id`, `driver_type`),
  FOREIGN KEY (`movement_id`) REFERENCES `vehicle_movements`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- STEP 5: Create vehicle movement checklists table
-- =============================================

CREATE TABLE IF NOT EXISTS `vehicle_movement_checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_id` int(11) NOT NULL,
  `checklist_item_id` int(11) DEFAULT NULL COMMENT 'Reference to vehicle_checklists item if exists',
  `item_name` varchar(255) NOT NULL COMMENT 'Name of the checked item',
  `check_timing` enum('departure', 'return') NOT NULL COMMENT 'When this was checked',
  `item_type` enum('boolean', 'numeric', 'text') DEFAULT 'boolean',
  `value_boolean` tinyint(1) DEFAULT NULL COMMENT 'Value for boolean items',
  `value_numeric` decimal(10,2) DEFAULT NULL COMMENT 'Value for numeric items',
  `value_text` text COMMENT 'Value for text items',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `movement_id` (`movement_id`),
  KEY `checklist_item_id` (`checklist_item_id`),
  FOREIGN KEY (`movement_id`) REFERENCES `vehicle_movements`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`checklist_item_id`) REFERENCES `vehicle_checklists`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- STEP 6: Add email configuration for vehicle movement alerts
-- =============================================

INSERT INTO `config` (`config_key`, `config_value`) 
VALUES ('vehicle_movement_alert_emails', '')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

-- =============================================
-- STEP 7: Add new driver qualifications to member_roles
-- Note: These are qualifications, not system roles. They will be added via the UI.
-- This is just documentation for the required qualification names:
-- - AUTISTA A
-- - AUTISTA B
-- - AUTISTA C
-- - AUTISTA D
-- - AUTISTA E
-- - PILOTA NATANTE
-- =============================================

-- =============================================
-- STEP 8: Add permissions for vehicle movement management
-- =============================================

INSERT INTO `permissions` (`module`, `action`, `description`)
VALUES 
  ('vehicle_movements', 'view', 'Visualizzare i movimenti dei veicoli'),
  ('vehicle_movements', 'create', 'Creare nuovi movimenti dei veicoli'),
  ('vehicle_movements', 'edit', 'Modificare i movimenti dei veicoli'),
  ('vehicle_movements', 'delete', 'Eliminare i movimenti dei veicoli')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
