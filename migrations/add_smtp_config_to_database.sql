-- Migration: Add SMTP configuration to database
-- This migration adds SMTP configuration settings to the config table
-- so emails can be sent via PHPMailer instead of native mail()

-- Insert SMTP configuration with default values if not exists
INSERT IGNORE INTO `config` (`config_key`, `config_value`) VALUES
('email_enabled', '1'),
('email_method', 'smtp'),
('email_smtp_host', ''),
('email_smtp_port', '587'),
('email_smtp_username', ''),
('email_smtp_password', ''),
('email_smtp_encryption', 'tls'),
('email_smtp_auth', '1'),
('email_smtp_debug', '0');

-- Update existing email configuration keys if they exist with empty values
-- This ensures clean migration from sendmail to SMTP configuration
