-- Migration: Update meeting types and remove title requirement
-- Date: 2025-12-27
-- Description: Updates meeting types to match new requirements and makes title field nullable

-- Update meeting_type enum to include new types
ALTER TABLE `meetings` 
MODIFY COLUMN `meeting_type` enum(
    'assemblea_ordinaria', 
    'assemblea_straordinaria', 
    'consiglio_direttivo', 
    'riunione_capisquadra',
    'riunione_nucleo',
    'altra_riunione'
) NOT NULL;

-- Make title field nullable since it's no longer required
ALTER TABLE `meetings` 
MODIFY COLUMN `title` varchar(255) NULL;

-- Update any existing 'altra_riunione' meetings to more specific types if needed
-- This is commented out as it requires manual review of existing data
-- UPDATE `meetings` SET `meeting_type` = 'riunione_capisquadra' WHERE `meeting_type` = 'altra_riunione' AND title LIKE '%capisquadra%';
-- UPDATE `meetings` SET `meeting_type` = 'riunione_nucleo' WHERE `meeting_type` = 'altra_riunione' AND title LIKE '%nucleo%';
