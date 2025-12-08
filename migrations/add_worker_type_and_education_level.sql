-- Migration: Add worker type and education level fields to members table
-- Date: 2025-12-08
-- Description: Adds worker_type and education_level fields to support enhanced member information collection

-- Add worker_type field to members table
ALTER TABLE `members` 
ADD COLUMN `worker_type` ENUM('studente', 'dipendente_privato', 'dipendente_pubblico', 'lavoratore_autonomo', 'disoccupato', 'pensionato') DEFAULT NULL 
COMMENT 'Tipo di lavoratore' AFTER `nationality`;

-- Add education_level field to members table
ALTER TABLE `members` 
ADD COLUMN `education_level` ENUM('licenza_media', 'diploma_maturita', 'laurea_triennale', 'laurea_magistrale', 'dottorato') DEFAULT NULL 
COMMENT 'Titolo di studio' AFTER `worker_type`;

-- Add indexes for better query performance
CREATE INDEX `idx_worker_type` ON `members` (`worker_type`);
CREATE INDEX `idx_education_level` ON `members` (`education_level`);
