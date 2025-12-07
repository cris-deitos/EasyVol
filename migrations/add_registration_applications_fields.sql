-- Migration: Add fields to member_applications table for enhanced registration system
-- Date: 2025-12-07
-- Description: Adds approved_at and member_id fields to support the new registration workflow

-- Add new fields to member_applications table if they don't exist
ALTER TABLE `member_applications` 
ADD COLUMN `approved_at` timestamp NULL DEFAULT NULL AFTER `processed_at`,
ADD COLUMN `member_id` int(11) DEFAULT NULL COMMENT 'ID of created member after approval' AFTER `approved_at`;

-- Add index
CREATE INDEX `idx_application_type` ON `member_applications` (`application_type`);

-- Update existing rows to use submitted_at as processed_at if needed
UPDATE `member_applications` 
SET `processed_at` = `submitted_at` 
WHERE `status` != 'pending' AND `processed_at` IS NULL;
