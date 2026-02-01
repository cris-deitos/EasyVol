-- Migration: Add permissions for membership applications management
-- This migration adds the necessary permissions for managing membership applications

-- Add applications permissions (if they don't exist)
INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'applications', 'view', 'Visualizzazione domande di iscrizione'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'applications' AND `action` = 'view');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'applications', 'edit', 'Modifica e approvazione domande di iscrizione'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'applications' AND `action` = 'edit');

INSERT INTO `permissions` (`module`, `action`, `description`) 
SELECT 'applications', 'delete', 'Eliminazione domande di iscrizione'
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `module` = 'applications' AND `action` = 'delete');

-- Grant applications permissions to admin role (role_id = 1)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p 
WHERE p.module = 'applications' AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp 
    WHERE rp.role_id = 1 AND rp.permission_id = p.id
);
