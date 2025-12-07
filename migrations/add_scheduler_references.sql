-- Migration: Add reference tracking to scheduler_items
-- Purpose: Link scheduler items to their source records (qualifications, licenses, vehicles)
-- Date: 2025-12-07

-- Add columns for tracking the source of scheduler items
ALTER TABLE scheduler_items 
ADD COLUMN reference_type VARCHAR(50) NULL COMMENT 'Type of reference: qualification, license, insurance, inspection',
ADD COLUMN reference_id INT NULL COMMENT 'ID of the referenced record',
ADD INDEX idx_reference (reference_type, reference_id);

-- Update vehicle_maintenance enum to include 'revisione' type
ALTER TABLE vehicle_maintenance 
MODIFY COLUMN maintenance_type ENUM(
    'revisione',
    'manutenzione_ordinaria', 
    'manutenzione_straordinaria',
    'anomalie',
    'guasti',
    'riparazioni',
    'sostituzioni',
    'ordinaria',
    'straordinaria',
    'guasto',
    'riparazione',
    'sostituzione',
    'danno',
    'incidente'
) NOT NULL COMMENT 'Tipo di manutenzione - revisione added for automatic inspection expiry calculation';

-- Add status column to vehicle_maintenance if it doesn't exist
ALTER TABLE vehicle_maintenance 
ADD COLUMN status ENUM('operativo', 'in_manutenzione', 'fuori_servizio') NULL COMMENT 'Vehicle status after maintenance';

-- Add created_by column to vehicle_maintenance for audit
ALTER TABLE vehicle_maintenance 
ADD COLUMN created_by INT NULL COMMENT 'User who created the maintenance record',
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD KEY idx_created_by (created_by);
