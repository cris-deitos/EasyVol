-- Migration: Update meeting_attachments table for verbale and numbered allegati
-- Date: 2026-02-22
-- Description: Adds attachment_type, title, description, and progressive_number
--   columns to meeting_attachments to support:
--   1. Verbale firmato (signed minutes PDF) - attachment_type = 'verbale'
--   2. Numbered allegati with title/description - attachment_type = 'allegato'

ALTER TABLE `meeting_attachments`
  ADD COLUMN `attachment_type` ENUM('verbale', 'allegato') NOT NULL DEFAULT 'allegato' AFTER `meeting_id`,
  ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `file_type`,
  ADD COLUMN `description` text DEFAULT NULL AFTER `title`,
  ADD COLUMN `progressive_number` int(11) DEFAULT NULL AFTER `description`,
  ADD COLUMN `uploaded_by` int(11) DEFAULT NULL AFTER `progressive_number`,
  ADD CONSTRAINT `fk_meeting_attachments_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
