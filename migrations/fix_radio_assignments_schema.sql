-- Migration: Fix radio_assignments table schema
-- Date: 2024-12-11
-- Description: Adds missing columns to radio_assignments table required by OperationsCenterController
-- This aligns the database schema with the code expectations

-- Add member_id column if it doesn't exist (for linking to members table)
ALTER TABLE `radio_assignments`
ADD COLUMN IF NOT EXISTS `member_id` INT(11) NULL AFTER `radio_id`,
ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `status`;

-- Add foreign key for member_id if it doesn't exist
-- Using a procedure to safely add the index and foreign key
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_member_fk_to_radio_assignments()
BEGIN
    -- Check if the index exists, if not create it
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'radio_assignments' 
        AND index_name = 'idx_member_id'
    ) THEN
        CREATE INDEX idx_member_id ON radio_assignments(member_id);
    END IF;
    
    -- Check if the foreign key exists, if not add it
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE table_schema = DATABASE() 
        AND table_name = 'radio_assignments' 
        AND constraint_name = 'fk_radio_assignments_member'
    ) THEN
        ALTER TABLE `radio_assignments`
        ADD CONSTRAINT `fk_radio_assignments_member` 
        FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;

CALL add_member_fk_to_radio_assignments();
DROP PROCEDURE IF EXISTS add_member_fk_to_radio_assignments;

-- Note: The table keeps the existing assignee_* columns for backward compatibility
-- and to allow assignments to non-members (external personnel)
