-- Migration 036: Add Conventions Module
-- Adds tables for managing conventions with entities and annual deadlines

-- Main conventions table
CREATE TABLE IF NOT EXISTS `conventions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convention entities (Comuni, Associazioni, etc.)
CREATE TABLE IF NOT EXISTS `convention_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `convention_id` int(11) NOT NULL,
  `denomination` varchar(255) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL COMMENT 'Comune, Associazione, Ente, etc.',
  `tax_code` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `pec` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `convention_id` (`convention_id`),
  FOREIGN KEY (`convention_id`) REFERENCES `conventions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convention annual deadlines
CREATE TABLE IF NOT EXISTS `convention_deadlines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `convention_id` int(11) NOT NULL,
  `day_of_month` int(2) NOT NULL,
  `month` int(2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `notify_to` varchar(255) DEFAULT NULL COMMENT 'Who to notify (email or role)',
  `advance_days` int(11) DEFAULT 7 COMMENT 'Days of advance notice',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `convention_id` (`convention_id`),
  FOREIGN KEY (`convention_id`) REFERENCES `conventions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add conventions permissions
INSERT IGNORE INTO `permissions` (`module`, `action`, `description`) VALUES
('conventions', 'view', 'Visualizzare le convenzioni'),
('conventions', 'create', 'Creare nuove convenzioni'),
('conventions', 'edit', 'Modificare le convenzioni'),
('conventions', 'delete', 'Eliminare le convenzioni');
