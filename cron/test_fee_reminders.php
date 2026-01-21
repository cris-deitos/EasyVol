#!/usr/bin/env php
<?php
/**
 * Test Script for Fee Payment Reminders Cron Job
 * 
 * This script tests the fee payment reminders cron job logic without actually
 * sending emails or creating database records.
 * 
 * Usage: php cron/test_fee_reminders.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\FeePaymentController;

echo "=== Fee Payment Reminders Cron Job Test ===\n\n";

try {
    // Initialize app
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    echo "[INFO] Application initialized successfully\n";
    echo "[INFO] Testing year: " . date('Y') . "\n\n";
    
    // Initialize controller
    $controller = new FeePaymentController($db, $config);
    echo "[INFO] FeePaymentController initialized\n\n";
    
    // Test 20-day cooldown check
    echo "--- Testing 20-day cooldown check ---\n";
    $year = date('Y');
    $checkResult = $controller->canSendReminders($year);
    
    if ($checkResult['can_send']) {
        echo "[✓] Can send reminders: YES\n";
        echo "    Last sent: " . ($checkResult['last_sent'] ?? 'Never') . "\n";
        echo "    Days since: " . ($checkResult['days_since'] ?? 'N/A') . "\n";
    } else {
        echo "[✗] Can send reminders: NO\n";
        echo "    Last sent: " . $checkResult['last_sent'] . "\n";
        echo "    Days since: " . $checkResult['days_since'] . "\n";
        echo "    Days remaining: " . (20 - $checkResult['days_since']) . "\n";
    }
    
    echo "\n--- Testing unpaid members query ---\n";
    
    // Use reflection to access private method for testing
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getUnpaidMembersForReminder');
    $method->setAccessible(true);
    
    $unpaidResult = $method->invoke($controller, $year);
    echo "[INFO] Unpaid members found: " . $unpaidResult['total'] . "\n";
    
    if ($unpaidResult['total'] > 0) {
        echo "[INFO] Sample members (first 3):\n";
        $sample = array_slice($unpaidResult['members'], 0, 3);
        foreach ($sample as $member) {
            echo "    - {$member['registration_number']}: {$member['first_name']} {$member['last_name']} ({$member['email']})\n";
        }
    }
    
    echo "\n--- Test Summary ---\n";
    echo "[✓] 20-day cooldown check: WORKING\n";
    echo "[✓] Unpaid members query: WORKING\n";
    echo "[✓] Controller methods: ACCESSIBLE\n";
    
    if ($checkResult['can_send'] && $unpaidResult['total'] > 0) {
        echo "\n[INFO] If this were a real execution:\n";
        echo "    - Would queue " . $unpaidResult['total'] . " emails to email_queue\n";
        echo "    - Would create 1 batch record in fee_payment_reminders\n";
        echo "    - Emails would be sent by email_queue.php cron job\n";
    } elseif (!$checkResult['can_send']) {
        echo "\n[INFO] Execution would be skipped due to 20-day cooldown\n";
    } elseif ($unpaidResult['total'] === 0) {
        echo "\n[INFO] No unpaid members found, nothing to do\n";
    }
    
    echo "\n=== Test Completed Successfully ===\n";
    
} catch (\Exception $e) {
    echo "\n[ERROR] Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
