-- Migration: Add training sessions table and exam fields to training_participants
-- This adds support for:
-- 1. Course sessions with date, start time, and end time
-- 2. Attendance tracking per session with hours
-- 3. Exam results (pass/fail) and score (1-10) for participants

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
-- Add session_id column if it doesn't exist
ALTER TABLE `training_attendance` 
ADD COLUMN IF NOT EXISTS `session_id` int(11) DEFAULT NULL AFTER `course_id`,
ADD COLUMN IF NOT EXISTS `hours_attended` decimal(5,2) DEFAULT NULL COMMENT 'Hours attended in this session',
ADD KEY IF NOT EXISTS `session_id` (`session_id`);

-- Add exam fields to training_participants
ALTER TABLE `training_participants`
ADD COLUMN IF NOT EXISTS `exam_passed` tinyint(1) DEFAULT NULL COMMENT 'Exam result: 1=passed, 0=failed, NULL=not taken',
ADD COLUMN IF NOT EXISTS `exam_score` tinyint(2) DEFAULT NULL COMMENT 'Exam score from 1 to 10',
ADD COLUMN IF NOT EXISTS `total_hours_attended` decimal(6,2) DEFAULT 0 COMMENT 'Total hours attended',
ADD COLUMN IF NOT EXISTS `total_hours_absent` decimal(6,2) DEFAULT 0 COMMENT 'Total hours absent';

-- Add foreign key for session_id (conditional - may fail if already exists)
-- This needs to be run separately due to MySQL limitations
-- ALTER TABLE `training_attendance` ADD FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE;
