-- Migration: Add recurring deadline support to scheduler_items table
-- Date: 2026-01-11
-- Purpose: Add recurrence fields to enable recurring deadlines (yearly, monthly, weekly)

-- Add is_recurring column
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND COLUMN_NAME = 'is_recurring'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE scheduler_items ADD COLUMN is_recurring TINYINT(1) DEFAULT 0 COMMENT ''Flag per scadenze ricorrenti'' AFTER reminder_days',
    'SELECT ''Column is_recurring already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add recurrence_type column
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND COLUMN_NAME = 'recurrence_type'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE scheduler_items ADD COLUMN recurrence_type ENUM(''yearly'', ''monthly'', ''weekly'') DEFAULT NULL COMMENT ''Tipo ricorrenza: yearly (1 volta anno), monthly (stesso giorno ogni mese), weekly (stesso giorno ogni settimana)'' AFTER is_recurring',
    'SELECT ''Column recurrence_type already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add recurrence_end_date column
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND COLUMN_NAME = 'recurrence_end_date'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE scheduler_items ADD COLUMN recurrence_end_date DATE DEFAULT NULL COMMENT ''Data fine ricorrenza. NULL = ricorrenza a tempo indeterminato'' AFTER recurrence_type',
    'SELECT ''Column recurrence_end_date already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add parent_recurrence_id column to track which recurring schedule this instance belongs to
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND COLUMN_NAME = 'parent_recurrence_id'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE scheduler_items ADD COLUMN parent_recurrence_id INT(11) DEFAULT NULL COMMENT ''ID della scadenza ricorrente principale (NULL se è la principale)'' AFTER recurrence_end_date',
    'SELECT ''Column parent_recurrence_id already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on parent_recurrence_id
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND INDEX_NAME = 'idx_parent_recurrence'
);

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE scheduler_items ADD INDEX idx_parent_recurrence (parent_recurrence_id)',
    'SELECT ''Index idx_parent_recurrence already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on is_recurring for faster queries
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'scheduler_items' 
    AND INDEX_NAME = 'idx_is_recurring'
);

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE scheduler_items ADD INDEX idx_is_recurring (is_recurring)',
    'SELECT ''Index idx_is_recurring already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration completed successfully
SELECT 'Migration 008 completed: Added recurring deadline support (is_recurring, recurrence_type, recurrence_end_date, parent_recurrence_id)' AS status;
