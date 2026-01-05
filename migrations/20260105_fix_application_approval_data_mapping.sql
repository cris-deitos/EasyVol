-- Migration: Fix Application Approval Data Mapping
-- Date: 2026-01-05
-- Description: Add missing fields to junior_member_guardians table and ensure
--              ApplicationController properly saves all collected form data.
-- 
-- Issues Fixed:
-- 1. Guardian birth_date and birth_place are collected but not saved
-- 2. Member gender, nationality, birth_province exist in DB but not saved by controller
-- 3. Junior member gender, nationality, birth_province exist in DB but not saved by controller

-- =============================================================================
-- NOTE: Members and Junior_Members tables already have these fields:
--   - gender enum('M', 'F')
--   - nationality varchar(100) DEFAULT 'Italiana'
--   - birth_province varchar(5)
-- 
-- The issue is in ApplicationController.php which doesn't INSERT these values.
-- No database changes needed for members/junior_members tables.
-- =============================================================================

-- =============================================================================
-- JUNIOR_MEMBER_GUARDIANS TABLE - Add missing fields for guardians
-- =============================================================================

-- IMPORTANT: If these columns already exist, this migration will fail with a 
-- "Duplicate column" error. This is expected and safe - it means the columns
-- are already present. The migration system should handle this gracefully.

-- Add birth_date field for guardians
ALTER TABLE `junior_member_guardians` 
ADD COLUMN `birth_date` date DEFAULT NULL AFTER `first_name`;

-- Add birth_place field for guardians
ALTER TABLE `junior_member_guardians` 
ADD COLUMN `birth_place` varchar(255) DEFAULT NULL AFTER `birth_date`;

-- =============================================================================
-- MEMBER_COURSES TABLE - Ensure proper structure for Corso Base PC
-- =============================================================================

-- The member_courses table should already handle corso_base_pc properly
-- as it's stored as a course entry. No changes needed.

-- =============================================================================
-- Verification queries (commented out - for manual testing)
-- =============================================================================

-- SHOW COLUMNS FROM `members` LIKE 'gender';
-- SHOW COLUMNS FROM `members` LIKE 'birth_province';
-- SHOW COLUMNS FROM `junior_members` LIKE 'gender';
-- SHOW COLUMNS FROM `junior_members` LIKE 'birth_province';
-- SHOW COLUMNS FROM `junior_member_guardians` LIKE 'birth_date';
-- SHOW COLUMNS FROM `junior_member_guardians` LIKE 'birth_place';

-- Migration completed
