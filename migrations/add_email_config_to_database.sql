-- Migration: Add email configuration to database
-- This migration adds email configuration settings to the config table
-- so they can be managed from the settings page instead of editing config.php

-- Insert email configuration with default values if not exists
INSERT IGNORE INTO `config` (`config_key`, `config_value`) VALUES
('email_from_address', 'noreply@example.com'),
('email_from_name', 'EasyVol'),
('email_reply_to', ''),
('email_return_path', ''),
('email_charset', 'UTF-8'),
('email_encoding', '8bit'),
('email_sendmail_params', ''),
('email_additional_headers', '');
