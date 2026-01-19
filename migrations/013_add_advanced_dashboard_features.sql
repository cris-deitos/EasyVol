-- Migration 013: Add Advanced Dashboard Features
-- Dashboard Statistiche Avanzate
--  - Grafici interattivi (Chart.js, D3.js)
--  - KPI personalizzabili
--  - Export dati in Excel/CSV per qualsiasi pagina/tabella
--  - Confronti anno su anno
--  - Mappe geografiche interventi

-- Add permissions for advanced dashboard features
INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'dashboard', 'view', 'Visualizza dashboard avanzata'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'dashboard' AND `action` = 'view');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'dashboard', 'view_advanced', 'Visualizza dashboard statistiche avanzate'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'dashboard' AND `action` = 'view_advanced');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'dashboard', 'customize', 'Personalizza KPI dashboard'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'dashboard' AND `action` = 'customize');

-- Add export permissions for all modules
INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'members', 'export', 'Esporta dati soci in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'members' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'junior_members', 'export', 'Esporta dati cadetti in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'junior_members' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'meetings', 'export', 'Esporta dati riunioni/assemblee in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'meetings' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'vehicles', 'export', 'Esporta dati mezzi in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'vehicles' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'warehouse', 'export', 'Esporta dati magazzino in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'warehouse' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'structure_management', 'export', 'Esporta dati strutture in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'structure_management' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'training', 'export', 'Esporta dati formazione in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'training' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'events', 'export', 'Esporta dati eventi/interventi in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'events' AND `action` = 'export');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'scheduler', 'export', 'Esporta dati scadenzario in Excel/CSV'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'scheduler' AND `action` = 'export');

-- Create table for custom KPI configurations per user
CREATE TABLE IF NOT EXISTS `dashboard_kpi_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `kpi_key` varchar(100) NOT NULL COMMENT 'Chiave identificativa del KPI',
  `display_order` int(11) DEFAULT 0 COMMENT 'Ordine di visualizzazione',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT 'Visibile o nascosto',
  `custom_label` varchar(255) DEFAULT NULL COMMENT 'Etichetta personalizzata',
  `custom_color` varchar(20) DEFAULT NULL COMMENT 'Colore personalizzato',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_kpi` (`user_id`, `kpi_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurazione KPI personalizzabili per dashboard utente';

-- Create table for dashboard chart configurations
CREATE TABLE IF NOT EXISTS `dashboard_chart_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `chart_type` varchar(50) NOT NULL COMMENT 'Tipo di grafico (line, bar, pie, etc)',
  `chart_key` varchar(100) NOT NULL COMMENT 'Chiave identificativa del grafico',
  `position` int(11) DEFAULT 0 COMMENT 'Posizione nel layout',
  `is_visible` tinyint(1) DEFAULT 1,
  `custom_title` varchar(255) DEFAULT NULL,
  `date_range` varchar(50) DEFAULT 'last_12_months' COMMENT 'Range temporale (last_month, last_6_months, last_12_months, year_to_date, all_time)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_chart` (`user_id`, `chart_key`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurazione grafici personalizzabili per dashboard utente';

-- Create table for caching dashboard statistics
CREATE TABLE IF NOT EXISTS `dashboard_stats_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL,
  `cache_data` mediumtext NOT NULL COMMENT 'JSON data',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache statistiche dashboard per performance';

-- Create view for year-over-year event statistics
CREATE OR REPLACE VIEW `v_yoy_event_stats` AS
SELECT 
    YEAR(start_date) as year,
    MONTH(start_date) as month,
    event_type,
    COUNT(*) as event_count,
    COUNT(DISTINCT id) as unique_events,
    SUM(CASE WHEN status = 'completato' THEN 1 ELSE 0 END) as completed_events,
    SUM(CASE WHEN status = 'in_corso' THEN 1 ELSE 0 END) as in_progress_events
FROM events
GROUP BY YEAR(start_date), MONTH(start_date), event_type;

-- Create view for year-over-year member statistics
CREATE OR REPLACE VIEW `v_yoy_member_stats` AS
SELECT 
    YEAR(created_at) as year,
    MONTH(created_at) as month,
    member_status,
    COUNT(*) as member_count
FROM members
GROUP BY YEAR(created_at), MONTH(created_at), member_status;

-- Create view for intervention geographic statistics
CREATE OR REPLACE VIEW `v_intervention_geographic_stats` AS
SELECT 
    i.id as intervention_id,
    i.title,
    e.municipality,
    e.start_date,
    e.event_type,
    i.latitude,
    i.longitude,
    COUNT(DISTINCT im.member_id) as volunteer_count,
    SUM(im.hours_worked) as total_hours
FROM interventions i
LEFT JOIN events e ON i.event_id = e.id
LEFT JOIN intervention_members im ON i.id = im.intervention_id
WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
GROUP BY i.id, i.title, e.municipality, e.start_date, e.event_type, i.latitude, i.longitude;

-- Grant permissions to admin role (role_id = 1) for all new features
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p 
WHERE p.module IN ('dashboard', 'members', 'junior_members', 'meetings', 'vehicles', 'warehouse', 
                    'structure_management', 'training', 'events', 'scheduler')
AND p.action IN ('view', 'view_advanced', 'customize', 'export')
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp 
    WHERE rp.role_id = 1 AND rp.permission_id = p.id
);
