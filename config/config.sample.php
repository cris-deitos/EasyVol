<?php
/**
 * EasyVol Configuration Sample
 * 
 * Copy this file to config.php and update with your settings
 */

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'easyvol',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    
    'app' => [
        'name' => 'EasyVol',
        'version' => '1.0.0',
        'url' => 'http://localhost',
        'timezone' => 'Europe/Rome',
        'locale' => 'it_IT',
    ],
    
    'security' => [
        'session_lifetime' => 7200, // 2 hours in seconds
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
    ],
    
    'email' => [
        'enabled' => true,
        'method' => 'smtp', // 'smtp' or 'sendmail'
        'from_address' => 'noreply@example.com',
        'from_name' => 'EasyVol',
        'reply_to' => 'noreply@example.com',
        'return_path' => 'noreply@example.com',
        'charset' => 'UTF-8',
        // SMTP Settings (used when method = 'smtp')
        'smtp_host' => 'smtp.gmail.com', // e.g., smtp.gmail.com, smtp.office365.com
        'smtp_port' => 587, // 587 for TLS, 465 for SSL
        'smtp_username' => '',
        'smtp_password' => '', // For Gmail, use App Password
        'smtp_encryption' => 'tls', // 'tls', 'ssl', or '' for none
        'smtp_auth' => true, // Require authentication
        'smtp_debug' => false, // Enable debug logging
    ],
    
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ],
    
    'recaptcha' => [
        'enabled' => false,
        'site_key' => '',
        'secret_key' => '',
    ],
    
    'uploads' => [
        'max_file_size' => 10485760, // 10MB in bytes
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
        'path' => __DIR__ . '/../uploads',
    ],
    
    'pdf' => [
        'engine' => 'mpdf', // mpdf, tcpdf, dompdf
        'default_font' => 'dejavusans',
        'default_font_size' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_left' => 10,
        'margin_right' => 10,
    ],
];
