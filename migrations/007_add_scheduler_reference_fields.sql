-- Migration: Add reference fields to scheduler_items table
-- Date: 2026-01-11
-- Purpose: Add reference_type and reference_id columns to enable tracking of source records
--          for automatic synchronization of expiry dates from various modules

-- Check if reference_type column exists, add if not
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND COLUMN_NAME = 'reference_type'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE scheduler_items ADD COLUMN reference_type VARCHAR(50) DEFAULT NULL COMMENT ''Type of source record (qualification, license, insurance, inspection, vehicle_document)'' AFTER completed_at',
    'SELECT ''Column reference_type already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if reference_id column exists, add if not
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND COLUMN_NAME = 'reference_id'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE scheduler_items ADD COLUMN reference_id INT(11) DEFAULT NULL COMMENT ''ID of source record for automatic sync'' AFTER reference_type',
    'SELECT ''Column reference_id already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on reference fields if they don't exist
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND INDEX_NAME = 'idx_reference'
);

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE scheduler_items ADD INDEX idx_reference (reference_type, reference_id)',
    'SELECT ''Index idx_reference already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration completed successfully
SELECT 'Migration 007 completed: Added reference_type and reference_id columns to scheduler_items' AS status;
