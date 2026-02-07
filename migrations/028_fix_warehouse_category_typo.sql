-- Migration: Fix warehouse_items category typo
-- This migration fixes a typo in the category value for electronic equipment
-- 
-- The category key was incorrectly spelled as:
--   'attrezzatura_elettronica_ed_accessoro' (wrong - ends with 'o')
-- 
-- It should be:
--   'attrezzatura_elettronica_ed_accessori' (correct - ends with 'i')
--
-- This migration updates any existing records with the old typo to the correct value

UPDATE `warehouse_items` 
SET `category` = 'attrezzatura_elettronica_ed_accessori' 
WHERE `category` = 'attrezzatura_elettronica_ed_accessoro';
