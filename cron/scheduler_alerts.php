#!/usr/bin/env php
<?php
/**
 * Scheduler Alerts Cron Job
 * 
 * Sends reminder emails for upcoming deadlines and updates overdue items.
 * 
 * Schedule: Daily at 08:00
 * Crontab: 0 8 * * * php /path/to/easyvol/cron/scheduler_alerts.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
require_once __DIR__ . '/../config/config.php';

use EasyVol\Database;
use EasyVol\Controllers\SchedulerController;
use EasyVol\Utils\EmailSender;

// Initialize
$db = new Database($config);
$controller = new SchedulerController($db, $config);
$emailSender = new EmailSender($config, $db);

echo "[" . date('Y-m-d H:i:s') . "] Starting scheduler alerts job...\n";

try {
    // 1. Update overdue status
    echo "Updating overdue items...\n";
    $overdueCount = $controller->updateOverdueStatus();
    echo "Updated $overdueCount items to 'scaduto' status\n";
    
    // 2. Get items that need reminders
    echo "Checking for reminder notifications...\n";
    $items = $controller->getItemsForReminder();
    
    if (empty($items)) {
        echo "No reminders to send today\n";
    } else {
        echo "Found " . count($items) . " items needing reminders\n";
        
        // Group by assigned user
        $groupedItems = [];
        foreach ($items as $item) {
            if ($item['assigned_to']) {
                if (!isset($groupedItems[$item['assigned_to']])) {
                    $groupedItems[$item['assigned_to']] = [
                        'user' => [
                            'email' => $item['assigned_email'],
                            'name' => $item['assigned_name']
                        ],
                        'items' => []
                    ];
                }
                $groupedItems[$item['assigned_to']]['items'][] = $item;
            }
        }
        
        // Send email for each user
        $sentCount = 0;
        foreach ($groupedItems as $userId => $data) {
            if (!$data['user']['email']) {
                echo "  Skipping user ID $userId - no email address\n";
                continue;
            }
            
            // Build email body
            $body = "Gentile " . $data['user']['name'] . ",\n\n";
            $body .= "Ti ricordiamo le seguenti scadenze in arrivo:\n\n";
            
            foreach ($data['items'] as $item) {
                $body .= "- " . $item['title'] . "\n";
                $body .= "  Scadenza: " . date('d/m/Y', strtotime($item['due_date'])) . "\n";
                $body .= "  Priorità: " . ucfirst($item['priority']) . "\n";
                if ($item['description']) {
                    $body .= "  Descrizione: " . $item['description'] . "\n";
                }
                $body .= "\n";
            }
            
            $body .= "Accedi al sistema per gestire le tue scadenze.\n\n";
            $body .= "Questo è un messaggio automatico, si prega di non rispondere.\n";
            
            // Send email
            $subject = "Promemoria Scadenze - " . count($data['items']) . " scadenza/e in arrivo";
            
            if ($emailSender->send($data['user']['email'], $subject, $body)) {
                $sentCount++;
                echo "  Sent reminder to {$data['user']['email']}\n";
            } else {
                echo "  Failed to send reminder to {$data['user']['email']}\n";
            }
        }
        
        // Send to custom recipients for items not assigned to users
        echo "Checking for custom recipients...\n";
        foreach ($items as $item) {
            if (!empty($item['custom_recipients'])) {
                foreach ($item['custom_recipients'] as $recipient) {
                    if (empty($recipient['email'])) {
                        continue;
                    }
                    
                    $body = "Gentile " . $recipient['name'] . ",\n\n";
                    $body .= "Ti ricordiamo la seguente scadenza in arrivo:\n\n";
                    $body .= "- " . $item['title'] . "\n";
                    $body .= "  Scadenza: " . date('d/m/Y', strtotime($item['due_date'])) . "\n";
                    $body .= "  Priorità: " . ucfirst($item['priority']) . "\n";
                    if ($item['description']) {
                        $body .= "  Descrizione: " . $item['description'] . "\n";
                    }
                    $body .= "\n";
                    $body .= "Accedi al sistema per maggiori dettagli.\n\n";
                    $body .= "Questo è un messaggio automatico, si prega di non rispondere.\n";
                    
                    $subject = "Promemoria Scadenza - " . $item['title'];
                    
                    if ($emailSender->send($recipient['email'], $subject, $body)) {
                        $sentCount++;
                        echo "  Sent reminder to {$recipient['email']}\n";
                    } else {
                        echo "  Failed to send reminder to {$recipient['email']}\n";
                    }
                }
            }
        }
        
        echo "Sent $sentCount reminder emails\n";
        
        // Send Telegram notifications
        echo "Sending Telegram notifications...\n";
        try {
            require_once __DIR__ . '/../src/Services/TelegramService.php';
            $telegramService = new \EasyVol\Services\TelegramService($db, $config);
            
            if ($telegramService->isEnabled()) {
                $telegramSentCount = 0;
                
                // Group items by assigned user for Telegram
                $groupedItemsTelegram = [];
                foreach ($items as $item) {
                    if ($item['assigned_to']) {
                        if (!isset($groupedItemsTelegram[$item['assigned_to']])) {
                            $groupedItemsTelegram[$item['assigned_to']] = [
                                'user' => [
                                    'name' => $item['assigned_name']
                                ],
                                'items' => []
                            ];
                        }
                        $groupedItemsTelegram[$item['assigned_to']]['items'][] = $item;
                    }
                }
                
                // Send Telegram message to each user with Telegram ID
                foreach ($groupedItemsTelegram as $userId => $data) {
                    // Check if user has Telegram ID
                    $telegramContact = $db->fetchOne(
                        "SELECT mc.value as telegram_id 
                         FROM member_contacts mc
                         JOIN members m ON mc.member_id = m.id
                         JOIN users u ON m.id = u.member_id
                         WHERE u.id = ? AND mc.contact_type = 'telegram_id'",
                        [$userId]
                    );
                    
                    if ($telegramContact && !empty($telegramContact['telegram_id'])) {
                        $message = "⏰ <b>Promemoria Scadenze</b>\n\n";
                        $message .= "Gentile " . htmlspecialchars($data['user']['name']) . ",\n\n";
                        $message .= "Ti ricordiamo le seguenti <b>" . count($data['items']) . " scadenze</b> in arrivo:\n\n";
                        
                        foreach ($data['items'] as $item) {
                            $priorityIcons = ['bassa' => '🟢', 'media' => '🟡', 'alta' => '🟠', 'urgente' => '🔴'];
                            $icon = $priorityIcons[$item['priority']] ?? '📌';
                            
                            $message .= "{$icon} <b>" . htmlspecialchars($item['title']) . "</b>\n";
                            $message .= "   📅 Scadenza: " . date('d/m/Y', strtotime($item['due_date'])) . "\n";
                            if ($item['description']) {
                                $message .= "   📝 " . htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : '') . "\n";
                            }
                            $message .= "\n";
                        }
                        
                        $message .= "Accedi al sistema per gestire le tue scadenze.";
                        
                        if ($telegramService->sendMessage($telegramContact['telegram_id'], $message)) {
                            $telegramSentCount++;
                            echo "  Sent Telegram notification to user ID {$userId}\n";
                        }
                    }
                }
                
                // Also send via notification recipients configuration
                $results = $telegramService->sendNotification('scheduler_expiry', 
                    "⏰ <b>Alert Scadenze Scadenzario</b>\n\n" .
                    "Ci sono <b>" . count($items) . " scadenze</b> in arrivo nei prossimi giorni.\n\n" .
                    "Controlla il sistema per i dettagli."
                );
                $telegramSentCount += count(array_filter($results, fn($r) => $r['success']));
                
                echo "Sent $telegramSentCount Telegram notifications\n";
            } else {
                echo "Telegram notifications disabled\n";
            }
        } catch (Exception $e) {
            echo "  Telegram notification error: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Check for overdue urgent items and send alert to admins
    $overdueUrgent = $controller->index([
        'status' => 'scaduto',
        'priority' => 'urgente'
    ], 1, 100);
    
    if (!empty($overdueUrgent)) {
        echo "Found " . count($overdueUrgent) . " overdue urgent items\n";
        
        // Get admin emails (you may want to configure this)
        // For now, send to configured notification email
        if (!empty($config['email']['notification_email'])) {
            $body = "Attenzione!\n\n";
            $body .= "Ci sono " . count($overdueUrgent) . " scadenze URGENTI scadute:\n\n";
            
            foreach ($overdueUrgent as $item) {
                $body .= "- " . $item['title'] . "\n";
                $body .= "  Scadenza: " . date('d/m/Y', strtotime($item['due_date'])) . "\n";
                $body .= "  Assegnato a: " . ($item['assigned_name'] ?? 'Nessuno') . "\n\n";
            }
            
            $body .= "Si prega di verificare e aggiornare lo stato di queste scadenze.\n";
            
            if ($emailSender->send(
                $config['email']['notification_email'],
                "ALERT: Scadenze Urgenti Scadute",
                $body
            )) {
                echo "Sent urgent items alert to admin\n";
            }
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Scheduler alerts job completed successfully\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
