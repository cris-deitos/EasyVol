-- Add amount field to fee_payment_requests table
-- This allows tracking the amount paid when uploading fee payment receipts

ALTER TABLE `fee_payment_requests` 
ADD COLUMN `amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Importo pagato' 
AFTER `payment_date`;
