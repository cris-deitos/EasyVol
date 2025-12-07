-- Add badge_number column to members table
-- This field stores the volunteer badge/ID number

ALTER TABLE members 
ADD COLUMN badge_number VARCHAR(20) NULL COMMENT 'Numero tesserino' 
AFTER registration_number;

-- Add index for faster lookups
CREATE INDEX idx_badge_number ON members(badge_number);
