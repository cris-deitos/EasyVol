-- Migration 014: Remove Advanced Dashboard Features
-- Rollback of migration 013
-- Removes dashboard avanzata, export universale, and related database objects

-- Drop views first (no dependencies)
DROP VIEW IF EXISTS `v_yoy_event_stats`;
DROP VIEW IF EXISTS `v_yoy_member_stats`;
DROP VIEW IF EXISTS `v_intervention_geographic_stats`;

-- Drop tables (with foreign keys, drop in correct order)
DROP TABLE IF EXISTS `dashboard_stats_cache`;
DROP TABLE IF EXISTS `dashboard_chart_config`;
DROP TABLE IF EXISTS `dashboard_kpi_config`;

-- Remove role_permissions entries for dashboard and export permissions
DELETE FROM `role_permissions` 
WHERE `permission_id` IN (
    SELECT id FROM `permissions` 
    WHERE (module = 'dashboard' AND action IN ('view', 'view_advanced', 'customize'))
    OR (action = 'export')
);

-- Remove permissions for dashboard advanced features
DELETE FROM `permissions` WHERE `module` = 'dashboard' AND `action` = 'view_advanced';
DELETE FROM `permissions` WHERE `module` = 'dashboard' AND `action` = 'customize';
DELETE FROM `permissions` WHERE `module` = 'dashboard' AND `action` = 'view';

-- Remove export permissions for all modules
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'members';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'junior_members';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'meetings';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'vehicles';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'warehouse';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'structure_management';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'training';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'events';
DELETE FROM `permissions` WHERE `action` = 'export' AND `module` = 'scheduler';

-- Commit changes
COMMIT;
