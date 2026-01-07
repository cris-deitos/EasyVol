-- Migration: Add certification_number column to member_courses
-- Date: 2026-01-07
-- Purpose: Add missing column for certification numbers in member courses

ALTER TABLE `member_courses` 
ADD COLUMN `certification_number` varchar(100) DEFAULT NULL COMMENT 'Numero certificato' 
AFTER `certificate_file`;
