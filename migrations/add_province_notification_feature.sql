-- Migration: Add Province Notification Feature
-- Description: Adds support for sending event notifications to Provincial Civil Protection
-- Date: 2026-01-03

-- Add provincial civil protection email to association table
ALTER TABLE `association`
ADD COLUMN `provincial_civil_protection_email` varchar(255) DEFAULT NULL 
COMMENT 'Email dell\'Ufficio Provinciale di Protezione Civile' 
AFTER `pec`;

-- Add province notification fields to events table
ALTER TABLE `events`
ADD COLUMN `province_email_sent` tinyint(1) DEFAULT 0 
COMMENT 'Flag: email inviata alla Provincia' 
AFTER `updated_at`,
ADD COLUMN `province_email_sent_at` timestamp NULL DEFAULT NULL 
COMMENT 'Data e ora invio email alla Provincia' 
AFTER `province_email_sent`,
ADD COLUMN `province_email_sent_by` int(11) DEFAULT NULL 
COMMENT 'ID utente che ha inviato l\'email' 
AFTER `province_email_sent_at`,
ADD COLUMN `province_email_status` varchar(50) DEFAULT NULL 
COMMENT 'Esito invio email (success/failure)' 
AFTER `province_email_sent_by`,
ADD COLUMN `province_access_token` varchar(64) DEFAULT NULL 
COMMENT 'Token per accesso protetto alla pagina Provincia' 
AFTER `province_email_status`,
ADD COLUMN `province_access_code` varchar(8) DEFAULT NULL 
COMMENT 'Codice alfanumerico di 8 cifre per autenticazione' 
AFTER `province_access_token`;

-- Add indexes for province notification fields
ALTER TABLE `events`
ADD KEY `idx_province_access_token` (`province_access_token`),
ADD KEY `idx_province_email_sent` (`province_email_sent`);

-- Add foreign key for province_email_sent_by
ALTER TABLE `events`
ADD CONSTRAINT `fk_events_province_email_sent_by` 
FOREIGN KEY (`province_email_sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
