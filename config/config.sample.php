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
        'method' => 'smtp', // smtp, sendmail, mail
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls', // tls, ssl
        'from_email' => 'noreply@example.com',
        'from_name' => 'EasyVol',
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
