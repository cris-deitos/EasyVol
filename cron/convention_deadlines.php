#!/usr/bin/env php
<?php
/**
 * Convention Deadlines Cron Job
 * 
 * Generates scheduler items from convention annual deadlines.
 * For each active convention, creates scheduler entries for the current year
 * based on the configured day/month deadlines.
 * 
 * Schedule: Daily at 03:00
 * Crontab: 0 3 * * * php /path/to/easyvol/cron/convention_deadlines.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

try {
    $app = App::getInstance();
    $db = $app->getDb();
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting convention deadlines sync...\n";
    
    $currentYear = (int)date('Y');
    $today = date('Y-m-d');
    
    // Get all active conventions with deadlines
    $sql = "SELECT c.id, c.name, cd.* 
            FROM conventions c
            INNER JOIN convention_deadlines cd ON cd.convention_id = c.id
            WHERE (c.end_date IS NULL OR c.end_date >= ?)
            AND c.start_date <= ?";
    
    $deadlines = $db->fetchAll($sql, [$today, $today]);
    
    $created = 0;
    
    foreach ($deadlines as $dl) {
        // Build the due date for current year
        $dueDate = sprintf('%04d-%02d-%02d', $currentYear, $dl['month'], $dl['day_of_month']);
        
        // Skip dates in the past (more than advance_days ago)
        $checkDate = date('Y-m-d', strtotime($dueDate . ' -' . ($dl['advance_days'] + 30) . ' days'));
        if ($checkDate > $today) {
            continue; // Too far in the future still
        }
        if ($dueDate < date('Y-m-d', strtotime('-30 days'))) {
            continue; // Already well past
        }
        
        // Check if already created for this year
        $existingTitle = "[Conv] {$dl['name']} - {$dl['description']}";
        $existing = $db->fetchOne(
            "SELECT id FROM scheduler_items WHERE title = ? AND due_date = ?",
            [$existingTitle, $dueDate]
        );
        
        if ($existing) {
            continue;
        }
        
        // Create scheduler item
        $sql = "INSERT INTO scheduler_items (title, description, due_date, category, priority, status, reminder_days, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $db->execute($sql, [
            $existingTitle,
            "Scadenza annuale convenzione: {$dl['name']}",
            $dueDate,
            'convenzione',
            'media',
            'in_attesa',
            $dl['advance_days']
        ]);
        
        $created++;
    }
    
    echo "Created $created new scheduler items from convention deadlines\n";
    echo "[" . date('Y-m-d H:i:s') . "] Convention deadlines sync completed\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
