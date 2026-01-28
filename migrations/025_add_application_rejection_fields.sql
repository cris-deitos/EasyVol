-- Migration: Add rejection tracking fields to member_applications table
-- These fields were missing, causing application rejection to fail silently

-- Add rejected_by column to track who rejected the application
ALTER TABLE `member_applications`
ADD COLUMN `rejected_by` int(11) DEFAULT NULL AFTER `processed_by`;

-- Add rejected_at column to track when the application was rejected
ALTER TABLE `member_applications`
ADD COLUMN `rejected_at` timestamp NULL DEFAULT NULL AFTER `rejected_by`;

-- Add rejection_reason column to store the reason for rejection
ALTER TABLE `member_applications`
ADD COLUMN `rejection_reason` text DEFAULT NULL AFTER `rejected_at`;

-- Add index on rejected_by for potential joins with users table
ALTER TABLE `member_applications`
ADD KEY `idx_rejected_by` (`rejected_by`);
