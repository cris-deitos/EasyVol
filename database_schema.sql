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
  `provincial_civil_protection_email` varchar(255) DEFAULT NULL COMMENT 'Email dell\'Ufficio Provinciale di Protezione Civile',
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
  `member_status` enum('attivo', 'decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo') DEFAULT 'attivo',
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
  `termination_date` date DEFAULT NULL COMMENT 'Data di cessazione (esclusione, dimissioni, decadenza)',
  `corso_base_completato` tinyint(1) DEFAULT 0 COMMENT 'Flag corso base protezione civile completato',
  `corso_base_anno` int(11) DEFAULT NULL COMMENT 'Anno completamento corso base protezione civile',
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
  `contact_type` enum('telefono_fisso', 'cellulare', 'email', 'pec', 'telegram_id') NOT NULL,
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
  `notes` text COMMENT 'Note aggiuntive sulla patente',
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
  `certification_number` varchar(100) DEFAULT NULL COMMENT 'Numero certificato',
  `notes` text COMMENT 'Note aggiuntive sul corso',
  `training_course_id` int(11) DEFAULT NULL COMMENT 'Reference to training_courses if from organized training',
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `training_course_id` (`training_course_id`),
  CONSTRAINT `fk_member_courses_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
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

-- =============================================
-- MEMBER FEES (QUOTE ASSOCIATIVE)
-- Tracks yearly membership fee payments for adult members
-- Used by:
-- - fee_payments.php "Richieste" tab to display payment requests
-- - fee_payments.php "Quote Non Versate" tab to identify members without payment
-- =============================================

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

CREATE TABLE IF NOT EXISTS `member_health_surveillance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `visit_date` date NOT NULL COMMENT 'Data della visita medica',
  `result` enum('Regolare', 'Con Limitazioni', 'Da Ripetere') NOT NULL COMMENT 'Esito della visita',
  `notes` text COMMENT 'Note sulla visita',
  `expiry_date` date NOT NULL COMMENT 'Data scadenza (default 2 anni dalla visita)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11),
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `expiry_date` (`expiry_date`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_sanctions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `sanction_date` date NOT NULL,
  `sanction_type` enum('decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL,
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
  `member_status` enum('attivo', 'decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo') DEFAULT 'attivo',
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
  `termination_date` date DEFAULT NULL COMMENT 'Data di cessazione (esclusione, dimissioni, decadenza)',
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
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
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
  `contact_type` enum('telefono_fisso', 'cellulare', 'email', 'telegram_id') NOT NULL,
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

CREATE TABLE IF NOT EXISTS `junior_member_health_surveillance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `visit_date` date NOT NULL COMMENT 'Data della visita medica',
  `result` enum('Regolare', 'Con Limitazioni', 'Da Ripetere') NOT NULL COMMENT 'Esito della visita',
  `notes` text COMMENT 'Note sulla visita',
  `expiry_date` date NOT NULL COMMENT 'Data scadenza (default 2 anni dalla visita)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11),
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11),
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  KEY `expiry_date` (`expiry_date`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- JUNIOR MEMBER FEES (QUOTE ASSOCIATIVE CADETTI)
-- Tracks yearly membership fee payments for junior members (cadetti)
-- Used by:
-- - fee_payments.php "Richieste" tab to display payment requests
-- - fee_payments.php "Quote Non Versate" tab to identify junior members without payment
-- =============================================

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
  `sanction_type` enum('decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', 'approvazione_consiglio_direttivo') NOT NULL,
  `reason` text,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `junior_member_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `junior_member_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `junior_member_id` (`junior_member_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE CASCADE
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
  `is_recurring` tinyint(1) DEFAULT 0 COMMENT 'Flag per scadenze ricorrenti',
  `recurrence_type` enum('yearly', 'monthly', 'weekly') DEFAULT NULL COMMENT 'Tipo ricorrenza: yearly (1 volta anno), monthly (stesso giorno ogni mese), weekly (stesso giorno ogni settimana)',
  `recurrence_end_date` date DEFAULT NULL COMMENT 'Data fine ricorrenza. NULL = ricorrenza a tempo indeterminato',
  `parent_recurrence_id` int(11) DEFAULT NULL COMMENT 'ID della scadenza ricorrente principale (NULL se è la principale)',
  `assigned_to` int(11),
  `completed_at` timestamp NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'Type of source record (qualification, license, insurance, inspection, vehicle_document)',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of source record for automatic sync',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `due_date` (`due_date`),
  KEY `status` (`status`),
  KEY `idx_reference` (`reference_type`, `reference_id`),
  KEY `idx_parent_recurrence` (`parent_recurrence_id`),
  KEY `idx_is_recurring` (`is_recurring`)
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

-- Add foreign key constraint from member_courses to training_courses
-- (added after training_courses table creation to avoid forward reference)
ALTER TABLE `member_courses`
  ADD CONSTRAINT `fk_member_courses_training` FOREIGN KEY (`training_course_id`) REFERENCES `training_courses`(`id`) ON DELETE SET NULL;

-- =============================================
-- EVENTS AND INTERVENTIONS
-- =============================================

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('emergenza', 'esercitazione', 'attivita', 'servizio') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `start_date` datetime NOT NULL,
  `end_date` datetime,
  `location` varchar(255),
  `latitude` DECIMAL(10, 8) NULL COMMENT 'Latitudine per georeferenziazione',
  `longitude` DECIMAL(11, 8) NULL COMMENT 'Longitudine per georeferenziazione',
  `full_address` VARCHAR(500) NULL COMMENT 'Indirizzo completo georeferenziato',
  `municipality` VARCHAR(100) NULL COMMENT 'Comune di riferimento',
  `status` enum('in_corso', 'concluso', 'annullato') DEFAULT 'in_corso',
  `created_by` int(11),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `province_email_sent` tinyint(1) DEFAULT 0 COMMENT 'Flag: email inviata alla Provincia',
  `province_email_sent_at` timestamp NULL DEFAULT NULL COMMENT 'Data e ora invio email alla Provincia',
  `province_email_sent_by` int(11) DEFAULT NULL COMMENT 'ID utente che ha inviato l\'email',
  `province_email_status` varchar(50) DEFAULT NULL COMMENT 'Esito invio email (success/failure)',
  `province_access_token` varchar(64) DEFAULT NULL COMMENT 'Token per accesso protetto alla pagina Provincia',
  `province_access_code` varchar(8) DEFAULT NULL COMMENT 'Codice alfanumerico di 8 cifre per autenticazione',
  `legal_benefits_recognized` ENUM('no', 'si') NOT NULL DEFAULT 'no' COMMENT 'Benefici di Legge riconosciuti (Art. 39 e 40 D. Lgs. n. 1 del 2018)',
  PRIMARY KEY (`id`),
  KEY `event_type` (`event_type`),
  KEY `status` (`status`),
  KEY `idx_municipality` (`municipality`),
  KEY `idx_coordinates` (`latitude`, `longitude`),
  KEY `idx_province_access_token` (`province_access_token`),
  KEY `idx_province_email_sent` (`province_email_sent`),
  CONSTRAINT `fk_events_province_email_sent_by` FOREIGN KEY (`province_email_sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
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
  `latitude` DECIMAL(10, 8) NULL COMMENT 'Latitudine per georeferenziazione',
  `longitude` DECIMAL(11, 8) NULL COMMENT 'Longitudine per georeferenziazione',
  `full_address` VARCHAR(500) NULL COMMENT 'Indirizzo completo georeferenziato',
  `municipality` VARCHAR(100) NULL COMMENT 'Comune di riferimento',
  `status` enum('in_corso', 'concluso', 'sospeso') DEFAULT 'in_corso',
  `report` text,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `idx_municipality` (`municipality`),
  KEY `idx_coordinates` (`latitude`, `longitude`),
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
  `dmr_id` varchar(50) DEFAULT NULL COMMENT 'DMR Radio ID',
  `device_type` varchar(100),
  `brand` varchar(100),
  `model` varchar(100),
  `serial_number` varchar(100),
  `notes` text,
  `status` enum('disponibile', 'assegnata', 'manutenzione', 'fuori_servizio') DEFAULT 'disponibile',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dmr_id` (`dmr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `radio_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `radio_id` int(11) NOT NULL,
  `member_id` int(11) NULL COMMENT 'Foreign key to members table for volunteer assignments',
  `junior_member_id` int(11) NULL COMMENT 'Foreign key to junior_members table for cadet assignments',
  `assignee_type` enum('member', 'cadet', 'external') DEFAULT 'member' COMMENT 'Type of assignee: member, cadet, or external personnel',
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
  KEY `junior_member_id` (`junior_member_id`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`junior_member_id`) REFERENCES `junior_members`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- OPERATIONS CENTER (EasyCO) TABLES
-- =============================================

-- 1. On-call/Availability Schedule Management
CREATE TABLE IF NOT EXISTS `on_call_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `role` enum('reperibile', 'backup') DEFAULT 'reperibile' COMMENT 'Role type: on-call or backup',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `start_datetime` (`start_datetime`),
  KEY `end_datetime` (`end_datetime`),
  KEY `idx_active_schedule` (`start_datetime`, `end_datetime`),
  KEY `idx_role` (`role`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Manages on-call rotation schedules for volunteers';

-- 2. Member Availability Status (Real-time)
CREATE TABLE IF NOT EXISTS `member_availability_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL COMMENT 'One status per member',
  `is_available` tinyint(1) DEFAULT 0 COMMENT 'Current availability status',
  `availability_start` datetime DEFAULT NULL COMMENT 'When availability started',
  `availability_end` datetime DEFAULT NULL COMMENT 'Expected end of availability',
  `notes` text COMMENT 'Additional notes about availability',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL COMMENT 'User who updated the status',
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_id` (`member_id`),
  KEY `is_available` (`is_available`),
  KEY `last_updated` (`last_updated`),
  KEY `updated_by` (`updated_by`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tracks real-time availability status of members';

-- 3. Missions (Emergency Operations and Interventions)
CREATE TABLE IF NOT EXISTS `missions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mission_code` varchar(100) NOT NULL UNIQUE COMMENT 'Unique mission identifier',
  `mission_type` enum('emergenza', 'esercitazione', 'servizio', 'assistenza', 'altro') NOT NULL COMMENT 'Type of mission',
  `title` varchar(255) NOT NULL,
  `description` text COMMENT 'Mission details and objectives',
  `location` varchar(255) DEFAULT NULL COMMENT 'Mission location',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `status` enum('planned', 'active', 'completed', 'cancelled') DEFAULT 'planned' COMMENT 'Current mission status',
  `priority` enum('bassa', 'media', 'alta', 'urgente') DEFAULT 'media' COMMENT 'Mission priority level',
  `requested_by` varchar(255) DEFAULT NULL COMMENT 'Organization or person requesting the mission',
  `coordinator_id` int(11) DEFAULT NULL COMMENT 'Member coordinating the mission',
  `notes` text COMMENT 'Additional operational notes',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mission_code` (`mission_code`),
  KEY `mission_type` (`mission_type`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `start_datetime` (`start_datetime`),
  KEY `coordinator_id` (`coordinator_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_active_missions` (`status`, `start_datetime`),
  FOREIGN KEY (`coordinator_id`) REFERENCES `members`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Core table for emergency missions and interventions';

-- 4. Mission Participants
CREATE TABLE IF NOT EXISTS `mission_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mission_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT NULL COMMENT 'Role in the mission (e.g., driver, medic, coordinator)',
  `status` enum('invited', 'confirmed', 'declined', 'present', 'absent') DEFAULT 'invited' COMMENT 'Participation status',
  `response_datetime` datetime DEFAULT NULL COMMENT 'When member responded to invitation',
  `arrival_datetime` datetime DEFAULT NULL COMMENT 'When member arrived at mission',
  `departure_datetime` datetime DEFAULT NULL COMMENT 'When member left the mission',
  `hours_worked` decimal(5,2) DEFAULT NULL COMMENT 'Total hours worked',
  `notes` text COMMENT 'Additional notes about participation',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `member_id` (`member_id`),
  KEY `status` (`status`),
  KEY `idx_mission_member` (`mission_id`, `member_id`),
  FOREIGN KEY (`mission_id`) REFERENCES `missions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tracks volunteers assigned to missions';

-- 5. Mission Vehicles
CREATE TABLE IF NOT EXISTS `mission_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mission_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL COMMENT 'When vehicle was assigned to mission',
  `returned_at` datetime DEFAULT NULL COMMENT 'When vehicle returned from mission',
  `notes` text COMMENT 'Vehicle usage notes, fuel consumption, issues',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `idx_active_assignments` (`mission_id`, `returned_at`),
  FOREIGN KEY (`mission_id`) REFERENCES `missions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tracks vehicles assigned to missions';

-- 6. Mission Equipment
CREATE TABLE IF NOT EXISTS `mission_equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mission_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'Reference to warehouse_items',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'Quantity of items assigned',
  `assigned_at` datetime NOT NULL COMMENT 'When equipment was assigned',
  `returned_at` datetime DEFAULT NULL COMMENT 'When equipment was returned',
  `notes` text COMMENT 'Condition, usage notes, damages',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `item_id` (`item_id`),
  KEY `idx_active_equipment` (`mission_id`, `returned_at`),
  FOREIGN KEY (`mission_id`) REFERENCES `missions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `warehouse_items`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tracks equipment assigned to missions';

-- 7. Mission Communications
CREATE TABLE IF NOT EXISTS `mission_communications` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `mission_id` int(11) NOT NULL,
  `communication_type` enum('radio', 'phone', 'sms', 'email', 'telegram', 'whatsapp', 'other') NOT NULL COMMENT 'Communication method',
  `message` text NOT NULL COMMENT 'Communication content',
  `sent_at` datetime NOT NULL COMMENT 'When communication was sent',
  `sent_by` int(11) NOT NULL COMMENT 'User who sent the communication',
  `recipients` longtext DEFAULT NULL COMMENT 'JSON array of recipients',
  `method` varchar(100) DEFAULT NULL COMMENT 'Additional method details',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mission_id` (`mission_id`),
  KEY `sent_by` (`sent_by`),
  KEY `sent_at` (`sent_at`),
  KEY `communication_type` (`communication_type`),
  FOREIGN KEY (`mission_id`) REFERENCES `missions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Logs all communications sent during missions';

-- 8. Operations Notes (Dashboard Quick Notes)
CREATE TABLE IF NOT EXISTS `operations_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `note_text` text NOT NULL COMMENT 'Note content',
  `priority` enum('bassa', 'media', 'alta', 'urgente') DEFAULT 'media' COMMENT 'Note priority',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether note is currently active/visible',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `priority` (`priority`),
  KEY `created_by` (`created_by`),
  KEY `idx_active_notes` (`is_active`, `priority`, `created_at`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Quick notes for operations center dashboard';

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
  `body` longtext NOT NULL,
  `attachments` text COMMENT 'JSON array of attachment paths',
  `priority` int(11) DEFAULT 3 COMMENT 'Priority level 1-5, 1 is highest',
  `status` enum('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `error_message` text,
  `scheduled_at` timestamp NULL COMMENT 'When the email should be sent',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `scheduled_at` (`scheduled_at`)
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

-- MEMBERS TEMPLATES
-- =============================================

-- 1. Tessera Socio (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tessera Socio',
    'Tessera associativa per singolo socio',
    'single', 'single', 'members',
    '<div style="width: 8.5cm; height: 5.4cm; border: 2px solid #333; padding: 0.3cm; display: flex; flex-direction: column;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.2cm; margin-bottom: 0.2cm;">
            <h3 style="margin: 0; font-size: 14pt;">TESSERA ASSOCIATIVA</h3>
            <p style="margin: 0; font-size: 9pt;">{{association_name}}</p>
        </div>
        
        <table style="width: 100%; font-size: 9pt;">
            <tr>
                <td style="width: 40%; padding: 0.1cm;"><strong>Nome:</strong></td>
                <td style="padding: 0.1cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Matricola:</strong></td>
                <td style="padding: 0.1cm;">{{registration_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Data Nasc.:</strong></td>
                <td style="padding: 0.1cm;">{{birth_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Tipo:</strong></td>
                <td style="padding: 0.1cm;">{{member_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Valida fino al:</strong></td>
                <td style="padding: 0.1cm;">31/12/{{current_year}}</td>
            </tr>
        </table>
        
        <div style="margin-top: auto; text-align: center; font-size: 7pt; color: #666;">
            Emessa il {{current_date}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        @page { size: 8.5cm 5.4cm; margin: 0; }
        body { margin: 0; }
    }',
    'custom', 'landscape', 0, 0, 1, 1
);

-- 2. Scheda Socio (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Socio',
    'Scheda completa del socio con tutti i dati',
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

        <h2>Informazioni Associative</h2>
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
            <tr>
                <td style="padding: 0.2cm; font-weight: bold;">Data Approvazione:</td>
                <td style="padding: 0.2cm;">{{approval_date}}</td>
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
        {{#each member_addresses}}
        <div style="border: 1px solid #ccc; padding: 0.5cm; margin-bottom: 0.5cm; background: #f9f9f9;">
            <p><strong>{{address_type}}:</strong> {{street}} {{number}}, {{cap}} {{city}} ({{province}})</p>
        </div>
        {{/each}}

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

        <h2>Corsi e Formazione</h2>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">
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

-- 3. Attestato di Partecipazione (single)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Attestato di Partecipazione',
    'Attestato di partecipazione per un socio',
    'single', 'single', 'members',
    '<div style="margin: 2cm; text-align: center;">
        <h1 style="font-size: 28pt; color: #333; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ATTESTATO DI PARTECIPAZIONE</h1>
        
        <div style="margin-top: 3cm; font-size: 16pt; line-height: 2;">
            <p>Si attesta che</p>
            
            <p style="font-size: 22pt; font-weight: bold; margin: 1cm 0;">
                {{first_name}} {{last_name}}
            </p>
            
            <p>Matricola: {{registration_number}}</p>
            
            <p style="margin-top: 2cm;">
                ha partecipato all\'evento/attività
            </p>
            
            <p style="font-size: 18pt; font-weight: bold; margin: 1cm 0;">
                _____________________________________________
            </p>
            
            <p>in data ___ / ___ / ______</p>
        </div>
        
        <div style="margin-top: 3cm; display: flex; justify-content: space-between; font-size: 12pt;">
            <div style="width: 45%;">
                <p><strong>Data:</strong> {{current_date}}</p>
            </div>
            <div style="width: 45%; text-align: right;">
                <p><strong>Il Presidente</strong></p>
                <p style="margin-top: 2cm;">_______________________</p>
            </div>
        </div>
    </div>',
    'body { font-family: "Times New Roman", serif; }
    @page { size: A4 landscape; }',
    'A4', 'landscape', 0, 0, 1, 0
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
    '{"filters": [{"field": "member_status", "label": "Stato Socio", "type": "select"}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Libro Soci</h2>
    </div>',
    1, 1
);

-- 5. Tessere Multiple (multi_page)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `is_active`, `is_default`
) VALUES (
    'Tessere Multiple',
    'Genera più tessere, una per pagina, per stampare in blocco',
    'multi_page', 'filtered', 'members',
    '<div style="width: 8.5cm; height: 5.4cm; border: 2px solid #333; padding: 0.3cm; display: flex; flex-direction: column;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 0.2cm; margin-bottom: 0.2cm;">
            <h3 style="margin: 0; font-size: 14pt;">TESSERA ASSOCIATIVA</h3>
            <p style="margin: 0; font-size: 9pt;">{{association_name}}</p>
        </div>
        
        <table style="width: 100%; font-size: 9pt;">
            <tr>
                <td style="width: 40%; padding: 0.1cm;"><strong>Nome:</strong></td>
                <td style="padding: 0.1cm;">{{first_name}} {{last_name}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Matricola:</strong></td>
                <td style="padding: 0.1cm;">{{registration_number}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Data Nasc.:</strong></td>
                <td style="padding: 0.1cm;">{{birth_date}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Tipo:</strong></td>
                <td style="padding: 0.1cm;">{{member_type}}</td>
            </tr>
            <tr>
                <td style="padding: 0.1cm;"><strong>Valida fino al:</strong></td>
                <td style="padding: 0.1cm;">31/12/{{current_year}}</td>
            </tr>
        </table>
        
        <div style="margin-top: auto; text-align: center; font-size: 7pt; color: #666;">
            Emessa il {{current_date}}
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        @page { size: 8.5cm 5.4cm; margin: 0; }
        body { margin: 0; }
    }',
    'custom', 'landscape', 0, 0, 1, 0
);

-- =============================================
-- VEHICLES TEMPLATES
-- =============================================

-- 6. Scheda Mezzo (relational)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `relations`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Scheda Mezzo',
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
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{date}}</td>
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

-- 7. Elenco Mezzi (list)
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

-- 8. Verbale Riunione (relational)
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
            <p><strong>Tipo:</strong> {{meeting_type}}</p>
            <p><strong>Luogo:</strong> {{location}}</p>
        </div>

        <h2 style="margin-top: 1cm;">Partecipanti</h2>
        <ul>
            {{#each meeting_participants}}
            <li>{{participant_name}} - {{role}}</li>
            {{/each}}
        </ul>

        <h2 style="margin-top: 1cm;">Ordine del Giorno</h2>
        <ol>
            {{#each meeting_agenda}}
            <li style="margin-bottom: 0.5cm;">
                <strong>{{subject}}</strong>
                <p style="margin-left: 1cm;">{{description}}</p>
            </li>
            {{/each}}
        </ol>

        <h2 style="margin-top: 1cm;">Resoconto</h2>
        <div style="border: 1px solid #ccc; padding: 0.5cm; min-height: 5cm; background: #f9f9f9;">
            {{description}}
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

-- 9. Foglio Presenze Riunione (relational)
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
                    <td style="padding: 0.5cm; border: 1px solid #ccc; height: 1.5cm;">{{@index}}</td>
                    <td style="padding: 0.5cm; border: 1px solid #ccc;">{{participant_name}}</td>
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

-- 10. Elenco Eventi (list)
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
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Fine</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{event_type}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{title}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{start_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{end_date}}</td>
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
        <h2 style="margin: 0;">{{association_name}} - Registro Eventi</h2>
        <p style="margin: 0;">Data: {{current_date}}</p>
    </div>',
    1, 1
);

-- =============================================
-- JUNIOR MEMBERS TEMPLATES
-- =============================================

-- 11. Libro Soci Cadetti (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Libro Soci Cadetti',
    'Elenco completo di tutti i soci minorenni con tutti i campi principali',
    'list', 'all', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">LIBRO SOCI MINORENNI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 9pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Matr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tutore</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Stato</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Iscr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Appr.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cess.</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{last_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_last_name}} {{guardian_first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{member_status}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{registration_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{approval_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{termination_date}}</td>
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
    '{"filters": [{"field": "member_status", "label": "Stato Socio", "type": "select"}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Libro Soci Minorenni</h2>
    </div>',
    1, 1
);

-- 12. Elenco Contatti Cadetti (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Elenco Contatti Cadetti',
    'Elenco soci minorenni con contatti del tutore per comunicazioni rapide',
    'list', 'filtered', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">ELENCO CONTATTI SOCI MINORENNI</h1>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 10pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000;">Cognome Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Data Nasc.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Tutore</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Telefono Tutore</th>
                    <th style="padding: 0.3cm; border: 1px solid #000;">Email Tutore</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;"><strong>{{last_name}} {{first_name}}</strong></td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{birth_date}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_last_name}} {{guardian_first_name}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc;">{{guardian_phone}}</td>
                    <td style="padding: 0.2cm; border: 1px solid #ccc; font-size: 9pt;">{{guardian_email}}</td>
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
    '{"filters": [{"field": "member_status", "label": "Stato", "type": "select", "options": ["attivo", "sospeso"]}]}',
    'A4', 'landscape', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}} - Contatti Soci Minorenni</h2>
        <p style="margin: 0; font-size: 10pt;">Stampato il {{current_date}}</p>
    </div>',
    1, 0
);

-- 13. Foglio Firma Cadetti (list)
INSERT INTO `print_templates` (
    `name`, `description`, `template_type`, `data_scope`, `entity_type`,
    `html_content`, `css_content`, `filter_config`, `page_format`, `page_orientation`,
    `show_header`, `show_footer`, `header_content`, `is_active`, `is_default`
) VALUES (
    'Foglio Firma Cadetti',
    'Foglio firme per presenza cadetti ad attività o eventi',
    'list', 'filtered', 'junior_members',
    '<div style="margin: 1cm;">
        <h1 style="text-align: center; border-bottom: 3px solid #333; padding-bottom: 0.5cm;">FOGLIO PRESENZE CADETTI</h1>
        
        <div style="margin: 1cm 0;">
            <p><strong>Attività:</strong> _______________________________________</p>
            <p><strong>Data:</strong> ______________________ <strong>Luogo:</strong> ______________________</p>
            <p><strong>Responsabile:</strong> _______________________________________</p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 1cm; font-size: 11pt;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 5%;">N.</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 15%;">Matricola</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 30%;">Cognome e Nome</th>
                    <th style="padding: 0.3cm; border: 1px solid #000; width: 50%;">Firma</th>
                </tr>
            </thead>
            <tbody>
                {{#each records}}
                <tr>
                    <td style="padding: 0.4cm; border: 1px solid #ccc; text-align: center;">{{@index_plus_1}}</td>
                    <td style="padding: 0.4cm; border: 1px solid #ccc;">{{registration_number}}</td>
                    <td style="padding: 0.4cm; border: 1px solid #ccc;"><strong>{{last_name}} {{first_name}}</strong></td>
                    <td style="padding: 0.4cm; border: 1px solid #ccc;">&nbsp;</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div style="margin-top: 2cm;">
            <p><strong>Firma del Responsabile:</strong> _______________________________________</p>
        </div>
    </div>',
    'body { font-family: Arial, sans-serif; }
    @media print {
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }',
    '{"filters": [{"field": "member_status", "label": "Stato", "type": "select", "options": ["attivo"]}]}',
    'A4', 'portrait', 1, 1,
    '<div style="text-align: center; margin-bottom: 0.5cm;">
        <h2 style="margin: 0;">{{association_name}}</h2>
    </div>',
    1, 0
);


-- TELEGRAM BOT INTEGRATION
-- =============================================

-- Telegram notification configuration
CREATE TABLE IF NOT EXISTS `telegram_notification_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(100) NOT NULL COMMENT 'Type of action: member_application, junior_application, fee_payment, vehicle_departure, vehicle_return, event_created, scheduler_expiry, vehicle_expiry, license_expiry, qualification_expiry, course_expiry, user_login, health_surveillance_expiry',
  `is_enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether notifications are enabled for this action',
  `message_template` text COMMENT 'Custom message template with placeholders',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telegram notification recipients (can be members or group IDs)
CREATE TABLE IF NOT EXISTS `telegram_notification_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL COMMENT 'Reference to telegram_notification_config',
  `recipient_type` enum('member', 'group') NOT NULL COMMENT 'Whether recipient is a member or a Telegram group',
  `member_id` int(11) DEFAULT NULL COMMENT 'Member ID if recipient_type is member',
  `telegram_group_id` varchar(255) DEFAULT NULL COMMENT 'Telegram group ID if recipient_type is group',
  `telegram_group_name` varchar(255) DEFAULT NULL COMMENT 'Friendly name for the group',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `config_id` (`config_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`config_id`) REFERENCES `telegram_notification_config`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration values
INSERT INTO `config` (`config_key`, `config_value`) VALUES
('telegram_bot_token', ''),
('telegram_bot_enabled', '0');

-- Insert default action configurations
INSERT INTO `telegram_notification_config` (`action_type`, `is_enabled`, `message_template`) VALUES
('member_application', 1, NULL),
('junior_application', 1, NULL),
('fee_payment', 1, NULL),
('vehicle_departure', 1, NULL),
('vehicle_return', 1, NULL),
('event_created', 1, NULL),
('scheduler_expiry', 1, NULL),
('vehicle_expiry', 1, NULL),
('license_expiry', 1, NULL),
('qualification_expiry', 1, NULL),
('course_expiry', 1, NULL),
('user_login', 1, NULL),
('health_surveillance_expiry', 1, NULL);

-- =============================================
-- DISPATCH SYSTEM TABLES
-- =============================================

-- TalkGroups Management
CREATE TABLE IF NOT EXISTS `dispatch_talkgroups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `talkgroup_id` varchar(50) NOT NULL COMMENT 'TalkGroup ID from radio network',
  `name` varchar(255) NOT NULL COMMENT 'TalkGroup name/description',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `talkgroup_id` (`talkgroup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Real-time Transmissions Tracking
CREATE TABLE IF NOT EXISTS `dispatch_transmissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) NOT NULL COMMENT 'Slot number (1 or 2)',
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `talkgroup_id` varchar(50) NOT NULL COMMENT 'TalkGroup ID being transmitted on',
  `transmission_start` datetime NOT NULL,
  `transmission_end` datetime DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether transmission is currently active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `talkgroup_id` (`talkgroup_id`),
  KEY `is_active` (`is_active`),
  KEY `transmission_start` (`transmission_start`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GPS Positions Tracking
CREATE TABLE IF NOT EXISTS `dispatch_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `latitude` decimal(10, 8) NOT NULL,
  `longitude` decimal(11, 8) NOT NULL,
  `altitude` decimal(8, 2) DEFAULT NULL COMMENT 'Altitude in meters',
  `speed` decimal(6, 2) DEFAULT NULL COMMENT 'Speed in km/h',
  `heading` decimal(5, 2) DEFAULT NULL COMMENT 'Heading in degrees',
  `accuracy` decimal(6, 2) DEFAULT NULL COMMENT 'GPS accuracy in meters',
  `timestamp` datetime NOT NULL COMMENT 'Position timestamp from GPS',
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When position was received by server',
  PRIMARY KEY (`id`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `timestamp` (`timestamp`),
  KEY `received_at` (`received_at`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Network Events Log
CREATE TABLE IF NOT EXISTS `dispatch_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) DEFAULT NULL COMMENT 'Slot number (1 or 2), NULL for system events',
  `event_type` varchar(50) NOT NULL COMMENT 'Event type: transmission, registration, deregistration, position, etc.',
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) DEFAULT NULL COMMENT 'DMR ID from radio network',
  `talkgroup_id` varchar(50) DEFAULT NULL COMMENT 'TalkGroup ID if applicable',
  `event_data` text COMMENT 'JSON data with additional event information',
  `event_timestamp` datetime NOT NULL COMMENT 'When the event occurred',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `event_type` (`event_type`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `talkgroup_id` (`talkgroup_id`),
  KEY `event_timestamp` (`event_timestamp`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audio Recordings
CREATE TABLE IF NOT EXISTS `dispatch_audio_recordings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) NOT NULL COMMENT 'Slot number (1 or 2)',
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `talkgroup_id` varchar(50) NOT NULL COMMENT 'TalkGroup ID',
  `file_path` varchar(500) NOT NULL COMMENT 'Path to audio file',
  `duration_seconds` int(11) DEFAULT NULL,
  `file_size_bytes` int(11) DEFAULT NULL,
  `recorded_at` datetime NOT NULL COMMENT 'When the audio was recorded',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `talkgroup_id` (`talkgroup_id`),
  KEY `recorded_at` (`recorded_at`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Text Messages
CREATE TABLE IF NOT EXISTS `dispatch_text_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot` tinyint(1) NOT NULL COMMENT 'Slot number (1 or 2)',
  `from_radio_id` int(11) DEFAULT NULL COMMENT 'Sender radio (foreign key)',
  `from_radio_dmr_id` varchar(50) NOT NULL COMMENT 'Sender DMR ID',
  `to_radio_dmr_id` varchar(50) DEFAULT NULL COMMENT 'Recipient radio DMR ID (NULL for broadcast)',
  `to_talkgroup_id` varchar(50) DEFAULT NULL COMMENT 'Recipient TalkGroup ID (NULL for direct)',
  `message_text` text NOT NULL,
  `message_timestamp` datetime NOT NULL COMMENT 'When the message was sent',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `slot` (`slot`),
  KEY `from_radio_id` (`from_radio_id`),
  KEY `from_radio_dmr_id` (`from_radio_dmr_id`),
  KEY `to_radio_dmr_id` (`to_radio_dmr_id`),
  KEY `to_talkgroup_id` (`to_talkgroup_id`),
  KEY `message_timestamp` (`message_timestamp`),
  FOREIGN KEY (`from_radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emergency Codes
CREATE TABLE IF NOT EXISTS `dispatch_emergency_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `radio_id` int(11) DEFAULT NULL COMMENT 'Foreign key to radio_directory',
  `radio_dmr_id` varchar(50) NOT NULL COMMENT 'DMR ID from radio network',
  `latitude` decimal(10, 8) DEFAULT NULL,
  `longitude` decimal(11, 8) DEFAULT NULL,
  `emergency_timestamp` datetime NOT NULL COMMENT 'When the emergency code was received',
  `acknowledged_by` int(11) DEFAULT NULL COMMENT 'User who acknowledged the emergency',
  `acknowledged_at` datetime DEFAULT NULL,
  `notes` text COMMENT 'Notes about the emergency',
  `status` enum('active', 'acknowledged', 'resolved') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `radio_id` (`radio_id`),
  KEY `radio_dmr_id` (`radio_dmr_id`),
  KEY `emergency_timestamp` (`emergency_timestamp`),
  KEY `status` (`status`),
  KEY `acknowledged_by` (`acknowledged_by`),
  FOREIGN KEY (`radio_id`) REFERENCES `radio_directory`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raspberry Pi Configuration
CREATE TABLE IF NOT EXISTS `dispatch_raspberry_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default Raspberry Pi configuration
INSERT INTO `dispatch_raspberry_config` (`config_key`, `config_value`, `description`) VALUES
('api_enabled', '1', 'Enable/disable API endpoints for Raspberry Pi'),
('api_key', '', 'API key for authentication (leave empty to generate)'),
('audio_storage_path', 'uploads/dispatch/audio/', 'Path to store audio recordings'),
('max_audio_file_size', '10485760', 'Maximum audio file size in bytes (10MB default)'),
('position_update_interval', '60', 'Expected position update interval in seconds'),
('position_inactive_threshold', '1800', 'Seconds after which a radio is considered inactive (30 minutes)');

-- =============================================
-- GATE MANAGEMENT SYSTEM TABLES
-- =============================================

-- System configuration table for gate management
CREATE TABLE IF NOT EXISTS `gate_system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_active` tinyint(1) DEFAULT 0 COMMENT 'Sistema Gestione Varchi attivo o disattivo',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration (system disabled by default)
INSERT INTO `gate_system_config` (`is_active`) 
SELECT 0 WHERE NOT EXISTS (SELECT 1 FROM `gate_system_config` LIMIT 1);

-- Gates table
CREATE TABLE IF NOT EXISTS `gates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gate_number` varchar(50) NOT NULL COMMENT 'Nr Varco',
  `name` varchar(255) NOT NULL COMMENT 'Nome varco',
  `status` enum('aperto', 'chiuso', 'non_gestito') DEFAULT 'non_gestito' COMMENT 'Stato: Aperto, Chiuso, Non Gestito',
  `latitude` decimal(10, 8) DEFAULT NULL COMMENT 'Latitudine GPS',
  `longitude` decimal(11, 8) DEFAULT NULL COMMENT 'Longitudine GPS',
  `limit_a` int(11) DEFAULT 0 COMMENT 'Limite A',
  `limit_b` int(11) DEFAULT 0 COMMENT 'Limite B',
  `limit_c` int(11) DEFAULT 0 COMMENT 'Limite C',
  `limit_manual` int(11) DEFAULT 0 COMMENT 'Limite Manuale',
  `limit_in_use` enum('a', 'b', 'c', 'manual') DEFAULT 'manual' COMMENT 'Limite in Uso: A, B, C o Manuale',
  `people_count` int(11) DEFAULT 0 COMMENT 'Numero Persone',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gate_number` (`gate_number`),
  KEY `status` (`status`),
  KEY `idx_coordinates` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gestione Varchi per conteggio persone';

-- Gate activity log for tracking changes
CREATE TABLE IF NOT EXISTS `gate_activity_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `gate_id` int(11) NOT NULL,
  `action_type` enum('add_person', 'remove_person', 'open_gate', 'close_gate', 'change_status', 'update_limit', 'set_manual_count', 'create_gate', 'delete_gate') NOT NULL,
  `previous_value` text COMMENT 'Valore precedente (JSON)',
  `new_value` text COMMENT 'Nuovo valore (JSON)',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `gate_id` (`gate_id`),
  KEY `action_type` (`action_type`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`gate_id`) REFERENCES `gates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log attività varchi';

-- Add permissions for gate management (if they don't exist)
INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gate_management', 'view', 'Visualizzare sistema gestione varchi'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gate_management' AND `action` = 'view');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gate_management', 'edit', 'Modificare configurazione sistema e varchi'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gate_management' AND `action` = 'edit');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gate_management', 'delete', 'Eliminare varchi'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gate_management' AND `action` = 'delete');

-- =============================================
-- STRUCTURE MANAGEMENT SYSTEM TABLES
-- =============================================

-- Structures table for managing association facilities/structures
CREATE TABLE IF NOT EXISTS `structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nome struttura',
  `type` varchar(255) DEFAULT NULL COMMENT 'Tipologia struttura',
  `full_address` varchar(500) DEFAULT NULL COMMENT 'Indirizzo completo',
  `latitude` decimal(10, 8) DEFAULT NULL COMMENT 'Latitudine GPS',
  `longitude` decimal(11, 8) DEFAULT NULL COMMENT 'Longitudine GPS',
  `owner` varchar(255) DEFAULT NULL COMMENT 'Proprietario',
  `owner_contacts` text COMMENT 'Contatti Proprietario',
  `contracts_deadlines` text COMMENT 'Contratti e Scadenze',
  `keys_codes` text COMMENT 'Chiavi e Codici',
  `notes` text COMMENT 'Note',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `type` (`type`),
  KEY `idx_coordinates` (`latitude`, `longitude`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gestione Strutture Associazione';

-- Add permissions for structure management (if they don't exist)
INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'structure_management', 'view', 'Visualizzare gestione strutture'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'structure_management' AND `action` = 'view');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'structure_management', 'edit', 'Modificare strutture'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'structure_management' AND `action` = 'edit');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'structure_management', 'delete', 'Eliminare strutture'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'structure_management' AND `action` = 'delete');

COMMIT;
