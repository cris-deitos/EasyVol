-- Migration: Add PDF download token for public access
-- This allows public PDF downloads via a secure token without authentication

-- Add token column to member_applications table
ALTER TABLE `member_applications` 
ADD COLUMN `pdf_download_token` VARCHAR(64) DEFAULT NULL AFTER `pdf_file`,
ADD COLUMN `pdf_token_expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `pdf_download_token`;

-- Add index for token lookup
ALTER TABLE `member_applications`
ADD INDEX `idx_pdf_download_token` (`pdf_download_token`);
