-- Migration: Add user_login notification type for Telegram
-- Date: 2026-01-10
-- Description: Add telegram notification configuration for user login events

-- Insert user_login action type into telegram_notification_config
INSERT INTO `telegram_notification_config` (`action_type`, `is_enabled`, `message_template`) 
VALUES ('user_login', 1, NULL)
ON DUPLICATE KEY UPDATE `action_type` = `action_type`;
