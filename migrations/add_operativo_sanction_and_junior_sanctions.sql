-- Migration: Add 'operativo' sanction type and create junior_member_sanctions table
-- Date: 2024-12-07
-- Description: 
-- 1. Add 'operativo' to member_sanctions sanction_type enum
-- 2. Create junior_member_sanctions table with same structure
-- 3. This allows tracking sanctions for junior members and implementing
--    the operativo sanction that returns members to active status

-- Step 1: Modify member_sanctions table to add 'operativo' sanction type
ALTER TABLE `member_sanctions` 
MODIFY COLUMN `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'operativo') NOT NULL;

-- Step 2: Create junior_member_sanctions table
CREATE TABLE IF NOT EXISTS `junior_member_sanctions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `sanction_date` date NOT NULL,
  `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'operativo') NOT NULL,
  `reason` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
