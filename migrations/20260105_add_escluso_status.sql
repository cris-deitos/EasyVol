-- Migration: Add 'escluso' status to members and sanctions
-- Date: 2026-01-05
-- Description: Adds the 'escluso' (excluded) status to member_status and sanction_type enums
-- This allows tracking members who have been excluded from the association

-- Add 'escluso' to member_status in members table
ALTER TABLE `members` 
MODIFY COLUMN `member_status` ENUM('attivo', 'decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo') DEFAULT 'attivo';

-- Add 'escluso' to member_status in junior_members table
ALTER TABLE `junior_members` 
MODIFY COLUMN `member_status` ENUM('attivo', 'decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo') DEFAULT 'attivo';

-- Add 'escluso' to sanction_type in member_sanctions table
ALTER TABLE `member_sanctions` 
MODIFY COLUMN `sanction_type` ENUM('decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL;

-- Add 'escluso' to sanction_type in junior_member_sanctions table
ALTER TABLE `junior_member_sanctions` 
MODIFY COLUMN `sanction_type` ENUM('decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL;
