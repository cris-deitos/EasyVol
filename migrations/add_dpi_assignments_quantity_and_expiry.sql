-- Migration: Add quantity and expiry_date columns to dpi_assignments table
-- Date: 2025-12-27

ALTER TABLE `dpi_assignments` 
ADD COLUMN `quantity` int(11) NOT NULL DEFAULT 1 AFTER `member_id`,
ADD COLUMN `expiry_date` date NULL AFTER `return_date`,
ADD COLUMN `assigned_date` date NULL AFTER `assignment_date`;

-- Update existing records to have assigned_date equal to assignment_date
UPDATE `dpi_assignments` SET `assigned_date` = `assignment_date` WHERE `assigned_date` IS NULL;
