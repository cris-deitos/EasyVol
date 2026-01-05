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

-- NOTE: MySQL 5.6 does not support "ADD COLUMN IF NOT EXISTS" syntax.
-- If these columns already exist, this migration will fail with error 1060 
-- "Duplicate column name". This is expected behavior. The migration runner
-- should either:
-- 1. Check if columns exist before running (recommended), OR
-- 2. Handle error 1060 gracefully as a non-fatal error

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
