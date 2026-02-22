-- Migration: Add province_access notification type for Telegram
-- Date: 2026-02-22
-- Description: Add telegram notification configuration for province civil protection office access events

INSERT INTO `telegram_notification_config` (`action_type`, `is_enabled`, `message_template`) 
VALUES ('province_access', 1, NULL)
ON DUPLICATE KEY UPDATE `action_type` = `action_type`;
