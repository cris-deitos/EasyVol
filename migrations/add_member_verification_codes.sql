-- Migration: Add member verification codes for public portal
-- Date: 2025-12-13
-- Description: Adds table to store temporary verification codes for member self-service portal

-- Create member_verification_codes table for member portal access
CREATE TABLE IF NOT EXISTS `member_verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL COMMENT 'Verification code (6-10 characters)',
  `email` varchar(255) NOT NULL COMMENT 'Email where code was sent',
  `expires_at` timestamp NOT NULL COMMENT 'Expiration time for the code',
  `used` tinyint(1) DEFAULT 0 COMMENT 'Whether the code has been used',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `code` (`code`),
  KEY `expires_at` (`expires_at`),
  KEY `used` (`used`),
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add email templates for member portal
INSERT INTO `email_templates` (`template_name`, `subject`, `body_html`, `placeholders`) VALUES
('member_verification_code', 'Codice di Verifica - {{association_name}}',
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #667eea;">Codice di Verifica</h2>
        <p>Gentile <strong>{{member_name}}</strong>,</p>
        <p>Hai richiesto l''accesso al portale di aggiornamento dati soci.</p>
        <div style="background-color: #f4f4f4; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
            <p style="margin: 5px 0; font-size: 14px;">Il tuo codice di verifica è:</p>
            <p style="margin: 10px 0; font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px;">{{verification_code}}</p>
            <p style="margin: 5px 0; font-size: 12px; color: #666;">Il codice scadrà tra 15 minuti</p>
        </div>
        <p>Inserisci questo codice nella pagina di verifica per procedere.</p>
        <p><strong>Se non hai richiesto questo codice, ignora questa email.</strong></p>
        <p>Cordiali saluti,<br>{{association_name}}</p>
    </div>
</body>
</html>',
'["association_name", "member_name", "verification_code"]')
ON DUPLICATE KEY UPDATE 
    `subject` = VALUES(`subject`),
    `body_html` = VALUES(`body_html`),
    `placeholders` = VALUES(`placeholders`);

INSERT INTO `email_templates` (`template_name`, `subject`, `body_html`, `placeholders`) VALUES
('member_data_updated', 'Conferma Aggiornamento Dati - {{association_name}}',
'<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #667eea;">Conferma Aggiornamento Dati</h2>
        <p>Gentile <strong>{{member_name}}</strong>,</p>
        <p>I tuoi dati sono stati aggiornati con successo nel nostro sistema.</p>
        <div style="background-color: #f4f4f4; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Riepilogo delle modifiche:</h3>
            {{changes_summary}}
        </div>
        <p>Se hai riscontrato errori o necessiti di ulteriori modifiche, contatta la Segreteria dell''Associazione.</p>
        <p>Cordiali saluti,<br>{{association_name}}</p>
    </div>
</body>
</html>',
'["association_name", "member_name", "changes_summary"]')
ON DUPLICATE KEY UPDATE 
    `subject` = VALUES(`subject`),
    `body_html` = VALUES(`body_html`),
    `placeholders` = VALUES(`placeholders`);
