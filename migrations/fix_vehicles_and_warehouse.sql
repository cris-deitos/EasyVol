-- Fix vehicles and warehouse database schema issues
-- Date: 2024-12-08
-- Purpose: Add missing columns needed for vehicle and warehouse management

-- Add notes column to warehouse_items table if it doesn't exist
-- Used for additional information and comments about warehouse items
ALTER TABLE `warehouse_items` 
ADD COLUMN IF NOT EXISTS `notes` TEXT NULL COMMENT 'Additional notes and comments' AFTER `status`;

-- Update vehicle_maintenance enum to include 'revisione' type if needed
-- This is safe to run multiple times as it includes all values
ALTER TABLE `vehicle_maintenance` 
MODIFY COLUMN `maintenance_type` ENUM(
    'revisione',
    'manutenzione_ordinaria', 
    'manutenzione_straordinaria',
    'anomalie',
    'guasti',
    'riparazioni',
    'sostituzioni',
    'ordinaria',
    'straordinaria',
    'guasto',
    'riparazione',
    'sostituzione',
    'danno',
    'incidente'
) NOT NULL COMMENT 'Tipo di manutenzione';

-- Add status column to vehicle_maintenance if it doesn't exist
ALTER TABLE `vehicle_maintenance` 
ADD COLUMN IF NOT EXISTS `status` ENUM('operativo', 'in_manutenzione', 'fuori_servizio') NULL 
COMMENT 'Vehicle status after maintenance';

-- Add created_by and created_at columns to vehicle_maintenance for audit if they don't exist
ALTER TABLE `vehicle_maintenance` 
ADD COLUMN IF NOT EXISTS `created_by` INT NULL COMMENT 'User who created the maintenance record';

ALTER TABLE `vehicle_maintenance` 
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add index for created_by if it doesn't exist
CREATE INDEX IF NOT EXISTS `idx_vehicle_maintenance_created_by` ON `vehicle_maintenance` (`created_by`);
