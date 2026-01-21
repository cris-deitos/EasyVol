-- Migration: Add newsletter management system
-- Created: 2026-01-21
-- Description: Adds tables for internal newsletter management with draft, schedule, and send capabilities

-- Add newsletters table
CREATE TABLE IF NOT EXISTS `newsletters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(500) NOT NULL COMMENT 'Newsletter subject',
  `body_html` longtext NOT NULL COMMENT 'HTML content of newsletter',
  `reply_to` varchar(255) DEFAULT NULL COMMENT 'Reply-to email address',
  `status` enum('draft', 'scheduled', 'sent', 'failed') NOT NULL DEFAULT 'draft' COMMENT 'Newsletter status',
  `scheduled_at` timestamp NULL COMMENT 'When newsletter should be sent',
  `sent_at` timestamp NULL COMMENT 'When newsletter was actually sent',
  `send_result` text COMMENT 'Result of send operation (success count, failed count, etc)',
  `recipient_filter` JSON COMMENT 'Filter for recipients: {"type": "all_members|all_cadets|all_cadets_with_parents|custom", "ids": []}',
  `total_recipients` int(11) DEFAULT 0 COMMENT 'Total number of recipients',
  `sent_count` int(11) DEFAULT 0 COMMENT 'Number of emails successfully sent',
  `failed_count` int(11) DEFAULT 0 COMMENT 'Number of emails that failed',
  `created_by` int(11) NOT NULL COMMENT 'User who created the newsletter',
  `sent_by` int(11) DEFAULT NULL COMMENT 'User who sent the newsletter',
  `cloned_from` int(11) DEFAULT NULL COMMENT 'ID of newsletter this was cloned from',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `scheduled_at` (`scheduled_at`),
  KEY `created_by` (`created_by`),
  KEY `sent_by` (`sent_by`),
  KEY `cloned_from` (`cloned_from`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`sent_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`cloned_from`) REFERENCES `newsletters`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add newsletter_attachments table
CREATE TABLE IF NOT EXISTS `newsletter_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `newsletter_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Original filename',
  `filepath` varchar(500) NOT NULL COMMENT 'Path to file on server',
  `filesize` bigint(20) NOT NULL COMMENT 'File size in bytes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `newsletter_id` (`newsletter_id`),
  FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add newsletter_recipients table (for tracking individual sends)
CREATE TABLE IF NOT EXISTS `newsletter_recipients` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `newsletter_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL COMMENT 'Recipient email address',
  `recipient_name` varchar(255) DEFAULT NULL COMMENT 'Recipient name',
  `recipient_type` enum('member', 'junior_member', 'guardian') NOT NULL COMMENT 'Type of recipient',
  `recipient_id` int(11) DEFAULT NULL COMMENT 'ID of member/junior_member',
  `email_queue_id` bigint(20) DEFAULT NULL COMMENT 'Reference to email_queue entry',
  `status` enum('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  `error_message` text COMMENT 'Error message if send failed',
  `sent_at` timestamp NULL COMMENT 'When email was sent',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `newsletter_id` (`newsletter_id`),
  KEY `email_queue_id` (`email_queue_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`email_queue_id`) REFERENCES `email_queue`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add newsletter permissions
INSERT IGNORE INTO `permissions` (`module`, `action`, `description`) VALUES
('newsletters', 'view', 'Visualizzare le newsletter'),
('newsletters', 'create', 'Creare nuove newsletter'),
('newsletters', 'edit', 'Modificare newsletter bozza'),
('newsletters', 'delete', 'Eliminare newsletter bozza'),
('newsletters', 'send', 'Inviare newsletter');
