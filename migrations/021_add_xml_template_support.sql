-- Migration: Add XML Template Support
-- Description: Adds XML template format support to print_templates table
-- Date: 2026-01-22

-- Add xml_content column to store XML template structure
ALTER TABLE `print_templates` 
ADD COLUMN `xml_content` LONGTEXT COMMENT 'Contenuto XML del template' AFTER `html_content`;

-- Add template_format column to distinguish between HTML and XML templates
ALTER TABLE `print_templates`
ADD COLUMN `template_format` ENUM('html', 'xml') NOT NULL DEFAULT 'html' COMMENT 'Formato template: html o xml' AFTER `template_type`;

-- Add xml_schema_version for future compatibility
ALTER TABLE `print_templates`
ADD COLUMN `xml_schema_version` VARCHAR(10) DEFAULT '1.0' COMMENT 'Versione schema XML' AFTER `xml_content`;

-- Add index for template format filtering
ALTER TABLE `print_templates`
ADD INDEX `idx_template_format` (`template_format`);

-- Update existing templates to use 'html' format
UPDATE `print_templates` SET `template_format` = 'html' WHERE `template_format` IS NULL OR `template_format` = '';
