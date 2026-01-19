-- Migration 014: Add GDPR Compliance Features
-- Compliance GDPR:
--  - Consensi privacy tracciati
--  - Scadenza consensi
--  - Export dati personali (diritto all'oblio)
--  - Log accessi dati sensibili
--  - Registro trattamenti
--  - Stampa nomina di responsabile del trattamento dati

-- =============================================
-- PRIVACY CONSENTS TABLE
-- =============================================
-- Tracks privacy consents for members and junior members
CREATE TABLE IF NOT EXISTS `privacy_consents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('member', 'junior_member') NOT NULL COMMENT 'Tipo entità: socio o cadetto',
  `entity_id` int(11) NOT NULL COMMENT 'ID del socio o cadetto',
  `consent_type` enum('privacy_policy', 'data_processing', 'sensitive_data', 'marketing', 'third_party_communication', 'image_rights') NOT NULL COMMENT 'Tipo di consenso',
  `consent_given` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Consenso dato (1=sì, 0=no)',
  `consent_date` date NOT NULL COMMENT 'Data del consenso',
  `consent_expiry_date` date DEFAULT NULL COMMENT 'Data scadenza consenso (se applicabile)',
  `consent_version` varchar(50) DEFAULT NULL COMMENT 'Versione informativa privacy',
  `consent_method` enum('paper', 'digital', 'verbal', 'implicit') DEFAULT 'paper' COMMENT 'Modalità acquisizione consenso',
  `consent_document_path` varchar(255) DEFAULT NULL COMMENT 'Path al documento firmato',
  `revoked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Consenso revocato',
  `revoked_date` date DEFAULT NULL COMMENT 'Data revoca consenso',
  `notes` text COMMENT 'Note aggiuntive',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_consent_type` (`consent_type`),
  KEY `idx_consent_expiry` (`consent_expiry_date`),
  KEY `idx_consent_given` (`consent_given`),
  KEY `idx_revoked` (`revoked`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracciamento consensi privacy GDPR';

-- =============================================
-- SENSITIVE DATA ACCESS LOG
-- =============================================
-- Logs all access to sensitive personal data
CREATE TABLE IF NOT EXISTS `sensitive_data_access_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Utente che ha effettuato l\'accesso',
  `entity_type` enum('member', 'junior_member', 'user') NOT NULL COMMENT 'Tipo di entità acceduta',
  `entity_id` int(11) NOT NULL COMMENT 'ID entità acceduta',
  `access_type` enum('view', 'edit', 'export', 'print', 'delete') NOT NULL COMMENT 'Tipo di accesso',
  `module` varchar(100) NOT NULL COMMENT 'Modulo da cui è stato effettuato l\'accesso',
  `data_fields` text COMMENT 'Campi dati sensibili acceduti (JSON)',
  `purpose` varchar(255) DEFAULT NULL COMMENT 'Finalità dell\'accesso',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `accessed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_access_type` (`access_type`),
  KEY `idx_accessed_at` (`accessed_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log accessi dati sensibili per GDPR';

-- =============================================
-- DATA PROCESSING REGISTRY
-- =============================================
-- Registry of data processing activities (Registro dei trattamenti)
CREATE TABLE IF NOT EXISTS `data_processing_registry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `processing_name` varchar(255) NOT NULL COMMENT 'Nome del trattamento',
  `processing_purpose` text NOT NULL COMMENT 'Finalità del trattamento',
  `data_categories` text NOT NULL COMMENT 'Categorie di dati trattati',
  `data_subjects` text NOT NULL COMMENT 'Categorie di interessati',
  `recipients` text DEFAULT NULL COMMENT 'Destinatari o categorie di destinatari',
  `third_country_transfer` tinyint(1) DEFAULT 0 COMMENT 'Trasferimento verso paesi terzi',
  `third_country_details` text DEFAULT NULL COMMENT 'Dettagli trasferimento paesi terzi',
  `retention_period` text DEFAULT NULL COMMENT 'Periodo di conservazione',
  `security_measures` text DEFAULT NULL COMMENT 'Misure di sicurezza tecniche e organizzative',
  `legal_basis` enum('consent', 'contract', 'legal_obligation', 'vital_interests', 'public_interest', 'legitimate_interest') NOT NULL COMMENT 'Base giuridica',
  `legal_basis_details` text DEFAULT NULL COMMENT 'Dettagli base giuridica',
  `data_controller` varchar(255) DEFAULT NULL COMMENT 'Titolare del trattamento',
  `data_processor` varchar(255) DEFAULT NULL COMMENT 'Responsabile del trattamento',
  `dpo_contact` varchar(255) DEFAULT NULL COMMENT 'Contatto DPO',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Trattamento attivo',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_legal_basis` (`legal_basis`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro dei trattamenti GDPR';

-- =============================================
-- DATA CONTROLLER APPOINTMENTS
-- =============================================
-- Tracks appointments of data controllers/processors
-- Nomina responsabili del trattamento dati
CREATE TABLE IF NOT EXISTS `data_controller_appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Utente nominato come responsabile',
  `appointment_type` enum('data_controller', 'data_processor', 'dpo', 'authorized_person') NOT NULL COMMENT 'Tipo di nomina',
  `appointment_date` date NOT NULL COMMENT 'Data nomina',
  `revocation_date` date DEFAULT NULL COMMENT 'Data revoca',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Nomina attiva',
  `scope` text COMMENT 'Ambito di competenza',
  `responsibilities` text COMMENT 'Responsabilità specifiche',
  `data_categories_access` text COMMENT 'Categorie di dati a cui può accedere',
  `appointment_document_path` varchar(255) DEFAULT NULL COMMENT 'Path al documento di nomina',
  `training_completed` tinyint(1) DEFAULT 0 COMMENT 'Formazione GDPR completata',
  `training_date` date DEFAULT NULL COMMENT 'Data formazione',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_appointment_type` (`appointment_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_appointment_date` (`appointment_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nomine responsabili trattamento dati GDPR';

-- =============================================
-- PERSONAL DATA EXPORT REQUESTS
-- =============================================
-- Tracks requests for personal data export (right to access)
CREATE TABLE IF NOT EXISTS `personal_data_export_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('member', 'junior_member') NOT NULL COMMENT 'Tipo entità',
  `entity_id` int(11) NOT NULL COMMENT 'ID entità',
  `request_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data richiesta',
  `requested_by_user_id` int(11) DEFAULT NULL COMMENT 'Utente che ha richiesto',
  `request_reason` text COMMENT 'Motivazione richiesta',
  `status` enum('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending' COMMENT 'Stato richiesta',
  `completed_date` datetime DEFAULT NULL COMMENT 'Data completamento',
  `export_file_path` varchar(255) DEFAULT NULL COMMENT 'Path al file esportato',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_status` (`status`),
  KEY `idx_request_date` (`request_date`),
  FOREIGN KEY (`requested_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Richieste export dati personali (diritto di accesso GDPR)';

-- =============================================
-- ADD GDPR FIELDS TO MEMBERS TABLE
-- =============================================
-- Add privacy consent tracking fields to members
ALTER TABLE `members` 
ADD COLUMN `privacy_consent_date` date DEFAULT NULL COMMENT 'Data consenso privacy' AFTER `notes`,
ADD COLUMN `privacy_consent_version` varchar(50) DEFAULT NULL COMMENT 'Versione informativa privacy' AFTER `privacy_consent_date`,
ADD COLUMN `data_processing_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso trattamento dati' AFTER `privacy_consent_version`,
ADD COLUMN `sensitive_data_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso dati sensibili' AFTER `data_processing_consent`,
ADD COLUMN `marketing_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso marketing' AFTER `sensitive_data_consent`,
ADD COLUMN `image_rights_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso diritti immagine' AFTER `marketing_consent`;

-- =============================================
-- ADD GDPR FIELDS TO JUNIOR_MEMBERS TABLE
-- =============================================
-- Add privacy consent tracking fields to junior_members
ALTER TABLE `junior_members` 
ADD COLUMN `privacy_consent_date` date DEFAULT NULL COMMENT 'Data consenso privacy tutore' AFTER `notes`,
ADD COLUMN `privacy_consent_version` varchar(50) DEFAULT NULL COMMENT 'Versione informativa privacy' AFTER `privacy_consent_date`,
ADD COLUMN `data_processing_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso trattamento dati tutore' AFTER `privacy_consent_version`,
ADD COLUMN `sensitive_data_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso dati sensibili tutore' AFTER `data_processing_consent`,
ADD COLUMN `marketing_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso marketing tutore' AFTER `sensitive_data_consent`,
ADD COLUMN `image_rights_consent` tinyint(1) DEFAULT 0 COMMENT 'Consenso diritti immagine tutore' AFTER `marketing_consent`;

-- =============================================
-- ADD WAREHOUSE PERMISSIONS
-- =============================================
-- Add missing CRUD permissions for warehouse module
INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'view', 'Visualizzare magazzino e inventario'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'view');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'create', 'Creare nuovi articoli in magazzino'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'create');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'edit', 'Modificare articoli in magazzino'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'edit');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'delete', 'Eliminare articoli in magazzino'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'delete');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'manage_movements', 'Gestire movimenti di magazzino'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'manage_movements');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'manage_maintenance', 'Gestire manutenzione articoli'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'manage_maintenance');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'manage_dpi', 'Gestire assegnazioni DPI'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'manage_dpi');

