-- Migration: Add digital signature support to documents table
-- Date: 2026-08-26
-- Description: Adds columns for storing digital signature information (CAdES/PAdES)

ALTER TABLE `documents` ADD COLUMN `has_signature` tinyint(1) DEFAULT 0 COMMENT 'Whether document has digital signatures' AFTER `tags`;
ALTER TABLE `documents` ADD COLUMN `signature_format` ENUM('CADES', 'PADES', 'UNKNOWN') DEFAULT NULL COMMENT 'Digital signature format (CADES or PADES)' AFTER `has_signature`;
ALTER TABLE `documents` ADD COLUMN `signature_count` int(11) DEFAULT 0 COMMENT 'Number of digital signatures in document' AFTER `signature_format`;
ALTER TABLE `documents` ADD COLUMN `signature_data` longtext COMMENT 'JSON array of signature information objects' AFTER `signature_count`;
ALTER TABLE `documents` ADD COLUMN `signature_validity` ENUM('valid', 'invalid', 'unknown') DEFAULT 'unknown' COMMENT 'Overall signature validity status' AFTER `signature_data`;
ALTER TABLE `documents` ADD COLUMN `signature_checked_at` timestamp NULL COMMENT 'When signature was last verified' AFTER `signature_validity`;

ALTER TABLE `documents` ADD KEY `idx_documents_has_signature` (`has_signature`);
ALTER TABLE `documents` ADD KEY `idx_documents_signature_validity` (`signature_validity`);
