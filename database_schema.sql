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
  `must_change_password` tinyint(1) DEFAULT 0 COMMENT 'Flag to force password change on next login',
  `last_login` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `member_id` (`member_id`),
  KEY `role_id` (`role_id`)
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
  KEY `education_level` (`education_level`)
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
  `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo') NOT NULL,
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
-- JUNIOR MEMBERS (SOCI MINORENNI - CADETTI)
-- =============================================

CREATE TABLE IF NOT EXISTS `junior_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_number` varchar(50) UNIQUE,
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
  `sanction_type` enum('decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo') NOT NULL,
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
  `meeting_type` enum('assemblea_ordinaria', 'assemblea_straordinaria', 'consiglio_direttivo', 'altra_riunione') NOT NULL,
  `title` varchar(255) NOT NULL,
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
  `assignment_date` date NOT NULL,
  `return_date` date,
  `status` enum('assegnato', 'restituito') DEFAULT 'assegnato',
  `notes` text,
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

COMMIT;
