-- Migration: Add 'servizio' to event_type enum
-- Date: 2026-01-06
-- Description: Adds 'servizio' as a new event type option to the events table

-- Modify the event_type enum to include 'servizio'
ALTER TABLE `events` 
MODIFY COLUMN `event_type` ENUM('emergenza', 'esercitazione', 'attivita', 'servizio') NOT NULL;
