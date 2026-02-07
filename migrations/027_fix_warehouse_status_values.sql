-- Migration: Fix warehouse_items status values
-- This migration ensures the warehouse_items status field has correct ENUM values
-- and fixes any potentially incorrect data that may have been inserted
-- 
-- The warehouse_items.status field should only contain:
-- - 'disponibile' (available)
-- - 'in_manutenzione' (under maintenance)  
-- - 'fuori_servizio' (out of service)
--
-- Note: The form previously incorrectly showed 'esaurito' and 'in_ordine' options
-- which would cause insert/update errors since they are not valid ENUM values.

-- Ensure the status column has the correct ENUM definition
-- This is a safety check - the schema should already be correct
ALTER TABLE `warehouse_items` 
MODIFY COLUMN `status` enum('disponibile', 'in_manutenzione', 'fuori_servizio') 
DEFAULT 'disponibile' 
COMMENT 'Status: disponibile=available, in_manutenzione=maintenance, fuori_servizio=out of service';

-- Update any records that might have NULL status to default value
UPDATE `warehouse_items` 
SET `status` = 'disponibile' 
WHERE `status` IS NULL;
