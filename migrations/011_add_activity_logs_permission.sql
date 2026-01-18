-- Migration: Add activity_logs permission
-- Description: Creates permission for viewing activity logs, allowing non-admin users to access logs

-- Add permission for viewing activity logs
INSERT IGNORE INTO permissions (module, action, description) 
VALUES ('activity_logs', 'view', 'Visualizzare i log delle attività del sistema');

-- Grant permission to admin role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin' 
  AND p.module = 'activity_logs' 
  AND p.action = 'view';
