-- Migration: Update Event Status - Remove 'aperto' and set default to 'in_corso'
-- Date: 2026-01-05
-- Description: 
--   - Updates existing events with status 'aperto' to 'in_corso'
--   - Modifies the status enum to remove 'aperto' option
--   - Changes the default status value to 'in_corso'

-- Step 1: Update all existing events with 'aperto' status to 'in_corso'
UPDATE events 
SET status = 'in_corso' 
WHERE status = 'aperto';

-- Step 2: Modify the events table to update the status enum
-- Remove 'aperto' from the enum and change default to 'in_corso'
ALTER TABLE events 
MODIFY COLUMN status enum('in_corso', 'concluso', 'annullato') DEFAULT 'in_corso';
