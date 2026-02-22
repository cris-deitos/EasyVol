-- Migration: Update meeting participants fields in verbale template
-- Date: 2026-02-22
-- Description: Updates the "Verbale Riunione/Assemblea" print template to use
--   the new enriched participant fields produced by SimplePdfGenerator::prepareMeetingData():
--   - full_name instead of participant_full_name (Name Surname from DB lookup)
--   - role_label instead of role (role value with fallback '-')
--   Keeps backward-compatible aliases (participant_full_name, role, delegated_to_name)
--   populated by the generator for old custom templates.

UPDATE `print_templates`
SET `html_content` = REPLACE(REPLACE(`html_content`,
    '{{participant_full_name}}', '{{full_name}}'),
    '<td>{{role}}</td>', '<td>{{role_label}}</td>')
WHERE `entity_type` = 'meetings'
  AND `html_content` LIKE '%{{participant_full_name}}%';
