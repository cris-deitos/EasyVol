-- Migration: Add phone column to association table
-- Date: 2024-12-13
-- Description: Adds phone field to association table for use in print templates and email signatures

-- Add phone column after address_cap if it doesn't exist
ALTER TABLE `association` ADD COLUMN IF NOT EXISTS `phone` varchar(50) AFTER `address_cap`;