-- =============================================
-- ADD GDPR COMPLIANCE PERMISSIONS
-- =============================================
-- Add permissions for GDPR compliance features
INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gdpr_compliance', 'view', 'Visualizzare dati conformità GDPR'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gdpr_compliance' AND `action` = 'view');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gdpr_compliance', 'manage_consents', 'Gestire consensi privacy'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gdpr_compliance' AND `action` = 'manage_consents');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gdpr_compliance', 'export_personal_data', 'Esportare dati personali (diritto di accesso)'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gdpr_compliance' AND `action` = 'export_personal_data');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gdpr_compliance', 'view_access_logs', 'Visualizzare log accessi dati sensibili'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gdpr_compliance' AND `action` = 'view_access_logs');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gdpr_compliance', 'manage_processing_registry', 'Gestire registro trattamenti'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gdpr_compliance' AND `action` = 'manage_processing_registry');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gdpr_compliance', 'manage_appointments', 'Gestire nomine responsabili trattamento'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gdpr_compliance' AND `action` = 'manage_appointments');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'gdpr_compliance', 'print_appointment', 'Stampare nomina responsabile trattamento dati'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'gdpr_compliance' AND `action` = 'print_appointment');

-- =============================================
-- GRANT PERMISSIONS TO ADMIN ROLE
-- =============================================
-- Grant all GDPR and warehouse permissions to admin role (role_id = 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p 
WHERE p.module IN ('warehouse', 'gdpr_compliance')
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp 
    WHERE rp.role_id = 1 AND rp.permission_id = p.id
);
