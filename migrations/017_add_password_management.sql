-- Migration 017: Add Password Management Feature
-- Adds password management functionality with:
-- 1. Password storage table
-- 2. Individual password permissions
-- 3. Module permissions for password management

-- =============================================
-- PASSWORD MANAGEMENT TABLES
-- =============================================

-- Create passwords table
CREATE TABLE IF NOT EXISTS `passwords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Titolo della password',
  `link` text DEFAULT NULL COMMENT 'Link di accesso',
  `username` varchar(255) DEFAULT NULL COMMENT 'Nome utente',
  `password` text NOT NULL COMMENT 'Password criptata',
  `notes` text DEFAULT NULL COMMENT 'Note aggiuntive',
  `created_by` int(11) NOT NULL COMMENT 'Utente che ha creato la password',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_title` (`title`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create password_permissions table for individual password access control
CREATE TABLE IF NOT EXISTS `password_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `password_id` int(11) NOT NULL COMMENT 'ID della password',
  `user_id` int(11) NOT NULL COMMENT 'ID dell\'utente con permesso',
  `can_view` tinyint(1) DEFAULT 1 COMMENT 'Può visualizzare',
  `can_edit` tinyint(1) DEFAULT 0 COMMENT 'Può modificare',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_password_user` (`password_id`, `user_id`),
  KEY `idx_password_id` (`password_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`password_id`) REFERENCES `passwords`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ADD PERMISSIONS
-- =============================================

-- Add permissions for password management module
INSERT INTO `permissions` (`module`, `action`, `description`)
SELECT 'password_management', 'view', 'Visualizzare la pagina di gestione password'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'password_management' AND `action` = 'view');

INSERT INTO `permissions` (`module`, `action`, `description`)
SELECT 'password_management', 'create', 'Creare nuove password'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'password_management' AND `action` = 'create');

INSERT INTO `permissions` (`module`, `action`, `description`)
SELECT 'password_management', 'edit', 'Modificare password esistenti'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'password_management' AND `action` = 'edit');

INSERT INTO `permissions` (`module`, `action`, `description`)
SELECT 'password_management', 'delete', 'Eliminare password'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'password_management' AND `action` = 'delete');
