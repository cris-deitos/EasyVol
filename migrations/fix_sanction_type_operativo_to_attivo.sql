-- Migration: Change 'operativo' sanction type to 'attivo'
-- Date: 2024-12-13
-- Description: 
-- The SanctionService expects 'attivo' as the sanction type to return members to active status,
-- but the database schema and a previous migration used 'operativo'.
-- This migration fixes the inconsistency by:
-- 1. Updating any existing 'operativo' sanctions to 'attivo'
-- 2. Modifying the enum to replace 'operativo' with 'attivo'

-- Step 1: Update existing records in member_sanctions
UPDATE `member_sanctions` 
SET `sanction_type` = 'attivo' 
WHERE `sanction_type` = 'operativo';

-- Step 2: Update existing records in junior_member_sanctions
UPDATE `junior_member_sanctions` 
SET `sanction_type` = 'attivo' 
WHERE `sanction_type` = 'operativo';

-- Step 3: Modify member_sanctions table to replace 'operativo' with 'attivo'
ALTER TABLE `member_sanctions` 
MODIFY COLUMN `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo') NOT NULL;

-- Step 4: Modify junior_member_sanctions table to replace 'operativo' with 'attivo'
ALTER TABLE `junior_member_sanctions` 
MODIFY COLUMN `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo') NOT NULL;
