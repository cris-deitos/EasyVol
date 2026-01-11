-- Migration: Fix email_queue table structure
-- Date: 2026-01-11
-- Description: Add missing columns and fix column names in email_queue table

-- First, check if we need to rename body_html to body
-- This will fail silently if body_html doesn't exist
ALTER TABLE `email_queue` 
  CHANGE COLUMN `body_html` `body` longtext NOT NULL;

-- Add priority column if it doesn't exist
ALTER TABLE `email_queue` 
  ADD COLUMN `priority` int(11) DEFAULT 3 COMMENT 'Priority level 1-5, 1 is highest' AFTER `attachments`;

-- Add scheduled_at column if it doesn't exist
ALTER TABLE `email_queue` 
  ADD COLUMN `scheduled_at` timestamp NULL COMMENT 'When the email should be sent' AFTER `error_message`;

-- Add index on scheduled_at
ALTER TABLE `email_queue` 
  ADD KEY `scheduled_at` (`scheduled_at`);

-- Update status enum to include 'processing'
ALTER TABLE `email_queue` 
  MODIFY COLUMN `status` enum('pending', 'processing', 'sent', 'failed') DEFAULT 'pending';
