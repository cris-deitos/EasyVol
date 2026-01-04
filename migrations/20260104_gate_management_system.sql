-- Gate Management System for People Counting at Large Events
-- Migration: 20260104_gate_management_system.sql

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
INSERT INTO `gate_system_config` (`id`, `is_active`) VALUES (1, 0);

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

-- Add permissions for gate management
INSERT IGNORE INTO `permissions` (`module`, `action`, `description`) VALUES
('gate_management', 'view', 'Visualizzare sistema gestione varchi'),
('gate_management', 'edit', 'Modificare configurazione sistema e varchi'),
('gate_management', 'delete', 'Eliminare varchi');
