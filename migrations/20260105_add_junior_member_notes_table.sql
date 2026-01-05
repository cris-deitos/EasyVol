-- Migration: Add junior_member_notes table
-- Date: 2026-01-05
-- Description: Add notes table for junior members to match adult member functionality

-- Create junior_member_notes table
CREATE TABLE IF NOT EXISTS `junior_member_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
