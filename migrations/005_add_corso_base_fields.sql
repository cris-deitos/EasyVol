-- Migration: Add corso base fields to members table
-- Date: 2026-01-11
-- Description: Add corso_base_completato (checkbox) and corso_base_anno (year) fields to track basic civil protection course completion

ALTER TABLE `members` 
ADD COLUMN `corso_base_completato` tinyint(1) DEFAULT 0 COMMENT 'Flag corso base protezione civile completato',
ADD COLUMN `corso_base_anno` int(11) DEFAULT NULL COMMENT 'Anno completamento corso base protezione civile';
