-- Migration 016: Extend Data Controller Appointments
-- Extends the data_controller_appointments table to support:
-- 1. Appointments for members (not just users)
-- 2. Appointments for external personnel (non-members/non-users)

-- =============================================
-- MODIFY DATA CONTROLLER APPOINTMENTS TABLE
-- =============================================

-- Make user_id nullable (since we can now have member_id or external person data)
ALTER TABLE `data_controller_appointments`
  MODIFY COLUMN `user_id` int(11) NULL COMMENT 'Utente nominato come responsabile (opzionale se member_id o esterni)';

-- Add member_id field to support members not linked to users
ALTER TABLE `data_controller_appointments`
  ADD COLUMN `member_id` int(11) NULL COMMENT 'Socio nominato (se non è utente)' AFTER `user_id`,
  ADD KEY `idx_member` (`member_id`),
  ADD CONSTRAINT `fk_dca_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE;

-- Add fields for external personnel (not members)
ALTER TABLE `data_controller_appointments`
  ADD COLUMN `external_person_name` varchar(255) NULL COMMENT 'Nome persona esterna' AFTER `member_id`,
  ADD COLUMN `external_person_surname` varchar(255) NULL COMMENT 'Cognome persona esterna' AFTER `external_person_name`,
  ADD COLUMN `external_person_tax_code` varchar(16) NULL COMMENT 'Codice fiscale persona esterna' AFTER `external_person_surname`,
  ADD COLUMN `external_person_birth_date` date NULL COMMENT 'Data di nascita persona esterna' AFTER `external_person_tax_code`,
  ADD COLUMN `external_person_birth_place` varchar(100) NULL COMMENT 'Luogo di nascita persona esterna' AFTER `external_person_birth_date`,
  ADD COLUMN `external_person_birth_province` varchar(2) NULL COMMENT 'Provincia di nascita persona esterna' AFTER `external_person_birth_place`,
  ADD COLUMN `external_person_gender` enum('M', 'F', 'other') NULL COMMENT 'Genere persona esterna' AFTER `external_person_birth_province`,
  ADD COLUMN `external_person_address` varchar(255) NULL COMMENT 'Indirizzo persona esterna' AFTER `external_person_gender`,
  ADD COLUMN `external_person_city` varchar(100) NULL COMMENT 'Città persona esterna' AFTER `external_person_address`,
  ADD COLUMN `external_person_province` varchar(2) NULL COMMENT 'Provincia persona esterna' AFTER `external_person_city`,
  ADD COLUMN `external_person_postal_code` varchar(10) NULL COMMENT 'CAP persona esterna' AFTER `external_person_province`,
  ADD COLUMN `external_person_phone` varchar(20) NULL COMMENT 'Telefono persona esterna' AFTER `external_person_postal_code`,
  ADD COLUMN `external_person_email` varchar(100) NULL COMMENT 'Email persona esterna' AFTER `external_person_phone`;

-- Add a check constraint to ensure at least one of user_id, member_id, or external_person_name is provided
-- Note: MySQL doesn't support CHECK constraints until 8.0.16, so we'll handle this in application logic
