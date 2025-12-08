-- Migration: Fix Critical System Issues
-- Date: 2025-12-08
-- Description: Adds missing columns and verifies tables exist

-- Add email_sent and email_sent_at columns to member_applications table
ALTER TABLE `member_applications` 
ADD COLUMN IF NOT EXISTS `email_sent` TINYINT(1) DEFAULT 0 COMMENT 'Flag indicating if email notification was sent' AFTER `pdf_file`,
ADD COLUMN IF NOT EXISTS `email_sent_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when email was sent' AFTER `email_sent`;

-- Note: The following tables should already exist in the database schema:
-- - users.must_change_password (added in add_password_reset_functionality.sql)
-- - email_logs (exists in database_schema.sql)
-- - password_reset_tokens (added in add_password_reset_functionality.sql)
-- - member_notes (added in add_member_notes_table.sql)
-- - junior_member_sanctions (added in add_operativo_sanction_and_junior_sanctions.sql)
-- - member_applications.pdf_file (exists in database_schema.sql)
-- - fee_payment_requests.amount (added in add_amount_to_fee_payment_requests.sql)

-- This migration only adds the missing email_sent fields that were not in the original schema
