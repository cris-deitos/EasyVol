-- Migration: Add SSPC course fields to training_courses table
-- Date: 2025-12-08
-- Description: Adds sspc_course_code and sspc_edition_code fields to support Italian Civil Protection course codes

-- Add sspc_course_code field to training_courses table
ALTER TABLE `training_courses` 
ADD COLUMN `sspc_course_code` VARCHAR(50) DEFAULT NULL 
COMMENT 'Codice Corso SSPC' AFTER `course_type`;

-- Add sspc_edition_code field to training_courses table
ALTER TABLE `training_courses` 
ADD COLUMN `sspc_edition_code` VARCHAR(50) DEFAULT NULL 
COMMENT 'Codice Edizione SSPC' AFTER `sspc_course_code`;

-- Add indexes for better query performance
CREATE INDEX `idx_sspc_course_code` ON `training_courses` (`sspc_course_code`);
CREATE INDEX `idx_sspc_edition_code` ON `training_courses` (`sspc_edition_code`);
