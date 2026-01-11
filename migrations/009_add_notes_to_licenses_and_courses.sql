-- Migration 009: Add notes field to member_licenses and member_courses tables
-- This migration adds missing notes fields that are used in the member portal

-- Add notes field to member_licenses if it doesn't exist
ALTER TABLE `member_licenses` 
ADD COLUMN IF NOT EXISTS `notes` text COMMENT 'Note aggiuntive sulla patente';

-- Add notes field to member_courses if it doesn't exist
ALTER TABLE `member_courses` 
ADD COLUMN IF NOT EXISTS `notes` text COMMENT 'Note aggiuntive sul corso';
