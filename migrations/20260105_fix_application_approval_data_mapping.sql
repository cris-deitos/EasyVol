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

-- Check if birth_date column exists, and add it if it doesn't
SET @dbname = DATABASE();
SET @tablename = 'junior_member_guardians';
SET @columnname = 'birth_date';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
     AND (table_schema = @dbname)
     AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " date DEFAULT NULL AFTER first_name")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check if birth_place column exists, and add it if it doesn't
SET @columnname = 'birth_place';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
     AND (table_schema = @dbname)
     AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " varchar(255) DEFAULT NULL AFTER birth_date")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

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
