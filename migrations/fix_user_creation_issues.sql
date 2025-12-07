-- Migration: Fix User Creation Issues
-- Date: 2025-12-07
-- Description: Fixes issues related to user creation, email sending, and activity logging

-- Step 1: Add must_change_password field to users table if not exists
-- This allows tracking when users need to change their password
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) DEFAULT 0 COMMENT 'Flag to force password change on next login' AFTER `is_active`;

-- Step 2: Create email_logs table if not exists
-- This table stores a log of all emails sent by the system
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext,
  `status` enum('sent', 'failed') NOT NULL,
  `error_message` text,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create password_reset_tokens table if not exists
-- This table stores password reset tokens for secure password recovery
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Insert email templates for user management
-- These templates are used for sending welcome and password reset emails
INSERT INTO `email_templates` (`template_name`, `subject`, `body_html`, `placeholders`) VALUES
('user_welcome', 'Benvenuto su {{app_name}}', 
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #667eea;">Benvenuto su {{app_name}}!</h2>
        <p>Gentile <strong>{{full_name}}</strong>,</p>
        <p>Il tuo account è stato creato con successo.</p>
        <div style="background-color: #f4f4f4; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Username:</strong> {{username}}</p>
            <p style="margin: 5px 0;"><strong>Password temporanea:</strong> {{password}}</p>
        </div>
        <p><strong>Per motivi di sicurezza, ti verrà richiesto di cambiare la password al primo accesso.</strong></p>
        <p>Puoi accedere al sistema utilizzando il seguente link:</p>
        <p><a href="{{login_url}}" style="display: inline-block; padding: 10px 20px; background-color: #667eea; color: white; text-decoration: none; border-radius: 5px;">Accedi al Sistema</a></p>
        <p>Cordiali saluti,<br>Il Team di {{app_name}}</p>
    </div>
</body>
</html>',
'["app_name", "full_name", "username", "password", "login_url"]')
ON DUPLICATE KEY UPDATE 
    `subject` = VALUES(`subject`),
    `body_html` = VALUES(`body_html`),
    `placeholders` = VALUES(`placeholders`);

INSERT INTO `email_templates` (`template_name`, `subject`, `body_html`, `placeholders`) VALUES
('password_reset', 'Reset Password - {{app_name}}',
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #667eea;">Reset Password</h2>
        <p>Gentile <strong>{{username}}</strong>,</p>
        <p>La tua password è stata resettata come richiesto.</p>
        <div style="background-color: #f4f4f4; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Nuova password temporanea:</strong> {{password}}</p>
        </div>
        <p><strong>Per motivi di sicurezza, ti verrà richiesto di cambiare la password al primo accesso.</strong></p>
        <p>Puoi accedere al sistema utilizzando il seguente link:</p>
        <p><a href="{{login_url}}" style="display: inline-block; padding: 10px 20px; background-color: #667eea; color: white; text-decoration: none; border-radius: 5px;">Accedi al Sistema</a></p>
        <p>Se non hai richiesto questo reset, contatta immediatamente l\'amministratore del sistema.</p>
        <p>Cordiali saluti,<br>Il Team di {{app_name}}</p>
    </div>
</body>
</html>',
'["app_name", "username", "password", "login_url"]')
ON DUPLICATE KEY UPDATE 
    `subject` = VALUES(`subject`),
    `body_html` = VALUES(`body_html`),
    `placeholders` = VALUES(`placeholders`);
