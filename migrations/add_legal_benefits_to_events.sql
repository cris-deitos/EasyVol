-- Migration: Add legal_benefits_recognized field to events table
-- This field indicates if legal benefits (Art. 39 and 40 D. Lgs. n. 1 del 2018) are recognized for the event

ALTER TABLE `events` 
ADD COLUMN `legal_benefits_recognized` ENUM('no', 'si') NOT NULL DEFAULT 'no' 
COMMENT 'Benefici di Legge riconosciuti (Art. 39 e 40 D. Lgs. n. 1 del 2018)' 
AFTER `province_access_code`;
