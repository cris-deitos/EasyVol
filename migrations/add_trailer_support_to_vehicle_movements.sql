-- Migration: Add Trailer Support to Vehicle Movements
-- This migration adds trailer/rimorchio support to the vehicle movements system
-- Date: 2025-12-27

-- =============================================
-- Add trailer field to vehicle_movements table
-- =============================================

ALTER TABLE `vehicle_movements` 
ADD COLUMN `trailer_id` int(11) DEFAULT NULL COMMENT 'Trailer/rimorchio attached to the vehicle' 
AFTER `vehicle_id`,
ADD KEY `trailer_id` (`trailer_id`),
ADD CONSTRAINT `fk_trailer_id` FOREIGN KEY (`trailer_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL;

-- Note: The trailer_id references the vehicles table since trailers are also vehicles (vehicle_type='rimorchio')
-- When a trailer is attached:
-- 1. License validation must check both vehicle and trailer license requirements
-- 2. Checklists should combine both vehicle and trailer checklists
-- 3. Both vehicle and trailer details should be displayed in movement history
