<?php
/**
 * Cron Job: Email Queue Processor
 * 
 * Processa la coda delle email da inviare
 * Eseguire ogni 5 minuti: * /5 * * * * php /path/to/easyvol/cron/email_queue.php
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Utils\EmailSender;

try {
    // Initialize app without authentication
    $app = new App(false);
    $db = $app->getDatabase();
    $config = $app->getConfig();
    
    // Check if email is enabled
    if (!($config['email']['enabled'] ?? false)) {
        echo "Email sending is disabled in configuration\n";
        exit(0);
    }
    
    $emailSender = new EmailSender($config, $db);
    
    // Process up to 50 emails
    $sent = $emailSender->processQueue(50);
    
    echo "Processed $sent emails\n";
    
    // Log activity
    $sql = "INSERT INTO activity_logs (user_id, module, action, details, created_at) 
            VALUES (NULL, 'cron', 'email_queue', ?, NOW())";
    $db->execute($sql, ["Processed $sent emails"]);
    
} catch (\Exception $e) {
    error_log("Email queue cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
