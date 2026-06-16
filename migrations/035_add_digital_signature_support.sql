-- Migration: Add digital signature support for meeting attachments
-- Date: 2026-02-23
-- Description: Adds columns to meeting_attachments to store digital signature metadata
--   Supports CADES and PADES formats with multiple signatures per document
--   Stores: signature validity, signer info, timestamp, signature count

ALTER TABLE `meeting_attachments`
  ADD COLUMN `has_signature` tinyint(1) DEFAULT 0 COMMENT 'Whether document has digital signatures',
  ADD COLUMN `signature_format` ENUM('CADES', 'PADES', 'UNKNOWN') DEFAULT NULL COMMENT 'Digital signature format (CADES or PADES)',
  ADD COLUMN `signature_count` int(11) DEFAULT 0 COMMENT 'Number of digital signatures in document',
  ADD COLUMN `signature_data` longtext COMMENT 'JSON array of signature information objects',
  ADD COLUMN `signature_validity` ENUM('valid', 'invalid', 'unknown') DEFAULT 'unknown' COMMENT 'Overall signature validity status',
  ADD COLUMN `signature_checked_at` timestamp NULL COMMENT 'When signature was last verified',
  ADD INDEX `idx_has_signature` (`has_signature`),
  ADD INDEX `idx_signature_validity` (`signature_validity`);
