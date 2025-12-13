-- Migration: Add operations center user flag
-- Description: Adds is_operations_center_user flag to users table to identify EasyCO users
-- Date: 2025-12-13

ALTER TABLE `users` 
ADD COLUMN `is_operations_center_user` TINYINT(1) DEFAULT 0 COMMENT 'Flag to identify operations center users (EasyCO)' 
AFTER `is_active`;

-- Add index for performance
ALTER TABLE `users` 
ADD INDEX `idx_is_operations_center_user` (`is_operations_center_user`);
