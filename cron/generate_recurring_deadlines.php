#!/usr/bin/env php
<?php
/**
 * Generate Recurring Deadlines Cron Job
 * 
 * Automatically generates future occurrences for all active recurring deadlines.
 * Ensures that deadlines are created in advance so reminders and notifications work properly.
 * 
 * Schedule: Daily at 02:00
 * Crontab: 0 2 * * * php /path/to/easyvol/cron/generate_recurring_deadlines.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\SchedulerController;

try {
    // Initialize
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    $controller = new SchedulerController($db, $config);
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting recurring deadlines generation job...\n";
    
    // Generate recurring instances for the next 90 days
    $daysAhead = 90;
    echo "Generating recurring deadline occurrences for the next $daysAhead days...\n";
    
    $generatedCount = $controller->generateAllRecurrences($daysAhead);
    
    if ($generatedCount > 0) {
        echo "Successfully generated $generatedCount new deadline occurrence(s)\n";
    } else {
        echo "No new deadline occurrences needed at this time\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Recurring deadlines generation job completed successfully\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
