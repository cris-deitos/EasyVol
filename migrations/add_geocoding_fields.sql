-- Migration: Add Geocoding Fields to Events and Interventions
-- Date: 2025-12-29
-- Description: Adds latitude, longitude, full_address, and municipality fields for georeferencing

-- Add geocoding fields to events table
ALTER TABLE `events` 
ADD COLUMN `latitude` DECIMAL(10, 8) NULL COMMENT 'Latitudine per georeferenziazione',
ADD COLUMN `longitude` DECIMAL(11, 8) NULL COMMENT 'Longitudine per georeferenziazione',
ADD COLUMN `full_address` VARCHAR(500) NULL COMMENT 'Indirizzo completo georeferenziato',
ADD COLUMN `municipality` VARCHAR(100) NULL COMMENT 'Comune di riferimento',
ADD INDEX `idx_municipality` (`municipality`),
ADD INDEX `idx_coordinates` (`latitude`, `longitude`);

-- Add geocoding fields to interventions table
ALTER TABLE `interventions` 
ADD COLUMN `latitude` DECIMAL(10, 8) NULL COMMENT 'Latitudine per georeferenziazione',
ADD COLUMN `longitude` DECIMAL(11, 8) NULL COMMENT 'Longitudine per georeferenziazione',
ADD COLUMN `full_address` VARCHAR(500) NULL COMMENT 'Indirizzo completo georeferenziato',
ADD COLUMN `municipality` VARCHAR(100) NULL COMMENT 'Comune di riferimento',
ADD INDEX `idx_municipality` (`municipality`),
ADD INDEX `idx_coordinates` (`latitude`, `longitude`);
