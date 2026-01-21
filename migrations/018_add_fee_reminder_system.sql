-- Migration 018: Add Fee Reminder System
-- Simplified system using existing email_queue infrastructure

-- Drop redundant table if it exists (cleanup from PR #274)
DROP TABLE IF EXISTS `fee_payment_reminder_members`;

-- Create simplified table to track fee reminder batches (for cooldown tracking only)
CREATE TABLE IF NOT EXISTS `fee_payment_reminders` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL COMMENT 'Anno di riferimento della quota',
  `sent_by` int(11) DEFAULT NULL COMMENT 'Utente che ha inviato i promemoria',
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data invio promemoria',
  `total_queued` int(11) DEFAULT 0 COMMENT 'Numero totale di email accodate',
  PRIMARY KEY (`id`),
  KEY `year` (`year`),
  KEY `sent_at` (`sent_at`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `fk_fee_reminders_user` FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
