-- Migration: Add "Approvazione del Consiglio Direttivo" sanction type
-- Date: 2025-12-27
-- Description: Adds 'approvazione_consiglio_direttivo' as a valid sanction type for both 
--              member_sanctions and junior_member_sanctions tables.
--              This represents the board approval step that comes after initial registration.

-- Add the new sanction type to member_sanctions table
ALTER TABLE `member_sanctions` 
MODIFY COLUMN `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL;

-- Add the new sanction type to junior_member_sanctions table
ALTER TABLE `junior_member_sanctions` 
MODIFY COLUMN `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL;
