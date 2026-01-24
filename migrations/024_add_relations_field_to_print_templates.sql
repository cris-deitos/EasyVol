-- Migration: Add relations field to print_templates table
-- This allows list templates to optionally specify which related tables to load
-- to avoid memory exhaustion when generating PDFs for large lists

-- Add relations column to print_templates table
ALTER TABLE `print_templates`
ADD COLUMN `relations` JSON NULL COMMENT 'Optional: Array of relation keys to load for list templates (e.g., ["contacts", "addresses"])' 
AFTER `entity_type`;

-- Update existing list templates that need related data
-- Templates that don't specify relations will not load any related data (default behavior)

-- Elenco Soci - Email e Cellulare (needs contacts for email/cellulare)
UPDATE `print_templates`
SET `relations` = JSON_ARRAY('contacts')
WHERE `name` = 'Elenco Soci - Email e Cellulare'
AND `template_type` = 'list';

-- Elenco Soci - Residenza e Domicilio (needs addresses)
UPDATE `print_templates`
SET `relations` = JSON_ARRAY('addresses')
WHERE `name` = 'Elenco Soci - Residenza e Domicilio'
AND `template_type` = 'list';

-- Elenco Soci - Intolleranze e Allergie (needs health)
UPDATE `print_templates`
SET `relations` = JSON_ARRAY('health')
WHERE `name` = 'Elenco Soci - Intolleranze e Allergie'
AND `template_type` = 'list';

-- Elenco Cadetti - Email e Cellulare (needs contacts)
UPDATE `print_templates`
SET `relations` = JSON_ARRAY('contacts')
WHERE `name` = 'Elenco Cadetti - Email e Cellulare'
AND `template_type` = 'list';

-- Elenco Cadetti - Tutori (needs guardians)
UPDATE `print_templates`
SET `relations` = JSON_ARRAY('guardians')
WHERE `name` = 'Elenco Cadetti - Tutori'
AND `template_type` = 'list';

-- Elenco Cadetti - Residenza e Domicilio (needs addresses)
UPDATE `print_templates`
SET `relations` = JSON_ARRAY('addresses')
WHERE `name` = 'Elenco Cadetti - Residenza e Domicilio'
AND `template_type` = 'list';

-- Elenco Cadetti - Intolleranze e Allergie (needs health)
UPDATE `print_templates`
SET `relations` = JSON_ARRAY('health')
WHERE `name` = 'Elenco Cadetti - Intolleranze e Allergie'
AND `template_type` = 'list';

-- Elenco Mezzi - Scadenze (might need maintenance/documents, checking template...)
-- Will be updated if needed after checking the template content

-- Note: Templates like "Libro Soci", "Libro Soci Cadetti", "Elenco Mezzi", "Elenco Eventi"
-- do NOT get relations specified, so they will NOT load any related data by default
-- This prevents memory exhaustion for large lists
