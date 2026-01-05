-- Migration: Add training_course_id link to member_courses table
-- Purpose: Link member_courses to organized training courses for automatic population
-- Date: 2026-01-05

-- Add the training_course_id column to member_courses
ALTER TABLE `member_courses`
ADD COLUMN `training_course_id` int(11) DEFAULT NULL COMMENT 'Reference to training_courses if from organized training' AFTER `certificate_file`,
ADD KEY `training_course_id` (`training_course_id`),
ADD CONSTRAINT `fk_member_courses_training` FOREIGN KEY (`training_course_id`) REFERENCES `training_courses`(`id`) ON DELETE SET NULL;
