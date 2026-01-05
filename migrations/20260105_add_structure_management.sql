-- Structure Management System for Association Facilities
-- Migration: 20260105_add_structure_management.sql

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
