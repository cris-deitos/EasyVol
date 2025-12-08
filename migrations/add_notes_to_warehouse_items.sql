-- Add notes column to warehouse_items table
-- This column is used in the warehouse_edit.php form but was missing from the schema

ALTER TABLE `warehouse_items` 
ADD COLUMN `notes` TEXT NULL AFTER `status`;
