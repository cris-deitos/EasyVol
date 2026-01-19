-- Migration: Add anomalies viewing permissions for members and junior members
-- This migration adds permissions to view anomalies for members and junior members

-- Add permission to view member anomalies
INSERT INTO permissions (module, action, description) 
VALUES ('members', 'view_anomalies', 'Visualizza anomalie soci')
ON DUPLICATE KEY UPDATE description = 'Visualizza anomalie soci';

-- Add permission to view junior member anomalies
INSERT INTO permissions (module, action, description) 
VALUES ('junior_members', 'view_anomalies', 'Visualizza anomalie cadetti')
ON DUPLICATE KEY UPDATE description = 'Visualizza anomalie cadetti';

-- Grant permissions to admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'admin' AND p.module = 'members' AND p.action = 'view_anomalies'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'admin' AND p.module = 'junior_members' AND p.action = 'view_anomalies'
ON DUPLICATE KEY UPDATE role_id = role_id;
