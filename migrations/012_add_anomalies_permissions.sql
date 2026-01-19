-- Migration: Add anomalies viewing permissions for members and junior members
-- This migration adds permissions to view anomalies for members and junior members
-- These permissions can be granted to any role or user through the permission management interface

-- Add permission to view member anomalies
INSERT INTO permissions (module, action, description) 
VALUES ('members', 'view_anomalies', 'Visualizza anomalie soci')
ON DUPLICATE KEY UPDATE description = 'Visualizza anomalie soci';

-- Add permission to view junior member anomalies
INSERT INTO permissions (module, action, description) 
VALUES ('junior_members', 'view_anomalies', 'Visualizza anomalie cadetti')
ON DUPLICATE KEY UPDATE description = 'Visualizza anomalie cadetti';

-- Note: Permissions are not automatically granted to any role.
-- Administrators can assign these permissions to roles or users as needed through the permission management interface.
