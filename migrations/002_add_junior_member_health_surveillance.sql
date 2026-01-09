-- Migration: Add junior_member_health_surveillance table
-- Date: 2026-01-09
-- Description: Add health surveillance tracking for junior members (cadetti)

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
