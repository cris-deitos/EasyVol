-- EasyVol Database Schema
-- PHP 8.4 + MySQL Management System for Volunteer Associations
-- Version 1.0
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `module_record` (`module`, `record_id`)
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
-- MEMBERS (SOCI MAGGIORENNI)
-- =============================================

CREATE TABLE IF NOT EXISTS `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_number` varchar(50) UNIQUE,
  `badge_number` varchar(20) DEFAULT NULL COMMENT 'Numero tesserino',
  `member_type` enum('ordinario', 'fondatore') DEFAULT 'ordinario',
  `member_status` enum('attivo', 'decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo') DEFAULT 'attivo',
  `volunteer_status` enum('operativo', 'non_operativo', 'in_formazione') DEFAULT 'in_formazione',
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `birth_place` varchar(255),
  `birth_province` varchar(5),
  `birth_date` date,
  `tax_code` varchar(50),
  `gender` enum('M', 'F'),
  `nationality` varchar(100) DEFAULT 'Italiana',
  `worker_type` enum('studente', 'dipendente_privato', 'dipendente_pubblico', 'lavoratore_autonomo', 'disoccupato', 'pensionato') DEFAULT NULL COMMENT 'Tipo di lavoratore',
  `education_level` enum('licenza_media', 'diploma_maturita', 'laurea_triennale', 'laurea_magistrale', 'dottorato') DEFAULT NULL COMMENT 'Titolo di studio',
  `registration_date` date,
  `approval_date` date,
  `photo` varchar(255),
  `photo_path` varchar(255),
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11),
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11),
  PRIMARY KEY (`id`),
  KEY `last_name` (`last_name`),
  KEY `member_status` (`member_status`),
  KEY `worker_type` (`worker_type`),
  KEY `education_level` (`education_level`),
  KEY `idx_badge_number` (`badge_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `address_type` enum('residenza', 'domicilio') NOT NULL,
  `street` varchar(255),
  `number` varchar(20),
  `city` varchar(100),
  `province` varchar(5),
  `cap` varchar(10),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `contact_type` enum('telefono_fisso', 'cellulare', 'email', 'pec') NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_education` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `degree_type` varchar(100),
  `institution` varchar(255),
  `year` int(11),
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_employment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `employer_name` varchar(255),
  `employer_address` varchar(255),
  `employer_city` varchar(100),
  `employer_phone` varchar(50),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_licenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `license_type` varchar(100) NOT NULL COMMENT 'patente A, B, C, D, E, nautica, muletto, etc',
  `license_number` varchar(100),
  `issue_date` date,
  `expiry_date` date,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_type` varchar(100) COMMENT 'base, DGR 1190/2019, altro',
  `completion_date` date,
  `expiry_date` date,
  `certificate_file` varchar(255),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `role_name` varchar(255) NOT NULL,
  `assigned_date` date,
  `end_date` date,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `availability_type` enum('comunale', 'provinciale', 'regionale', 'nazionale', 'internazionale') NOT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `payment_date` date,
  `amount` decimal(10,2),
  `receipt_file` varchar(255),
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11),
  `verified_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `year` (`year`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_health` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `health_type` enum('vegano', 'vegetariano', 'allergie', 'intolleranze', 'patologie') NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_sanctions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `sanction_date` date NOT NULL,
  `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL,
  `reason` text,
  `created_by` int(11),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100),
  `description` text,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- MEMBER VERIFICATION CODES (PORTALE SOCI)
-- =============================================

CREATE TABLE IF NOT EXISTS `member_verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL COMMENT 'Verification code (6-10 characters)',
  `email` varchar(255) NOT NULL COMMENT 'Email where code was sent',
  `expires_at` timestamp NOT NULL COMMENT 'Expiration time for the code',
  `used` tinyint(1) DEFAULT 0 COMMENT 'Whether the code has been used',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `code` (`code`),
  KEY `expires_at` (`expires_at`),
  KEY `used` (`used`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- JUNIOR MEMBERS (SOCI MINORENNI - CADETTI)
-- =============================================

CREATE TABLE IF NOT EXISTS `junior_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_number` varchar(50) UNIQUE,
  `member_type` enum('ordinario') DEFAULT 'ordinario',
  `member_status` enum('attivo', 'decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo') DEFAULT 'attivo',
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `birth_place` varchar(255),
  `birth_province` varchar(5),
  `birth_date` date,
  `tax_code` varchar(50),
  `gender` enum('M', 'F'),
  `nationality` varchar(100) DEFAULT 'Italiana',
  `registration_date` date,
  `approval_date` date,
  `photo` varchar(255),
  `photo_path` varchar(255),
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11),
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `junior_member_guardians` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `guardian_type` enum('padre', 'madre', 'tutore') NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `tax_code` varchar(50),
  `phone` varchar(50),
  `email` varchar(255),
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `junior_member_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `address_type` enum('residenza', 'domicilio') NOT NULL,
  `street` varchar(255),
  `number` varchar(20),
  `city` varchar(100),
  `province` varchar(5),
  `cap` varchar(10),
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `junior_member_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `contact_type` enum('telefono_fisso', 'cellulare', 'email') NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `junior_member_health` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `health_type` enum('vegano', 'vegetariano', 'allergie', 'intolleranze', 'patologie') NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `junior_member_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `payment_date` date,
  `amount` decimal(10,2),
  `receipt_file` varchar(255),
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11),
  `verified_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `junior_member_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100),
  `description` text,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- JUNIOR MEMBER SANCTIONS
-- =============================================

CREATE TABLE IF NOT EXISTS `junior_member_sanctions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `sanction_date` date NOT NULL,
  `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL,
  `reason` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- REGISTRATION REQUESTS (PENDING APPLICATIONS)
-- =============================================

CREATE TABLE IF NOT EXISTS `member_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_code` varchar(50) UNIQUE NOT NULL,
  `application_type` enum('adult', 'junior') NOT NULL,
  `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
  `application_data` longtext NOT NULL COMMENT 'JSON data with all member information',
  `pdf_file` varchar(255),
  `pdf_download_token` varchar(64) DEFAULT NULL COMMENT 'Token for public PDF download access',
  `pdf_token_expires_at` timestamp NULL DEFAULT NULL COMMENT 'Expiry for PDF download token',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL,
  `processed_by` int(11),
  `approved_at` timestamp NULL,
  `member_id` int(11) DEFAULT NULL COMMENT 'ID of created member after approval',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `application_type` (`application_type`),
  KEY `idx_pdf_download_token` (`pdf_download_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fee_payment_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_number` varchar(50),
  `last_name` varchar(100),
  `payment_year` int(11),
  `payment_date` date,
  `amount` decimal(10,2) DEFAULT NULL COMMENT 'Importo pagato',
  `receipt_file` varchar(255),
  `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL,
  `processed_by` int(11),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- MEETINGS AND ASSEMBLIES
