#!/usr/bin/env php
<?php
/**
 * Fee Payment Reminders Cron Job
 * 
 * Sends automated reminders for unpaid membership fees.
 * Respects 20-day cooldown period to prevent spam.
 * 
 * Schedule: Monthly (recommended: 1st day of each month at 09:00)
 * Crontab: 0 9 1 * * php /path/to/easyvol/cron/fee_payment_reminders.php
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
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting fee payment reminders job...\n";
    
    // Get current year
    $year = date('Y');
    
    // Initialize controller
    $controller = new FeePaymentController($db, $config);
    
    // Check if can send reminders (20-day cooldown check)
    $checkResult = $controller->canSendReminders($year);
    
    if (!$checkResult['can_send']) {
        $daysSince = $checkResult['days_since'];
        $daysRemaining = 20 - $daysSince;
        echo "[" . date('Y-m-d H:i:s') . "] Skipping: Reminders already sent {$daysSince} days ago. ";
        echo "Can send again in {$daysRemaining} days (last sent: {$checkResult['last_sent']})\n";
        
        // Log activity
        $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
                VALUES (NULL, 'cron', 'fee_payment_reminders', ?, NOW())";
        $db->execute($sql, [
            "Skipped: Reminders already sent {$daysSince} days ago (last: {$checkResult['last_sent']})"
        ]);
        
        echo "[" . date('Y-m-d H:i:s') . "] Fee payment reminders job completed (skipped)\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] 20-day cooldown check passed. Proceeding with reminders for year {$year}...\n";
    
    // Create reminder batch (this will queue emails)
    // Using system user ID (null/0) since this is automated
    $reminderId = $controller->createReminderBatch($year, 0);
    
    if (!$reminderId) {
        echo "[" . date('Y-m-d H:i:s') . "] No unpaid members found or error creating batch\n";
        
        // Log activity
        $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
                VALUES (NULL, 'cron', 'fee_payment_reminders', ?, NOW())";
        $db->execute($sql, [
            "No unpaid members found for year {$year}"
        ]);
        
        echo "[" . date('Y-m-d H:i:s') . "] Fee payment reminders job completed (no members to notify)\n";
        exit(0);
    }
    
    // Get batch details
    $stmt = $db->query(
        "SELECT * FROM fee_payment_reminders WHERE id = ?",
        [$reminderId]
    );
    $batch = $stmt->fetch();
    
    echo "[" . date('Y-m-d H:i:s') . "] Reminder batch created successfully (ID: {$reminderId})\n";
    echo "[" . date('Y-m-d H:i:s') . "] Total emails queued: {$batch['total_queued']}\n";
    echo "[" . date('Y-m-d H:i:s') . "] Emails will be processed by email_queue cron job\n";
    
    // Log activity
    $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
            VALUES (NULL, 'cron', 'fee_payment_reminders', ?, NOW())";
    $db->execute($sql, [
        "Sent {$batch['total_queued']} fee payment reminders for year {$year} (batch ID: {$reminderId})"
    ]);
    
    echo "[" . date('Y-m-d H:i:s') . "] Fee payment reminders job completed successfully\n";
    
} catch (\Exception $e) {
    error_log("Fee payment reminders cron error: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
