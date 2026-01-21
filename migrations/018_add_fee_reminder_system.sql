-- Migration 018: Add Fee Reminder System
-- Adds table to track fee payment reminder emails sent to members

-- Create table to track fee reminder emails sent
CREATE TABLE IF NOT EXISTS `fee_payment_reminders` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL COMMENT 'Anno di riferimento della quota',
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data invio promemoria',
  `sent_by` int(11) DEFAULT NULL COMMENT 'Utente che ha inviato i promemoria',
  `total_sent` int(11) DEFAULT 0 COMMENT 'Numero totale di email inviate',
  `status` enum('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  `notes` text COMMENT 'Note aggiuntive',
  PRIMARY KEY (`id`),
  KEY `year` (`year`),
  KEY `sent_at` (`sent_at`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `fk_fee_reminders_user` FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table to track individual reminder emails for each member
CREATE TABLE IF NOT EXISTS `fee_payment_reminder_members` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `reminder_id` bigint(20) NOT NULL COMMENT 'ID del batch di promemoria',
  `member_type` enum('adult', 'junior') NOT NULL COMMENT 'Tipo di socio',
  `member_id` int(11) NOT NULL COMMENT 'ID del socio',
  `registration_number` varchar(50) NOT NULL COMMENT 'Matricola del socio',
  `email` varchar(255) NOT NULL COMMENT 'Email del destinatario',
  `status` enum('pending', 'sent', 'failed') DEFAULT 'pending',
  `sent_at` timestamp NULL COMMENT 'Data invio effettivo',
  `error_message` text COMMENT 'Messaggio di errore se invio fallito',
  PRIMARY KEY (`id`),
  KEY `reminder_id` (`reminder_id`),
  KEY `member_type_id` (`member_type`, `member_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_fee_reminder_members` FOREIGN KEY (`reminder_id`) REFERENCES `fee_payment_reminders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
