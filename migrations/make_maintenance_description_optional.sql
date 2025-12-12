-- Migration: Make vehicle_maintenance description optional
-- Date: 2024-12-12
-- Description: Makes the description field in vehicle_maintenance table nullable

ALTER TABLE `vehicle_maintenance` MODIFY COLUMN `description` TEXT NULL;
