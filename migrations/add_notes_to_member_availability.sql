-- Migration: Add notes column to member_availability table
-- This fixes the error: Unknown column 'ma.notes' in 'field list'
-- in OperationsCenterController.php when accessing the operations center dashboard

-- Add notes column if it doesn't exist
-- MySQL doesn't support IF NOT EXISTS for ALTER TABLE ADD COLUMN,
-- so we use a procedure to safely add the column

DELIMITER //

DROP PROCEDURE IF EXISTS add_notes_to_member_availability//

CREATE PROCEDURE add_notes_to_member_availability()
BEGIN
    -- Check if notes column already exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
        AND table_name = 'member_availability' 
        AND column_name = 'notes'
    ) THEN
        ALTER TABLE member_availability 
        ADD COLUMN notes TEXT;
    END IF;
END//

DELIMITER ;

CALL add_notes_to_member_availability();

DROP PROCEDURE IF EXISTS add_notes_to_member_availability;
