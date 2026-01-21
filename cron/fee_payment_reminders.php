<?php
/**
 * Cron Job: Fee Payment Reminders Processor
 * 
 * Processa la coda dei promemoria di pagamento quote e invia le email
 * Eseguire ogni 5 minuti con crontab -e:
 * (asterisk)/5 (asterisk) (asterisk) (asterisk) (asterisk) php /path/to/easyvol/cron/fee_payment_reminders.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\FeePaymentController;

try {
    // Initialize app
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    // Check if email is enabled
    if (!($config['email']['enabled'] ?? false)) {
        echo "Email sending is disabled in configuration\n";
        exit(0);
    }
    
    $controller = new FeePaymentController($db, $config);
    
    // Process up to 50 reminder emails per run
    $sent = $controller->processReminderQueue(50);
    
    echo "Processed $sent fee payment reminder emails\n";
    
    // Log activity
    $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
            VALUES (NULL, 'cron', 'fee_payment_reminders', ?, NOW())";
    $db->execute($sql, ["Processed $sent fee payment reminder emails"]);
    
} catch (\Exception $e) {
    error_log("Fee payment reminders cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
