-- Migration: Add Print Templates System
-- Description: Creates the print_templates table for the complete print/PDF generation system
-- Date: 2025-12-07

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
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template per generazione stampe e PDF';

-- Indici aggiuntivi per performance
CREATE INDEX idx_entity_active ON print_templates(entity_type, is_active);
CREATE INDEX idx_type_active ON print_templates(template_type, is_active);
