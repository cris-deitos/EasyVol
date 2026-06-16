-- EasyVol Database Schema
-- PHP 8.4 + MySQL Management System for Volunteer Associations
-- Version 1.1
-- 
-- MySQL Compatibility: MySQL 5.6+ and MySQL 8.x
-- Note: This schema is designed to work with both MySQL 5.6 and MySQL 8.
-- Timestamp columns use NULL DEFAULT for updated_at to ensure MySQL 5.6 compatibility.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- CONFIGURATION AND SYSTEM TABLES
-- =============================================

CREATE TABLE IF NOT EXISTS `config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `association` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `logo` varchar(255),
  `address_street` varchar(255),
  `address_number` varchar(20),
  `address_city` varchar(100),
  `address_province` varchar(5),
  `address_cap` varchar(10),
  `phone` varchar(50),
  `email` varchar(255),
  `pec` varchar(255),
  `provincial_civil_protection_email` varchar(255) DEFAULT NULL COMMENT 'Email dell\'Ufficio di Protezione Civile della Provincia',
  `tax_code` varchar(50),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- USER MANAGEMENT
-- =============================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(255),
  `member_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_operations_center_user` tinyint(1) DEFAULT 0 COMMENT 'Flag to identify operations center users (EasyCO)',
  `must_change_password` tinyint(1) DEFAULT 0 COMMENT 'Flag to force password change on next login',
  `last_login` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `member_id` (`member_id`),
  KEY `role_id` (`role_id`),
  KEY `idx_is_operations_center_user` (`is_operations_center_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'view, create, edit, delete, report',
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_action` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role_id`, `permission_id`),
  KEY `permission_id` (`permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_permission` (`user_id`, `permission_id`),
  KEY `permission_id` (`permission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ACTIVITY LOGGING
-- =============================================

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11),
  `action` varchar(100) NOT NULL,
  `module` varchar(100),
  `record_id` int(11),
  `description` text,
  `ip_address` varchar(45),
  `user_agent` text,
  `old_data` longtext COMMENT 'JSON: record data before the change (for update/delete)',
  `new_data` longtext COMMENT 'JSON: record data after the change (for create/update)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `module_record` (`module`, `record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- RATE LIMITING
-- =============================================

CREATE TABLE IF NOT EXISTS `rate_limit_attempts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL COMMENT 'IP address or username being rate limited',
  `action` varchar(50) NOT NULL COMMENT 'Action being attempted (login, reset_password, etc.)',
  `success` tinyint(1) DEFAULT 0 COMMENT 'Whether the attempt was successful',
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_identifier_action` (`identifier`, `action`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- CSV IMPORT LOGS
-- =============================================

CREATE TABLE IF NOT EXISTS `import_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_type` enum('soci', 'cadetti', 'mezzi', 'attrezzature') NOT NULL COMMENT 'Tipo di import',
  `file_name` varchar(255) NOT NULL COMMENT 'Nome file CSV caricato',
  `file_encoding` varchar(50) DEFAULT 'UTF-8' COMMENT 'Encoding rilevato del file',
  `total_rows` int(11) DEFAULT 0 COMMENT 'Totale righe nel CSV',
  `imported_rows` int(11) DEFAULT 0 COMMENT 'Righe importate con successo',
  `skipped_rows` int(11) DEFAULT 0 COMMENT 'Righe saltate (duplicati o errori)',
  `error_rows` int(11) DEFAULT 0 COMMENT 'Righe con errori',
  `status` enum('in_progress', 'completed', 'failed', 'partial') DEFAULT 'in_progress' COMMENT 'Stato import',
  `error_message` text COMMENT 'Messaggio di errore generale',
  `import_details` longtext COMMENT 'Dettagli import in formato JSON',
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL,
  `created_by` int(11) COMMENT 'User ID che ha eseguito import',
  PRIMARY KEY (`id`),
  KEY `import_type` (`import_type`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `started_at` (`started_at`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- MEETINGS AND ASSEMBLIES
-- =============================================

CREATE TABLE IF NOT EXISTS `meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_type` enum('assemblea_ordinaria', 'assemblea_straordinaria', 'consiglio_direttivo', 'riunione_capisquadra', 'riunione_nucleo', 'altra_riunione') NOT NULL,
  `progressive_number` int(11) DEFAULT NULL COMMENT 'Numero progressivo della riunione/assemblea per tipo',
  `title` varchar(255) DEFAULT NULL,
  `meeting_date` date NOT NULL,
  `start_time` time,
  `end_time` time,
  `location_type` enum('fisico', 'online') NOT NULL,
  `location_address` text,
  `online_details` text,
  `location` varchar(255),
  `convocator` varchar(255),
  `description` text,
  `status` enum('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
  `convocation_sent_at` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meeting_type_progressive` (`meeting_type`, `progressive_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meeting_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `member_id` int(11),
  `member_type` enum('adult', 'junior') DEFAULT 'adult',
  `junior_member_id` int(11),
  `participant_name` varchar(255),
  `role` varchar(100) COMMENT 'presidente, segretario, auditore, socio',
  `present` tinyint(1) DEFAULT 0,
  `attendance_status` enum('invited', 'present', 'absent', 'delegated') DEFAULT 'invited',
  `delegated_to` int(11),
  `invitation_sent_at` timestamp NULL,
  `response_date` timestamp NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`),
  KEY `member_id` (`member_id`),
  KEY `junior_member_id` (`junior_member_id`),
  KEY `delegated_to` (`delegated_to`),
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meeting_agenda` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `order_number` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text,
  `discussion` text,
  `has_voting` tinyint(1) DEFAULT 0,
  `voting_total` int(11) DEFAULT 0,
  `voting_in_favor` int(11) DEFAULT 0,
  `voting_against` int(11) DEFAULT 0,
  `voting_abstentions` int(11) DEFAULT 0,
  `voting_result` enum('approvato', 'respinto', 'non_votato') DEFAULT 'non_votato',
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`),
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meeting_minutes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `content_html` longtext,
  `pdf_file` varchar(255),
  `is_draft` tinyint(1) DEFAULT 1,
  `finalized_at` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`),
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meeting_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `attachment_type` ENUM('verbale', 'allegato') NOT NULL DEFAULT 'allegato' COMMENT 'verbale = signed minutes, allegato = numbered attachment',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100),
  `title` varchar(255) DEFAULT NULL COMMENT 'Title for allegato type',
  `description` text DEFAULT NULL COMMENT 'Description for allegato type',
  `progressive_number` int(11) DEFAULT NULL COMMENT 'Progressive number for allegato type',
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `has_signature` tinyint(1) DEFAULT 0 COMMENT 'Whether document has digital signatures',
  `signature_format` ENUM('CADES', 'PADES', 'UNKNOWN') DEFAULT NULL COMMENT 'Digital signature format (CADES or PADES)',
  `signature_count` int(11) DEFAULT 0 COMMENT 'Number of digital signatures in document',
  `signature_data` longtext COMMENT 'JSON array of signature information objects',
  `signature_validity` ENUM('valid', 'invalid', 'unknown') DEFAULT 'unknown' COMMENT 'Overall signature validity status',
  `signature_checked_at` timestamp NULL COMMENT 'When signature was last verified',
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`),
  KEY `idx_has_signature` (`has_signature`),
  KEY `idx_signature_validity` (`signature_validity`),
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;