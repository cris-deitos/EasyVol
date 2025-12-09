-- Migration: Add training sessions table and exam fields to training_participants
-- This adds support for:
-- 1. Course sessions with date, start time, and end time
-- 2. Attendance tracking per session with hours
-- 3. Exam results (pass/fail) and score (1-10) for participants
--
-- NOTE: This migration is designed to be run once on a fresh database or 
-- on an existing database that doesn't have these columns yet.
-- For MySQL < 8.0.12, IF NOT EXISTS is not supported in ALTER TABLE.
-- The migration uses a stored procedure pattern for compatibility.

-- Create training_sessions table for course dates with times
CREATE TABLE IF NOT EXISTS `training_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `session_date` (`session_date`),
  FOREIGN KEY (`course_id`) REFERENCES `training_courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update training_attendance to link to sessions instead of just date
-- Using DROP PROCEDURE IF EXISTS pattern for MySQL 5.6 compatibility
DELIMITER //

DROP PROCEDURE IF EXISTS add_training_attendance_columns//

CREATE PROCEDURE add_training_attendance_columns()
BEGIN
    -- Add session_id column if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'training_attendance' 
                   AND COLUMN_NAME = 'session_id') THEN
        ALTER TABLE `training_attendance` ADD COLUMN `session_id` int(11) DEFAULT NULL AFTER `course_id`;
        ALTER TABLE `training_attendance` ADD KEY `session_id` (`session_id`);
    END IF;
    
    -- Add hours_attended column if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'training_attendance' 
                   AND COLUMN_NAME = 'hours_attended') THEN
        ALTER TABLE `training_attendance` ADD COLUMN `hours_attended` decimal(5,2) DEFAULT NULL COMMENT 'Hours attended in this session';
    END IF;
END//

DROP PROCEDURE IF EXISTS add_training_participants_columns//

CREATE PROCEDURE add_training_participants_columns()
BEGIN
    -- Add exam_passed column if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'training_participants' 
                   AND COLUMN_NAME = 'exam_passed') THEN
        ALTER TABLE `training_participants` ADD COLUMN `exam_passed` tinyint(1) DEFAULT NULL COMMENT 'Exam result: 1=passed, 0=failed, NULL=not taken';
    END IF;
    
    -- Add exam_score column if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'training_participants' 
                   AND COLUMN_NAME = 'exam_score') THEN
        ALTER TABLE `training_participants` ADD COLUMN `exam_score` tinyint(2) DEFAULT NULL COMMENT 'Exam score from 1 to 10';
    END IF;
    
    -- Add total_hours_attended column if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'training_participants' 
                   AND COLUMN_NAME = 'total_hours_attended') THEN
        ALTER TABLE `training_participants` ADD COLUMN `total_hours_attended` decimal(6,2) DEFAULT 0 COMMENT 'Total hours attended';
    END IF;
    
    -- Add total_hours_absent column if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'training_participants' 
                   AND COLUMN_NAME = 'total_hours_absent') THEN
        ALTER TABLE `training_participants` ADD COLUMN `total_hours_absent` decimal(6,2) DEFAULT 0 COMMENT 'Total hours absent';
    END IF;
END//

DELIMITER ;

-- Execute the procedures
CALL add_training_attendance_columns();
CALL add_training_participants_columns();

-- Clean up procedures
DROP PROCEDURE IF EXISTS add_training_attendance_columns;
DROP PROCEDURE IF EXISTS add_training_participants_columns;
