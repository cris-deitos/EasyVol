-- Migration: Add Telegram Bot Support
-- Date: 2025-12-28
-- Description: Adds Telegram integration support including Telegram ID in contacts and notification configuration

-- 1. Update member_contacts to support telegram_id
ALTER TABLE `member_contacts` 
MODIFY COLUMN `contact_type` enum('telefono_fisso', 'cellulare', 'email', 'pec', 'telegram_id') NOT NULL;

-- 2. Update junior_member_contacts to support telegram_id
ALTER TABLE `junior_member_contacts` 
MODIFY COLUMN `contact_type` enum('telefono_fisso', 'cellulare', 'email', 'telegram_id') NOT NULL;

-- 3. Create telegram_notification_config table
CREATE TABLE IF NOT EXISTS `telegram_notification_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(100) NOT NULL COMMENT 'Type of action: member_application, junior_application, fee_payment, vehicle_departure, vehicle_return, event_created, scheduler_expiry, vehicle_expiry, license_expiry, qualification_expiry, course_expiry',
  `is_enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether notifications are enabled for this action',
  `message_template` text COMMENT 'Custom message template with placeholders',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create telegram_notification_recipients table
CREATE TABLE IF NOT EXISTS `telegram_notification_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL COMMENT 'Reference to telegram_notification_config',
  `recipient_type` enum('member', 'group') NOT NULL COMMENT 'Whether recipient is a member or a Telegram group',
  `member_id` int(11) DEFAULT NULL COMMENT 'Member ID if recipient_type is member',
  `telegram_group_id` varchar(255) DEFAULT NULL COMMENT 'Telegram group ID if recipient_type is group',
  `telegram_group_name` varchar(255) DEFAULT NULL COMMENT 'Friendly name for the group',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `config_id` (`config_id`),
  KEY `member_id` (`member_id`),
  FOREIGN KEY (`config_id`) REFERENCES `telegram_notification_config`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Insert default configuration values
INSERT INTO `config` (`config_key`, `config_value`) VALUES
('telegram_bot_token', ''),
('telegram_bot_enabled', '0')
ON DUPLICATE KEY UPDATE config_key=config_key;

-- 6. Insert default action configurations
INSERT INTO `telegram_notification_config` (`action_type`, `is_enabled`, `message_template`) VALUES
('member_application', 1, NULL),
('junior_application', 1, NULL),
('fee_payment', 1, NULL),
('vehicle_departure', 1, NULL),
('vehicle_return', 1, NULL),
('event_created', 1, NULL),
('scheduler_expiry', 1, NULL),
('vehicle_expiry', 1, NULL),
('license_expiry', 1, NULL),
('qualification_expiry', 1, NULL),
('course_expiry', 1, NULL)
ON DUPLICATE KEY UPDATE action_type=action_type;
