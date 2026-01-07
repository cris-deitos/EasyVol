-- Migration: Ensure member_courses table exists
-- Date: 2026-01-06
-- Updated: 2026-01-07 - Added certification_number column
-- Purpose: Fix fatal error when viewing member details by ensuring the member_courses table exists
-- This table may be missing in fresh installations

-- Create member_courses table if it doesn't exist
CREATE TABLE IF NOT EXISTS `member_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_type` varchar(100) COMMENT 'base, DGR 1190/2019, altro',
  `completion_date` date,
  `expiry_date` date,
  `certificate_file` varchar(255),
  `certification_number` varchar(100) DEFAULT NULL COMMENT 'Numero certificato',
  `training_course_id` int(11) DEFAULT NULL COMMENT 'Reference to training_courses if from organized training',
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `training_course_id` (`training_course_id`),
  CONSTRAINT `fk_member_courses_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_courses_training` FOREIGN KEY (`training_course_id`) REFERENCES `training_courses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
