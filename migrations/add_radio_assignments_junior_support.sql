-- Migration: Add support for junior members (cadets) in radio assignments
-- Date: 2026-01-05

-- Add junior_member_id column to radio_assignments table
ALTER TABLE `radio_assignments` 
ADD COLUMN `junior_member_id` int(11) NULL COMMENT 'Foreign key to junior_members table for cadet assignments' AFTER `member_id`,
ADD COLUMN `assignee_type` enum('member', 'cadet', 'external') DEFAULT 'member' COMMENT 'Type of assignee: member, cadet, or external personnel' AFTER `junior_member_id`;

-- Add foreign key constraint for junior_members
ALTER TABLE `radio_assignments`
ADD KEY `junior_member_id` (`junior_member_id`),
ADD FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE SET NULL;

-- Add check constraint to ensure either member_id or junior_member_id is set for non-external assignments
-- Note: MySQL 5.6 doesn't support check constraints, but MySQL 8.0+ does
-- For MySQL 5.6 compatibility, this will be enforced at application level
-- ALTER TABLE `radio_assignments`
-- ADD CONSTRAINT `chk_assignee_type` CHECK (
--     (assignee_type = 'external' AND member_id IS NULL AND junior_member_id IS NULL) OR
--     (assignee_type = 'member' AND member_id IS NOT NULL AND junior_member_id IS NULL) OR
--     (assignee_type = 'cadet' AND member_id IS NULL AND junior_member_id IS NOT NULL)
-- );
