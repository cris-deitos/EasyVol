-- Migration: Add health_surveillance_expiry notification type for Telegram
-- Date: 2026-01-10
-- Description: Add telegram notification configuration for health surveillance expiry events

-- Insert health_surveillance_expiry action type into telegram_notification_config
INSERT INTO `telegram_notification_config` (`action_type`, `is_enabled`, `message_template`) 
VALUES ('health_surveillance_expiry', 1, NULL)
ON DUPLICATE KEY UPDATE `action_type` = `action_type`;
