-- Add member_type column to junior_members table
-- This allows tracking different types of junior members (e.g., Ordinario)

ALTER TABLE `junior_members` 
ADD COLUMN `member_type` enum('ordinario') DEFAULT 'ordinario' 
AFTER `registration_number`;
