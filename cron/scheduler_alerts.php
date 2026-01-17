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
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\SchedulerController;
use EasyVol\Utils\EmailSender;

/**
 * Build HTML email for reminder notifications
 * 
 * @param string $userName Name of the recipient
 * @param array $items Array of scheduler items
 * @return string HTML email content
 */
function buildReminderEmailHtml($userName, $items) {
    $itemsHtml = '';
    
    foreach ($items as $item) {
        $priorityColors = [
            'bassa' => '#28a745',
            'media' => '#ffc107', 
            'alta' => '#fd7e14',
            'urgente' => '#dc3545'
        ];
        $priorityColor = $priorityColors[$item['priority']] ?? '#6c757d';
        
        $statusLabels = [
            'in_attesa' => 'In Attesa',
            'in_corso' => 'In Corso',
            'completato' => 'Completato',
            'scaduto' => 'Scaduto'
        ];
        $statusLabel = $statusLabels[$item['status']] ?? ucfirst($item['status']);
        
        $statusColors = [
            'in_attesa' => '#6c757d',
            'in_corso' => '#0d6efd',
            'completato' => '#28a745',
            'scaduto' => '#dc3545'
        ];
        $statusColor = $statusColors[$item['status']] ?? '#6c757d';
        
        $itemsHtml .= '<div style="background-color: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid ' . $priorityColor . ';">';
        $itemsHtml .= '<h3 style="margin-top: 0; color: #333;">' . htmlspecialchars($item['title']) . '</h3>';
        
        $itemsHtml .= '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
        $itemsHtml .= '<tr><td style="padding: 5px 0; color: #666;"><strong>📅 Data Scadenza:</strong></td><td style="padding: 5px 0; color: #333;"><strong>' . date('d/m/Y', strtotime($item['due_date'])) . '</strong></td></tr>';
        
        if (!empty($item['category'])) {
            $itemsHtml .= '<tr><td style="padding: 5px 0; color: #666;"><strong>📂 Categoria:</strong></td><td style="padding: 5px 0; color: #333;">' . htmlspecialchars($item['category']) . '</td></tr>';
        }
        
        $itemsHtml .= '<tr><td style="padding: 5px 0; color: #666;"><strong>⚡ Priorità:</strong></td><td style="padding: 5px 0;"><span style="background-color: ' . $priorityColor . '; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">' . ucfirst($item['priority']) . '</span></td></tr>';
        
        $itemsHtml .= '<tr><td style="padding: 5px 0; color: #666;"><strong>📊 Stato:</strong></td><td style="padding: 5px 0;"><span style="background-color: ' . $statusColor . '; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">' . $statusLabel . '</span></td></tr>';
        
        if (!empty($item['assigned_name'])) {
            $itemsHtml .= '<tr><td style="padding: 5px 0; color: #666;"><strong>👤 Assegnato a:</strong></td><td style="padding: 5px 0; color: #333;">' . htmlspecialchars($item['assigned_name']) . '</td></tr>';
        }
        
        if (!empty($item['description'])) {
            $itemsHtml .= '<tr><td colspan="2" style="padding: 10px 0; color: #666;"><strong>📝 Descrizione:</strong><br><span style="color: #333;">' . nl2br(htmlspecialchars($item['description'])) . '</span></td></tr>';
        }
        
        $itemsHtml .= '</table>';
        $itemsHtml .= '</div>';
    }
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promemoria Scadenze</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <div style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px;">⏰ Promemoria Scadenze</h1>
            <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">Sistema di Gestione Scadenzario</p>
        </div>
        
        <!-- Content -->
        <div style="padding: 30px;">
            <p style="font-size: 16px; margin-bottom: 20px;">Gentile <strong>{$userName}</strong>,</p>
            <p style="font-size: 16px; margin-bottom: 30px;">Ti ricordiamo le seguenti <strong>scadenze in arrivo</strong>:</p>
            
            {$itemsHtml}
            
            <div style="background-color: #e7f3ff; border-left: 4px solid #0d6efd; padding: 15px; margin-top: 20px; border-radius: 5px;">
                <p style="margin: 0; color: #004085;"><strong>💡 Azione richiesta:</strong> Accedi al sistema per gestire le tue scadenze e aggiornare lo stato degli elementi.</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;">
            <p style="margin: 0; font-size: 12px; color: #6c757d;">Questo è un messaggio automatico, si prega di non rispondere.</p>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #6c757d;">Sistema EasyVol - Gestione Associazione</p>
        </div>
    </div>
</body>
</html>
HTML;
    
    return $html;
}

try {
    // Initialize
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    $controller = new SchedulerController($db, $config);
    $emailSender = new EmailSender($config, $db);
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting scheduler alerts job...\n";
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
            
            // Build HTML email body
            $body = $this->buildReminderEmailHtml($data['user']['name'], $data['items']);
            
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
                    
                    $body = buildReminderEmailHtml($recipient['name'], [$item]);
                    
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
                            $statusIcons = ['in_attesa' => '⏸️', 'in_corso' => '▶️', 'completato' => '✅', 'scaduto' => '❌'];
                            $icon = $priorityIcons[$item['priority']] ?? '📌';
                            $statusIcon = $statusIcons[$item['status']] ?? '📋';
                            
                            $message .= "{$icon} <b>" . htmlspecialchars($item['title']) . "</b>\n";
                            $message .= "   📅 <b>Scadenza:</b> " . date('d/m/Y', strtotime($item['due_date'])) . "\n";
                            if (!empty($item['category'])) {
                                $message .= "   📂 <b>Categoria:</b> " . htmlspecialchars($item['category']) . "\n";
                            }
                            $message .= "   " . $icon . " <b>Priorità:</b> " . ucfirst($item['priority']) . "\n";
                            $message .= "   " . $statusIcon . " <b>Stato:</b> " . ucfirst(str_replace('_', ' ', $item['status'])) . "\n";
                            if (!empty($item['assigned_name'])) {
                                $message .= "   👤 <b>Assegnato a:</b> " . htmlspecialchars($item['assigned_name']) . "\n";
                            }
                            if ($item['description']) {
                                $message .= "   📝 " . htmlspecialchars(substr($item['description'], 0, 80)) . (strlen($item['description']) > 80 ? '...' : '') . "\n";
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
