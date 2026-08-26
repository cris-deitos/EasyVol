-- Migration: Add convention_attachments table
-- Supports file uploads (PDF, Word, images, etc.) with digital signature detection

CREATE TABLE IF NOT EXISTS `convention_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `convention_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `has_signature` tinyint(1) DEFAULT 0 COMMENT 'Whether document has digital signatures',
  `signature_format` ENUM('CADES', 'PADES', 'UNKNOWN') DEFAULT NULL COMMENT 'Digital signature format',
  `signature_count` int(11) DEFAULT 0 COMMENT 'Number of digital signatures',
  `signature_data` longtext COMMENT 'JSON array of signature information',
  `signature_validity` ENUM('valid', 'invalid', 'unknown') DEFAULT 'unknown' COMMENT 'Overall signature validity',
  `signature_checked_at` timestamp NULL COMMENT 'When signature was last verified',
  PRIMARY KEY (`id`),
  KEY `convention_id` (`convention_id`),
  KEY `idx_has_signature` (`has_signature`),
  FOREIGN KEY (`convention_id`) REFERENCES `conventions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
