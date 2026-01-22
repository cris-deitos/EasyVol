-- Migration: Simplify Print Templates System
-- Date: 2026-01-22
-- Description: Removes complex XML, relational, and file-based template features
--              Keeps only essential HTML-based template functionality

-- Step 1: Backup existing templates that will be affected
CREATE TABLE IF NOT EXISTS `print_templates_backup_20260122` LIKE `print_templates`;
INSERT INTO `print_templates_backup_20260122` SELECT * FROM `print_templates`;

-- Step 2: Remove columns that are no longer needed
ALTER TABLE `print_templates`
  DROP COLUMN IF EXISTS `xml_content`,
  DROP COLUMN IF EXISTS `xml_schema_version`,
  DROP COLUMN IF EXISTS `relations`,
  DROP COLUMN IF EXISTS `filter_config`,
  DROP COLUMN IF EXISTS `variables`,
  DROP COLUMN IF EXISTS `watermark`,
  DROP COLUMN IF EXISTS `show_header`,
  DROP COLUMN IF EXISTS `show_footer`,
  DROP COLUMN IF EXISTS `header_content`,
  DROP COLUMN IF EXISTS `footer_content`;

-- Step 3: Modify template_format column to remove XML option
ALTER TABLE `print_templates`
  DROP COLUMN IF EXISTS `template_format`;

-- Step 4: Modify template_type to remove complex types
ALTER TABLE `print_templates`
  MODIFY COLUMN `template_type` enum('single', 'list') NOT NULL DEFAULT 'single' 
  COMMENT 'Tipo template: single (singolo record) o list (lista record)';

-- Step 5: Update data_scope enum to simplify
ALTER TABLE `print_templates`
  MODIFY COLUMN `data_scope` enum('single', 'filtered', 'all') NOT NULL DEFAULT 'single' 
  COMMENT 'Scope dati: single (singolo), filtered (con filtri), all (tutti)';

-- Step 6: Simplify page_format options
ALTER TABLE `print_templates`
  MODIFY COLUMN `page_format` enum('A4', 'Letter') DEFAULT 'A4' 
  COMMENT 'Formato pagina: A4 o Letter';

-- Step 7: Update description comment
ALTER TABLE `print_templates`
  COMMENT='Template semplificati per generazione stampe e PDF';

-- Step 8: Remove templates with unsupported types (multi_page, relational)
-- Mark them as inactive instead of deleting to preserve history
UPDATE `print_templates` 
SET `is_active` = 0, 
    `description` = CONCAT('[DEPRECATO] ', IFNULL(`description`, ''))
WHERE `template_type` NOT IN ('single', 'list');

-- Step 9: Clean up templates that only have XML content (no HTML)
UPDATE `print_templates` 
SET `is_active` = 0,
    `description` = CONCAT('[DEPRECATO - Solo XML] ', IFNULL(`description`, ''))
WHERE (`html_content` IS NULL OR `html_content` = '') 
  AND `xml_content` IS NOT NULL 
  AND `xml_content` != '';

-- Note: Administrators can manually review and update deprecated templates in the UI
