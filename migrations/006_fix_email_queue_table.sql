-- Migration: Fix email_queue table structure
-- Date: 2026-01-11
-- Description: Add missing columns and fix column names in email_queue table

-- Add priority column if it doesn't exist
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'email_queue' 
     AND COLUMN_NAME = 'priority') = 0,
    'ALTER TABLE email_queue ADD COLUMN priority int(11) DEFAULT 3 COMMENT "Priority level 1-5, 1 is highest" AFTER attachments',
    'SELECT "Column priority already exists" AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add scheduled_at column if it doesn't exist
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'email_queue' 
     AND COLUMN_NAME = 'scheduled_at') = 0,
    'ALTER TABLE email_queue ADD COLUMN scheduled_at timestamp NULL COMMENT "When the email should be sent" AFTER error_message',
    'SELECT "Column scheduled_at already exists" AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on scheduled_at if it doesn't exist
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'email_queue' 
     AND INDEX_NAME = 'scheduled_at') = 0,
    'ALTER TABLE email_queue ADD KEY scheduled_at (scheduled_at)',
    'SELECT "Index scheduled_at already exists" AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rename body_html to body if body_html exists
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'email_queue' 
     AND COLUMN_NAME = 'body_html') > 0,
    'ALTER TABLE email_queue CHANGE COLUMN body_html body longtext NOT NULL',
    'SELECT "Column body_html does not exist or already renamed" AS message'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update status enum to include 'processing'
ALTER TABLE email_queue 
  MODIFY COLUMN status enum('pending', 'processing', 'sent', 'failed') DEFAULT 'pending';