-- =============================================

CREATE TABLE IF NOT EXISTS `meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_type` enum('assemblea_ordinaria', 'assemblea_straordinaria', 'consiglio_direttivo', 'riunione_capisquadra', 'riunione_nucleo', 'altra_riunione') NOT NULL,
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
  PRIMARY KEY (`id`)
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
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100),
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`),
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- VEHICLES MANAGEMENT
-- =============================================

CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_type` enum('veicolo', 'natante', 'rimorchio') NOT NULL,
  `name` varchar(255) NOT NULL,
  `license_plate` varchar(50),
  `brand` varchar(100),
  `model` varchar(100),
  `year` int(11),
  `serial_number` varchar(100),
  `status` enum('operativo', 'in_manutenzione', 'fuori_servizio', 'dismesso') DEFAULT 'operativo',
  `license_type` varchar(50) DEFAULT NULL COMMENT 'Required license types: A, B, C, D, E, Nautica or combinations (e.g., "B,E")',
  `insurance_expiry` date,
  `inspection_expiry` date,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `maintenance_type` enum('revisione', 'manutenzione_ordinaria', 'manutenzione_straordinaria', 'anomalie', 'guasti', 'riparazioni', 'sostituzioni', 'ordinaria', 'straordinaria', 'guasto', 'riparazione', 'sostituzione', 'danno', 'incidente') NOT NULL,
  `date` date NOT NULL,
  `description` text,
  `cost` decimal(10,2),
  `performed_by` varchar(255),
  `notes` text,
  `status` enum('operativo', 'in_manutenzione', 'fuori_servizio'),
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `idx_vehicle_maintenance_created_by` (`created_by`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `expiry_date` date,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- VEHICLE MOVEMENT MANAGEMENT
-- =============================================

CREATE TABLE IF NOT EXISTS `vehicle_checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL COMMENT 'Name of the checklist item',
  `item_type` enum('boolean', 'numeric', 'text') DEFAULT 'boolean' COMMENT 'Type of item: boolean (checkbox), numeric (quantity), text (notes)',
  `check_timing` enum('departure', 'return', 'both') NOT NULL COMMENT 'When to check: at departure, return, or both',
  `is_required` tinyint(1) DEFAULT 0 COMMENT 'Is this item required?',
  `display_order` int(11) DEFAULT 0 COMMENT 'Order in which items are displayed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `trailer_id` int(11) DEFAULT NULL COMMENT 'Trailer/rimorchio attached to the vehicle',
  `departure_datetime` datetime NOT NULL,
  `departure_km` decimal(10,2) DEFAULT NULL COMMENT 'Odometer at departure',
  `departure_fuel_level` enum('empty', '1/4', '1/2', '3/4', 'full') DEFAULT NULL COMMENT 'Fuel level at departure',
  `service_type` varchar(255) DEFAULT NULL COMMENT 'Type of service/mission',
  `destination` varchar(255) DEFAULT NULL COMMENT 'Mission destination',
  `authorized_by` varchar(255) DEFAULT NULL COMMENT 'Name of person who authorized the departure',
  `departure_notes` text COMMENT 'Notes from departure checklist',
  `departure_anomaly_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag if anomaly reported at departure',
  `departure_anomaly_email_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether anomaly email was sent',
  `return_datetime` datetime DEFAULT NULL,
  `return_km` decimal(10,2) DEFAULT NULL COMMENT 'Odometer at return',
  `return_fuel_level` enum('empty', '1/4', '1/2', '3/4', 'full') DEFAULT NULL COMMENT 'Fuel level at return',
  `return_notes` text COMMENT 'Notes from return checklist',
  `return_anomaly_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag if anomaly reported at return',
  `return_anomaly_email_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether anomaly email was sent at return',
  `traffic_violation_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag for possible traffic violations',
  `traffic_violation_email_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether traffic violation email was sent',
  `status` enum('in_mission', 'completed', 'completed_no_return') DEFAULT 'in_mission' COMMENT 'Movement status',
  `trip_duration_minutes` int(11) DEFAULT NULL COMMENT 'Trip duration in minutes (calculated)',
  `trip_km` decimal(10,2) DEFAULT NULL COMMENT 'Total kilometers traveled (calculated)',
  `created_by_member_id` int(11) NOT NULL COMMENT 'Member who created the departure',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `trailer_id` (`trailer_id`),
  KEY `created_by_member_id` (`created_by_member_id`),
  KEY `departure_datetime` (`departure_datetime`),
  KEY `status` (`status`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`trailer_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by_member_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_movement_drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `driver_type` enum('departure', 'return') NOT NULL COMMENT 'Driver for departure or return',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `movement_id` (`movement_id`),
  KEY `member_id` (`member_id`),
  UNIQUE KEY `movement_member_type` (`movement_id`, `member_id`, `driver_type`),
  FOREIGN KEY (`movement_id`) REFERENCES `vehicle_movements`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_movement_checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_id` int(11) NOT NULL,
  `checklist_item_id` int(11) DEFAULT NULL COMMENT 'Reference to vehicle_checklists item if exists',
  `item_name` varchar(255) NOT NULL COMMENT 'Name of the checked item',
  `check_timing` enum('departure', 'return') NOT NULL COMMENT 'When this was checked',
  `item_type` enum('boolean', 'numeric', 'text') DEFAULT 'boolean',
  `value_boolean` tinyint(1) DEFAULT NULL COMMENT 'Value for boolean items',
  `value_numeric` decimal(10,2) DEFAULT NULL COMMENT 'Value for numeric items',
  `value_text` text COMMENT 'Value for text items',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `movement_id` (`movement_id`),
  KEY `checklist_item_id` (`checklist_item_id`),
  FOREIGN KEY (`movement_id`) REFERENCES `vehicle_movements`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`checklist_item_id`) REFERENCES `vehicle_checklists`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- WAREHOUSE MANAGEMENT
-- =============================================

CREATE TABLE IF NOT EXISTS `warehouse_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) UNIQUE,
  `name` varchar(255) NOT NULL,
  `category` varchar(100),
  `description` text,
  `quantity` int(11) DEFAULT 0,
  `minimum_quantity` int(11) DEFAULT 0,
  `unit` varchar(50),
  `location` varchar(255),
  `qr_code` varchar(255),
  `barcode` varchar(255),
  `status` enum('disponibile', 'in_manutenzione', 'fuori_servizio') DEFAULT 'disponibile',
  `notes` text COMMENT 'Additional notes and comments',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouse_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('carico', 'scarico', 'assegnazione', 'restituzione', 'trasferimento') NOT NULL,
  `quantity` int(11) NOT NULL,
  `member_id` int(11),
  `destination` varchar(255),
  `notes` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`item_id`) REFERENCES `warehouse_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouse_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `maintenance_type` enum('ordinaria', 'straordinaria', 'guasto', 'riparazione', 'sostituzione') NOT NULL,
  `date` date NOT NULL,
  `description` text NOT NULL,
  `cost` decimal(10,2),
  `performed_by` varchar(255),
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  FOREIGN KEY (`item_id`) REFERENCES `warehouse_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dpi_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `assignment_date` date NOT NULL,
  `assigned_date` date,
  `return_date` date,
  `expiry_date` date,
  `status` enum('assegnato', 'restituito') DEFAULT 'assegnato',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`item_id`) REFERENCES `warehouse_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SCHEDULER AND DEADLINES
-- =============================================

CREATE TABLE IF NOT EXISTS `scheduler_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `due_date` date NOT NULL,
  `category` varchar(100),
  `priority` enum('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
  `status` enum('in_attesa', 'in_corso', 'completato', 'scaduto') DEFAULT 'in_attesa',
  `reminder_days` int(11) DEFAULT 7,
  `assigned_to` int(11),
  `completed_at` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `due_date` (`due_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for scheduler item email recipients
CREATE TABLE IF NOT EXISTS `scheduler_item_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheduler_item_id` int(11) NOT NULL,
  `recipient_type` enum('user', 'member', 'external') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `external_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `scheduler_item_id` (`scheduler_item_id`),
  KEY `user_id` (`user_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`scheduler_item_id`) REFERENCES `scheduler_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table to track annual member data verification emails (sent January 7th each year)
CREATE TABLE IF NOT EXISTS `annual_data_verification_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) DEFAULT NULL,
  `member_type` enum('adult', 'junior') NOT NULL DEFAULT 'adult',
  `junior_member_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `sent_at` timestamp NOT NULL,
  `year` int(11) NOT NULL,
  `status` enum('sent', 'failed', 'bounced') DEFAULT 'sent',
  `error_message` text,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `junior_member_id` (`junior_member_id`),
  KEY `year` (`year`),
  KEY `sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TRAINING MANAGEMENT
-- =============================================

CREATE TABLE IF NOT EXISTS `training_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(255) NOT NULL,
  `course_type` varchar(100),
  `sspc_course_code` varchar(50) DEFAULT NULL COMMENT 'Codice Corso SSPC',
  `sspc_edition_code` varchar(50) DEFAULT NULL COMMENT 'Codice Edizione SSPC',
  `description` text,
  `location` varchar(255),
  `start_date` date,
  `end_date` date,
  `instructor` varchar(255),
  `max_participants` int(11),
  `status` enum('pianificato', 'in_corso', 'completato', 'annullato') DEFAULT 'pianificato',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sspc_course_code` (`sspc_course_code`),
  KEY `idx_sspc_edition_code` (`sspc_edition_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `training_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `session_date` (`session_date`),
  FOREIGN KEY (`course_id`) REFERENCES `training_courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `training_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `registration_date` date,
  `attendance_status` enum('iscritto', 'presente', 'assente', 'ritirato') DEFAULT 'iscritto',
  `final_grade` varchar(50),
  `exam_passed` tinyint(1) DEFAULT NULL COMMENT 'Exam result: 1=passed, 0=failed, NULL=not taken',
  `exam_score` tinyint(2) DEFAULT NULL COMMENT 'Exam score from 1 to 10',
  `total_hours_attended` decimal(6,2) DEFAULT 0 COMMENT 'Total hours attended',
  `total_hours_absent` decimal(6,2) DEFAULT 0 COMMENT 'Total hours absent',
  `certificate_issued` tinyint(1) DEFAULT 0,
  `certificate_file` varchar(255),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`course_id`) REFERENCES `training_courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `training_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `member_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `present` tinyint(1) DEFAULT 0,
  `hours_attended` decimal(5,2) DEFAULT NULL COMMENT 'Hours attended in this session',
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `session_id` (`session_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`course_id`) REFERENCES `training_courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- EVENTS AND INTERVENTIONS
-- =============================================

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('emergenza', 'esercitazione', 'attivita') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `start_date` datetime NOT NULL,
  `end_date` datetime,
  `location` varchar(255),
  `status` enum('aperto', 'in_corso', 'concluso', 'annullato') DEFAULT 'aperto',
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_type` (`event_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT NULL COMMENT 'Role of the member in the event',
  `hours` decimal(5,2) DEFAULT 0.00 COMMENT 'Hours of service',
  `notes` text COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks member participation in events';

CREATE TABLE IF NOT EXISTS `event_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_name` varchar(255) DEFAULT NULL COMMENT 'Name of the driver',
  `hours` decimal(5,2) DEFAULT 0.00 COMMENT 'Hours of vehicle usage',
  `km_traveled` int(11) DEFAULT 0 COMMENT 'Kilometers traveled',
  `km_start` int(11) DEFAULT NULL COMMENT 'Starting odometer reading',
  `km_end` int(11) DEFAULT NULL COMMENT 'Ending odometer reading',
  `notes` text COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `vehicle_id` (`vehicle_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks vehicle usage in events';

CREATE TABLE IF NOT EXISTS `interventions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `start_time` datetime NOT NULL,
  `end_time` datetime,
  `location` varchar(255),
  `status` enum('in_corso', 'concluso', 'sospeso') DEFAULT 'in_corso',
  `report` text,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intervention_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `intervention_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `role` varchar(100),
  `hours_worked` decimal(5,2),
  PRIMARY KEY (`id`),
  KEY `intervention_id` (`intervention_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`intervention_id`) REFERENCES `interventions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intervention_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `intervention_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `km_start` int(11),
  `km_end` int(11),
  PRIMARY KEY (`id`),
  KEY `intervention_id` (`intervention_id`),
  KEY `vehicle_id` (`vehicle_id`),
  FOREIGN KEY (`intervention_id`) REFERENCES `interventions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- OPERATIONS CENTER (CENTRALE OPERATIVA)
-- =============================================

CREATE TABLE IF NOT EXISTS `radio_directory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `identifier` varchar(100),
  `device_type` varchar(100),
  `brand` varchar(100),
  `model` varchar(100),
  `serial_number` varchar(100),
  `notes` text,
  `status` enum('disponibile', 'assegnata', 'manutenzione', 'fuori_servizio') DEFAULT 'disponibile',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `radio_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `radio_id` int(11) NOT NULL,
  `member_id` int(11) NULL COMMENT 'Foreign key to members table for volunteer assignments',
  `assignee_first_name` varchar(100) NOT NULL,
  `assignee_last_name` varchar(100) NOT NULL,
  `assignee_phone` varchar(50),
  `assignee_organization` varchar(255),
  `assigned_by` int(11) NOT NULL,
  `assignment_date` datetime NOT NULL,
  `return_by` int(11),
  `return_date` datetime,
  `status` enum('assegnata', 'restituita') DEFAULT 'assegnata',
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `radio_id` (`radio_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DOCUMENT MANAGEMENT
-- =============================================

CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20),
  `mime_type` varchar(100),
  `tags` varchar(255),
  `uploaded_by` int(11),
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- EMAIL TEMPLATES AND NOTIFICATIONS
-- =============================================

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL UNIQUE,
  `subject` varchar(255) NOT NULL,
  `body_html` longtext NOT NULL,
  `placeholders` text COMMENT 'JSON array of available placeholders',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` longtext NOT NULL,
  `attachments` text COMMENT 'JSON array of attachment paths',
  `status` enum('pending', 'sent', 'failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext,
  `status` enum('sent', 'failed') NOT NULL,
  `error_message` text,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text,
  `link` varchar(255),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PRINT TEMPLATES
-- =============================================

CREATE TABLE IF NOT EXISTS `print_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nome template',
  `description` text COMMENT 'Descrizione template',
  `template_type` enum('single', 'list', 'multi_page', 'relational') NOT NULL DEFAULT 'single' COMMENT 'Tipo template: singolo, lista, multi-pagina, relazionale',
  `data_scope` enum('single', 'filtered', 'all', 'custom') NOT NULL DEFAULT 'single' COMMENT 'Scope dati: singolo record, filtrati, tutti, custom',
  `entity_type` varchar(100) NOT NULL COMMENT 'Tipo entità: members, junior_members, vehicles, meetings, etc',
  `html_content` LONGTEXT NOT NULL COMMENT 'Contenuto HTML del template',
  `css_content` TEXT COMMENT 'CSS personalizzato',
  `relations` JSON COMMENT 'Configurazione tabelle relazionali: ["member_contacts", "member_addresses"]',
  `filter_config` JSON COMMENT 'Configurazione filtri disponibili',
  `variables` JSON COMMENT 'Variabili template disponibili',
  `page_format` enum('A4', 'A3', 'Letter') DEFAULT 'A4' COMMENT 'Formato pagina',
  `page_orientation` enum('portrait', 'landscape') DEFAULT 'portrait' COMMENT 'Orientamento pagina',
  `show_header` tinyint(1) DEFAULT 1 COMMENT 'Mostra header',
  `show_footer` tinyint(1) DEFAULT 1 COMMENT 'Mostra footer',
  `header_content` TEXT COMMENT 'Contenuto header',
  `footer_content` TEXT COMMENT 'Contenuto footer',
  `watermark` varchar(255) COMMENT 'Testo watermark opzionale',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Template attivo',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Template di default',
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int(11),
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`),
  KEY `template_type` (`template_type`),
  KEY `is_active` (`is_active`),
  KEY `idx_entity_active` (`entity_type`, `is_active`),
  KEY `idx_type_active` (`template_type`, `is_active`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template per generazione stampe e PDF';

-- =============================================
-- STATISTICS AND REPORTS
-- =============================================

CREATE TABLE IF NOT EXISTS `statistics_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stat_key` varchar(100) NOT NULL,
  `stat_value` text,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stat_key` (`stat_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SESSIONS
-- =============================================

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int(11),
  `ip_address` varchar(45),
  `user_agent` text,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- INITIAL DATA AND CONFIGURATIONS
-- =============================================

-- Vehicle movement alert configuration
INSERT INTO `config` (`config_key`, `config_value`) 
VALUES ('vehicle_movement_alert_emails', '')
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`);

-- Vehicle movements permissions
INSERT INTO `permissions` (`module`, `action`, `description`)
VALUES 
  ('vehicle_movements', 'view', 'Visualizzare i movimenti dei veicoli'),
  ('vehicle_movements', 'create', 'Creare nuovi movimenti dei veicoli'),
  ('vehicle_movements', 'edit', 'Modificare i movimenti dei veicoli'),
  ('vehicle_movements', 'delete', 'Eliminare i movimenti dei veicoli')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Email configuration defaults
INSERT INTO `config` (`config_key`, `config_value`) VALUES
('email_enabled', '1'),
('email_method', 'smtp'),
('email_smtp_host', ''),
('email_smtp_port', '587'),
('email_smtp_username', ''),
('email_smtp_password', ''),
('email_smtp_encryption', 'tls'),
('email_smtp_auth', '1'),
('email_smtp_debug', '0'),
('email_from_address', 'noreply@example.com'),
('email_from_name', 'EasyVol'),
('email_reply_to', ''),
('email_return_path', ''),
('email_charset', 'UTF-8'),
('email_encoding', '8bit'),
('email_sendmail_params', ''),
('email_additional_headers', '')
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`);

-- Default Print Templates
-- Description: Inserts default templates for members, vehicles, and meetings
-- Date: 2025-12-07

-- =============================================
-- MEMBERS TEMPLATES
-- =============================================

-- 1. Certificato Iscrizione (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`,
    `is_active`, `is_default`
) VALUES (
    'Certificato di Iscrizione',
    'Certificato ufficiale di iscrizione all\'associazione',
    'single', 'single', 'members',
    '<div style="text-align: center; margin-top: 3cm;">
        <h1 style="font-size: 24pt; margin-bottom: 2cm;">CERTIFICATO DI ISCRIZIONE</h1>
        <div style="text-align: left; margin: 0 auto; max-width: 15cm; font-size: 14pt;">
            <p style="line-height: 1.8;">Si certifica che</p>
            <p style="text-align: center; font-size: 18pt; font-weight: bold; margin: 1cm 0;">
                {{first_name}} {{last_name}}
            </p>
            <p style="line-height: 1.8;">
                nato/a a {{birth_place}} ({{birth_province}}) il {{birth_date}}<br>
                C.F. {{tax_code}}
            </p>
            <p style="line-height: 1.8; margin-top: 1cm;">
                è iscritto/a a questa Associazione con matricola <strong>{{registration_number}}</strong><br>
                dal {{registration_date}} con la qualifica di <strong>Socio {{member_type}}</strong>
            </p>
            <p style="line-height: 1.8; margin-top: 1cm;">
                Stato attuale: <strong>{{member_status}}</strong>
            </p>
        </div>
        <div style="text-align: right; margin-top: 3cm;">
            <p>Data, _______________</p>
            <p style="margin-top: 2cm;">Il Presidente</p>
            <p>___________________________</p>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    h1 { border-bottom: 2px solid #333; padding-bottom: 0.5cm; }
    @media print { 
        .page-break { page-break-after: always; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 1cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
        <p style="margin: 0.2cm 0;">{{association_address}} - {{association_city}}</p>
    </div>',
    '<div style="text-align: center; margin-top: 1cm; font-size: 10pt; color: #666;">
        <p>Documento generato il {{current_date}}</p>
    </div>',
    1, 1
);

-- 2. Tessera Socio (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tessera Socio',
    'Tessera identificativa del socio - formato card',
    'single', 'single', 'members',
    '<div style="width: 8.5cm; height: 5.4cm; border: 2px solid #000; border-radius: 0.3cm; padding: 0.3cm; margin: 2cm auto;">
        <div style="text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 0.2cm; margin-bottom: 0.3cm;">
            <strong style="font-size: 12pt;">TESSERA SOCIO</strong>
        </div>
        <table style="width: 100%; font-size: 10pt;">
            <tr>
                <td style="padding: 0.1cm;"><strong>Matricola:</strong></td>
                <td style="padding: 0.1cm;">{{registration_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nome:</strong></td>
                <td style="padding: 0.1cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nato il:</strong></td>
                <td style="padding: 0.1cm;">{{birth_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Tipo:</strong></td>
                <td style="padding: 0.1cm;">{{member_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Iscrizione:</strong></td>
                <td style="padding: 0.1cm;">{{registration_date}}</td>
            </tr>
        </table>
        <div style="text-align: center; margin-top: 0.3cm; font-size: 8pt;">
            Valida per l\'anno in corso
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print { 
        @page { margin: 0.5cm; }
        body { margin: 0; }
    }',
    'A4', 'portrait', 0, 0, 1, 0
);

-- 3. Scheda Completa Socio (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Completa Socio',
    'Scheda dettagliata con tutti i dati del socio e tabelle correlate',
    'relational', 'single', 'members',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA SOCIO</h1>
        
        <h2 style="margin-top: 1cm;">Dati Anagrafici</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Matricola:</td>
                <td style="padding: 0.2cm;">{{registration_number}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Nome e Cognome:</td>
                <td style="padding: 0.2cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data di Nascita:</td>
                <td style="padding: 0.2cm;">{{birth_date}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Luogo di Nascita:</td>
                <td style="padding: 0.2cm;">{{birth_place}} ({{birth_province}})</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Codice Fiscale:</td>
                <td style="padding: 0.2cm;">{{tax_code}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Sesso:</td>
                <td style="padding: 0.2cm;">{{gender}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Nazionalità:</td>
                <td style="padding: 0.2cm;">{{nationality}}</td>
            </tr>
        </table>

        <h2>Stato Associativo</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Tipo Socio:</td>
                <td style="padding: 0.2cm;">{{member_type}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{member_status}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato Volontario:</td>
                <td style="padding: 0.2cm;">{{volunteer_status}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Data Iscrizione:</td>
                <td style="padding: 0.2cm;">{{registration_date}}</td>
            </tr>
        </table>

        <h2>Contatti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Valore</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_contacts}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{contact_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{value}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2>Indirizzi</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Indirizzo</th>
                    <th style="padding: 0.3cm; text-align: left;">Città</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_addresses}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{address_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{street}} {{number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{city}} ({{province}}) {{cap}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <div style="page-break-after: always;"></div>

        <h2>Patenti</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Numero</th>
                    <th style="padding: 0.3cm; text-align: left;">Rilascio</th>
                    <th style="padding: 0.3cm; text-align: left;">Scadenza</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_licenses}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{issue_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{expiry_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2>Corsi e Qualifiche</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Corso</th>
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Completamento</th>
                    <th style="padding: 0.3cm; text-align: left;">Scadenza</th>
                </tr>
            </thead>
            <tbody>
                {{#each member_courses}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{course_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{course_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{completion_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{expiry_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }
    @media print { 
        .page-break { page-break-after: always; }
    }',
    '["member_contacts", "member_addresses", "member_licenses", "member_courses"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- 4. Libro Soci (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Libro Soci',
    'Elenco completo di tutti i soci con tutti i campi principali',
    'list', 'all', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">LIBRO SOCI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Codice Fiscale</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Iscr.</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{tax_code}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"member_status": ["attivo", "sospeso", "dimesso"], "member_type": ["ordinario", "fondatore"]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- 5. Elenco Telefonico (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Telefonico Soci',
    'Lista soci con matricola, nome e contatti',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO TELEFONICO</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Nome e Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Telefono</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Email</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}} {{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">-</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">-</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt; color: #666;">
            <p>Nota: Per i contatti completi consultare la scheda dettagliata di ciascun socio</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- 6. Tessere Multiple (multi_page)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tessere Soci Multiple',
    'Genera più tessere in un unico PDF (una per pagina)',
    'multi_page', 'filtered', 'members',
    '<div style="width: 8.5cm; height: 5.4cm; border: 2px solid #000; border-radius: 0.3cm; padding: 0.3cm; margin: 5cm auto;">
        <div style="text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 0.2cm; margin-bottom: 0.3cm;">
            <strong style="font-size: 12pt;">TESSERA SOCIO</strong>
        </div>
        <table style="width: 100%; font-size: 10pt;">
            <tr>
                <td style="padding: 0.1cm;"><strong>Matricola:</strong></td>
                <td style="padding: 0.1cm;">{{registration_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nome:</strong></td>
                <td style="padding: 0.1cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Nato il:</strong></td>
                <td style="padding: 0.1cm;">{{birth_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Tipo:</strong></td>
                <td style="padding: 0.1cm;">{{member_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Iscrizione:</strong></td>
                <td style="padding: 0.1cm;">{{registration_date}}</td>
            </tr>
        </table>
        <div style="text-align: center; margin-top: 0.3cm; font-size: 8pt;">
            Valida per l\'anno in corso
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print { 
        @page { margin: 0; }
        body { margin: 0; }
    }',
    'A4', 'portrait', 0, 0, 1, 0
);

-- =============================================
-- VEHICLES TEMPLATES
-- =============================================

-- 7. Scheda Tecnica Mezzo (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Tecnica Mezzo',
    'Scheda completa del mezzo con storico manutenzioni',
    'relational', 'single', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA TECNICA MEZZO</h1>
        
        <h2 style="margin-top: 1cm;">Dati Identificativi</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Targa:</td>
                <td style="padding: 0.2cm;">{{license_plate}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Tipo Mezzo:</td>
                <td style="padding: 0.2cm;">{{vehicle_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Marca e Modello:</td>
                <td style="padding: 0.2cm;">{{brand}} {{model}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Anno:</td>
                <td style="padding: 0.2cm;">{{year}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{status}}</td>
            </tr>
        </table>

        <h2>Storico Manutenzioni</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Data</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Descrizione</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Costo</th>
                </tr>
            </thead>
            <tbody>
                {{#each vehicle_maintenance}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{maintenance_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{maintenance_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{description}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">€ {{cost}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }',
    '["vehicle_maintenance"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Parco Mezzi</h2>
    </div>',
    1, 1
);

-- 8. Elenco Mezzi (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Mezzi',
    'Lista completa dei mezzi dell\'associazione',
    'list', 'all', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO MEZZI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Targa</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Marca/Modello</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Anno</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; text-align: left;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_plate}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{vehicle_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{brand}} {{model}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{year}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- =============================================
-- MEETINGS TEMPLATES
-- =============================================

-- 9. Verbale Riunione (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `footer_content`, `is_active`, `is_default`
) VALUES (
    'Verbale di Riunione',
    'Verbale ufficiale della riunione con partecipanti',
    'relational', 'single', 'meetings',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">VERBALE DI RIUNIONE</h1>
        
        <div style="margin-top: 1cm;">
            <p><strong>Data:</strong> {{meeting_date}}</p>
            <p><strong>Ora:</strong> {{meeting_time}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
            <p><strong>Tipo:</strong> {{meeting_type}}</p>
        </div>

        <h2 style="margin-top: 1cm;">Partecipanti</h2>
        <ul>
            {{#each meeting_participants}}
            <li>{{member_name}} - {{role}}</li>
            {{/each}}
        </ul>

        <h2 style="margin-top: 1cm;">Ordine del Giorno</h2>
        <ol>
            {{#each meeting_agenda}}
            <li style="margin-bottom: 0.5cm;">
                <strong>{{title}}</strong>
                <p style="margin-left: 1cm;">{{description}}</p>
            </li>
            {{/each}}
        </ol>

        <h2 style="margin-top: 1cm;">Resoconto</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 5cm; background: #f9f9f9;">
            {{notes}}
        </div>

        <div style="margin-top: 2cm;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%;">
                        <p><strong>Il Segretario</strong></p>
                        <p style="margin-top: 2cm;">_________________________</p>
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <p><strong>Il Presidente</strong></p>
                        <p style="margin-top: 2cm;">_________________________</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.5; }
    h1 { font-size: 18pt; }
    h2 { font-size: 14pt; color: #333; }
    @media print { 
        .page-break { page-break-after: always; }
    }',
    '["meeting_participants", "meeting_agenda"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    '<div style="text-align: center; margin-top: 0.5cm; padding-top: 0.3cm; border-top: 1px solid #ccc; font-size: 9pt;">
        Pagina {{page}} - Documento generato il {{current_date}}
    </div>',
    1, 1
);

-- 10. Foglio Presenze Riunione (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Foglio Presenze Riunione',
    'Foglio firme per la presenza alla riunione',
    'relational', 'single', 'meetings',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">FOGLIO PRESENZE</h1>
        
        <div style="margin-top: 1cm; margin-bottom: 1cm;">
            <p><strong>Riunione del:</strong> {{meeting_date}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
            <p><strong>Tipo:</strong> {{meeting_type}}</p>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: left; width: 5%;">N.</th>
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: left; width: 35%;">Nome e Cognome</th>
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: left; width: 20%;">Ruolo</th>
                    <th style="padding: 0.5cm; border: 1px solid #000; text-align: center; width: 40%;">Firma</th>
                </tr>
            </thead>
            <tbody>
                {{#each meeting_participants}}
                <tr>
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;">{{index}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;">{{member_name}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;">{{role}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                </tr>
                {{/each}}
                <!-- Extra rows for additional attendees -->
                <tr>
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                </tr>
                <tr>
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;"></td>
                </tr>
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '["meeting_participants"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);

-- Note: The created_by field will be NULL as we're inserting default templates
-- In production, these should be updated to reference the admin user ID

-- Additional Print Templates for EasyVol
-- Description: Inserts additional standard templates for all entity types as requested
-- Date: 2025-12-13

-- =============================================
-- ADDITIONAL MEMBERS TEMPLATES
-- =============================================

-- Elenco Soci Attivi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Attivi',
    'Lista dei soci con stato attivo',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI ATTIVI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nascita</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Codice Fiscale</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Iscrizione</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{tax_code}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Elenco Soci Attivi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Elenco Soci Sospesi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Sospesi',
    'Lista dei soci sospesi, in congedo o in aspettativa',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI SOSPESI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Motivo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{volunteer_status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Elenco Soci Sospesi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Elenco Soci Attivi con Contatti
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Attivi con Contatti',
    'Lista soci attivi con numeri di cellulare ed email',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI ATTIVI CON CONTATTI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cellulare</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Email</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}} {{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{mobile}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 8pt;">{{email}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt; color: #666;">
            <p><strong>Nota:</strong> I contatti sono riservati e non possono essere divulgati a terzi senza autorizzazione.</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Contatti Soci Attivi</h2>
        <p style="margin: 0;">Data: {{current_date}} - RISERVATO</p>
    </div>',
    1, 0
);

-- Elenco Soci Attivi con Ruoli
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci Attivi con Ruoli',
    'Lista soci attivi con i rispettivi ruoli ricoperti',
    'relational', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI ATTIVI CON RUOLI</h1>
        
        <div style="margin-top: 1cm;">
            {{#each records}}
            <div style="margin-bottom: 1cm; padding: 0.5cm; border: 1px solid #ddd; background: #f9f9f9;">
                <h3 style="margin: 0; color: #333;">{{registration_number}} - {{last_name}} {{first_name}}</h3>
                <p style="margin: 0.3cm 0;"><strong>Tipo Socio:</strong> {{member_type}}</p>
                <p style="margin: 0;"><strong>Ruoli:</strong></p>
                <ul style="margin: 0.3cm 0;">
                    {{#each member_roles}}
                    <li>{{role_name}} (dal {{start_date}}{{#if end_date}} al {{end_date}}{{/if}})</li>
                    {{/each}}
                </ul>
            </div>
            {{/each}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h3 { font-size: 13pt; }
    @media print {
        .page-break { page-break-after: always; }
    }',
    '["member_roles"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Soci con Ruoli</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Elenco Soci con Intolleranze o Scelte Alimentari
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Soci con Intolleranze Alimentari',
    'Lista soci con intolleranze, allergie o scelte alimentari particolari',
    'relational', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO SOCI CON INTOLLERANZE O SCELTE ALIMENTARI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Intolleranze/Allergie</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Note Salute</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}} {{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">
                        {{#each member_health}}
                        {{#if allergies}}Allergie: {{allergies}}{{/if}}{{#if food_intolerances}}{{#if allergies}} | {{/if}}Intolleranze: {{food_intolerances}}{{/if}}
                        {{/each}}
                    </td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 8pt;">
                        {{#each member_health}}
                        {{health_notes}}
                        {{/each}}
                    </td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt; color: #666;">
            <p><strong>Nota:</strong> Queste informazioni sono sensibili e devono essere trattate con riservatezza.</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '["member_health"]',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Info Alimentari RISERVATO</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 0
);

-- Foglio Firma Assemblea con Deleghe
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Foglio Firma Assemblea con Deleghe',
    'Foglio presenze per assemblee con colonna per delegato',
    'list', 'filtered', 'members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">FOGLIO FIRMA ASSEMBLEA</h1>
        
        <div style="margin: 1cm 0;">
            <p><strong>Data Assemblea:</strong> __________________</p>
            <p><strong>Ora:</strong> __________________</p>
            <p><strong>Luogo:</strong> __________________________________________________</p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 5%;">N.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 10%;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 25%;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 10%;">Quota OK</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 25%;">Firma</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 25%;">Delega a</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; height: 1.2cm; text-align: center;">{{@index}}</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;">{{last_name}} {{first_name}}</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; text-align: center;">☐</td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                </tr>
                {{/each}}
                <!-- Righe vuote per ospiti -->
                <tr><td colspan="6" style="padding: 0.3cm; background: #f0f0f0; font-weight: bold;">Ospiti/Altri</td></tr>
                <tr>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; height: 1.2cm;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                </tr>
                <tr>
                    <td style="padding: 0.3cm; border: 1px solid #ccc; height: 1.2cm;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                    <td style="padding: 0.3cm; border: 1px solid #ccc;"></td>
                </tr>
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione</h2>
    </div>',
    1, 0
);

-- =============================================
-- JUNIOR MEMBERS TEMPLATES (Come Soci)
-- =============================================

-- Libro Soci Minorenni
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Libro Soci Minorenni',
    'Elenco completo di tutti i soci minorenni (cadetti)',
    'list', 'all', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">LIBRO SOCI MINORENNI (CADETTI)</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Luogo Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Codice Fiscale</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Iscr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_place}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{tax_code}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Soci Minorenni</h2>
    </div>',
    1, 0
);

-- Scheda Socio Minorenne Completa
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Socio Minorenne Completa',
    'Scheda dettagliata socio minorenne con genitori/tutori',
    'relational', 'single', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA SOCIO MINORENNE</h1>
        
        <h2 style="margin-top: 1cm;">Dati Anagrafici</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Matricola:</td>
                <td style="padding: 0.2cm;">{{registration_number}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Nome e Cognome:</td>
                <td style="padding: 0.2cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data di Nascita:</td>
                <td style="padding: 0.2cm;">{{birth_date}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Luogo di Nascita:</td>
                <td style="padding: 0.2cm;">{{birth_place}} ({{birth_province}})</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Codice Fiscale:</td>
                <td style="padding: 0.2cm;">{{tax_code}}</td>
            </tr>
        </table>

        <h2>Genitori/Tutori</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 1cm;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left;">Tipo</th>
                    <th style="padding: 0.3cm; text-align: left;">Nome</th>
                    <th style="padding: 0.3cm; text-align: left;">Cognome</th>
                    <th style="padding: 0.3cm; text-align: left;">Contatto</th>
                </tr>
            </thead>
            <tbody>
                {{#each junior_member_guardians}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{phone}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>

        <h2>Salute e Note</h2>
        {{#each junior_member_health}}
        <div style="border: 1px solid #ccc; padding: 0.5cm; background: #f9f9f9;">
            <p><strong>Allergie:</strong> {{allergies}}</p>
            <p><strong>Intolleranze:</strong> {{food_intolerances}}</p>
            <p><strong>Note:</strong> {{health_notes}}</p>
        </div>
        {{/each}}
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }',
    '["junior_member_guardians", "junior_member_health"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione</h2>
    </div>',
    1, 1
);

-- =============================================
-- VEHICLES TEMPLATES (Additional)
-- =============================================

-- Elenco Mezzi con Scadenze
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Mezzi con Scadenze',
    'Lista mezzi con scadenze assicurazione e revisione',
    'list', 'all', 'vehicles',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO MEZZI CON SCADENZE</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Targa</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Marca/Modello</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Scad. Assic.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Scad. Revisione</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{license_plate}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{vehicle_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{brand}} {{model}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{insurance_expiry}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{inspection_expiry}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 1cm; font-size: 9pt;">
            <p><strong>Legenda Stati:</strong></p>
            <ul>
                <li><strong>Operativo:</strong> Mezzo disponibile per utilizzo</li>
                <li><strong>In Manutenzione:</strong> Mezzo temporaneamente non disponibile</li>
                <li><strong>Fuori Servizio:</strong> Mezzo non utilizzabile</li>
            </ul>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Parco Mezzi</h2>
        <p style="margin: 0;">Scadenze Assicurazione e Revisione - {{current_date}}</p>
    </div>',
    1, 0
);

-- =============================================
-- EVENTS TEMPLATES
-- =============================================

-- Elenco Eventi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Eventi',
    'Lista eventi con tipologia, date e orari',
    'list', 'all', 'events',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO EVENTI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tipo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Titolo</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Inizio</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Ora Inizio</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Fine</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Ora Fine</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{event_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{title}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Registro Eventi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 1
);

-- Scheda Evento con Interventi
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Evento con Interventi',
    'Scheda dettagliata evento con tutti gli interventi registrati',
    'relational', 'single', 'events',
    '<div style="margin: 1cm;">
        <h1 style="border-bottom: 3px solid #333; padding-bottom: 0.3cm;">SCHEDA EVENTO</h1>
        
        <h2 style="margin-top: 1cm;">Informazioni Generali</h2>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1cm;">
            <tr>
                <td style="width: 30%; padding: 0.2cm; font-weight: bold;">Tipo Evento:</td>
                <td style="padding: 0.2cm;">{{event_type}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Titolo:</td>
                <td style="padding: 0.2cm;">{{title}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Descrizione:</td>
                <td style="padding: 0.2cm;">{{description}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Data e Ora Inizio:</td>
                <td style="padding: 0.2cm;">{{start_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data e Ora Fine:</td>
                <td style="padding: 0.2cm;">{{end_date}}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 0.2cm; font-weight: bold;">Luogo:</td>
                <td style="padding: 0.2cm;">{{location}}</td>
            </tr>
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Stato:</td>
                <td style="padding: 0.2cm;">{{status}}</td>
            </tr>
        </table>

        <h2>Interventi Registrati</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Titolo</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Descrizione</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Inizio</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Fine</th>
                    <th style="padding: 0.3cm; text-align: left; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each interventions}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;"><strong>{{title}}</strong></td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 9pt;">{{description}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_time}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{status}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>',
    'body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; color: #333; }
    h2 { font-size: 14pt; color: #555; margin-top: 0.8cm; margin-bottom: 0.3cm; }
    table { font-size: 10pt; }',
    '["interventions"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione - Scheda Evento</h2>
    </div>',
    1, 0
);

-- =============================================
-- MEETINGS TEMPLATES (Additional)
-- =============================================

-- Avviso di Assemblea
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Avviso di Assemblea',
    'Convocazione ufficiale per assemblea soci',
    'single', 'single', 'meetings',
    '<div style="margin: 2cm;">
        <h1 style="text-align: center; font-size: 20pt; margin-bottom: 2cm;">AVVISO DI CONVOCAZIONE ASSEMBLEA</h1>
        
        <div style="font-size: 13pt; line-height: 1.8; text-align: justify;">
            <p>I Soci sono convocati in Assemblea {{meeting_type}}</p>
            
            <p style="text-align: center; margin: 2cm 0; font-size: 14pt;">
                <strong>{{meeting_date}}</strong><br>
                alle ore <strong>{{meeting_time}}</strong><br>
                presso <strong>{{location}}</strong>
            </p>
            
            <p>L\'Assemblea è convocata in prima convocazione per l\'ora indicata e in seconda convocazione
            alle ore ___:___ del giorno successivo, nello stesso luogo.</p>
            
            <p style="margin-top: 2cm;">Si invitano tutti i Soci in regola con il versamento della quota associativa
            a intervenire personalmente o a farsi rappresentare da altro Socio mediante delega scritta.</p>
            
            <p style="margin-top: 2cm;">Cordiali saluti.</p>
        </div>
        
        <div style="text-align: right; margin-top: 3cm;">
            <p><strong>Il Presidente</strong></p>
            <p style="margin-top: 2cm;">_________________________</p>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    h1 { border-bottom: 3px solid #333; padding-bottom: 0.5cm; }',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 1cm;">
        <h2 style="margin: 0;">Associazione</h2>
        <p style="margin: 0.2cm 0;">Sede: ________________</p>
    </div>',
    1, 0
);

-- Ordine del Giorno
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Ordine del Giorno Riunione',
    'Ordine del giorno per riunione o assemblea',
    'relational', 'single', 'meetings',
    '<div style="margin: 1.5cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ORDINE DEL GIORNO</h1>
        
        <div style="margin-top: 1cm; margin-bottom: 1.5cm; font-size: 12pt;">
            <p><strong>Riunione:</strong> {{meeting_type}}</p>
            <p><strong>Data:</strong> {{meeting_date}} ore {{meeting_time}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
        </div>
        
        <div style="margin-top: 1.5cm;">
            <ol style="font-size: 12pt; line-height: 2;">
                {{#each meeting_agenda}}
                <li style="margin-bottom: 1cm;">
                    <strong style="font-size: 13pt;">{{title}}</strong>
                    {{#if description}}
                    <p style="margin-left: 1cm; margin-top: 0.3cm; font-size: 11pt; color: #555;">{{description}}</p>
                    {{/if}}
                </li>
                {{/each}}
            </ol>
        </div>
        
        <div style="margin-top: 2cm; text-align: right;">
            <p><strong>Il Segretario</strong></p>
            <p style="margin-top: 1.5cm;">_________________________</p>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    h1 { font-size: 18pt; }
    li { page-break-inside: avoid; }',
    '["meeting_agenda"]',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.3cm; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">Associazione</h2>
    </div>',
    1, 0
);

-- Note: These templates will need to be inserted through a migration or manual SQL execution
-- The created_by field will be NULL for default templates

COMMIT;
