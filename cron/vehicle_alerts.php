<?php
/**
 * Cron Job: Vehicle Alerts
 * 
 * Controlla scadenze mezzi e invia alert
 * Eseguire giornalmente: 0 8 * * * php /path/to/easyvol/cron/vehicle_alerts.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\EmailSender;

try {
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    // Get vehicles with upcoming expirations (next 30 days)
    // Note: vehicle_maintenance table doesn't have scheduled_date, only tracks past maintenance
    $sql = "SELECT v.*, 
            v.insurance_expiry as scheduled_date,
            'insurance' as alert_type
            FROM vehicles v
            WHERE v.insurance_expiry IS NOT NULL
            AND v.insurance_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND v.status != 'dismesso'
            
            UNION
            
            SELECT v.*, 
            v.inspection_expiry as scheduled_date,
            'inspection' as alert_type
            FROM vehicles v
            WHERE v.inspection_expiry IS NOT NULL
            AND v.inspection_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND v.status != 'dismesso'";
    
    $expiringItems = $db->fetchAll($sql);
    
    if (!empty($expiringItems)) {
        $emailSender = new EmailSender($config, $db);
        
        // Group by alert type
        $grouped = [];
        foreach ($expiringItems as $item) {
            $grouped[$item['alert_type']][] = $item;
        }
        
        // Prepare email body
        $body = '<h2>Alert Scadenze Mezzi</h2>';
        $body .= '<p>Le seguenti scadenze sono imminenti nei prossimi 30 giorni:</p>';
        
        foreach ($grouped as $type => $items) {
            $typeLabels = [
                'insurance' => 'Assicurazioni',
                'inspection' => 'Revisioni'
            ];
            
            $body .= '<h3>' . ($typeLabels[$type] ?? ucfirst($type)) . '</h3>';
            $body .= '<ul>';
            
            foreach ($items as $item) {
                $body .= '<li>';
                $body .= '<strong>' . htmlspecialchars($item['name']) . '</strong> ';
                $body .= '(' . htmlspecialchars($item['license_plate']) . ') - ';
                $body .= 'Scadenza: ' . date('d/m/Y', strtotime($item['scheduled_date']));
                $body .= '</li>';
            }
            
            $body .= '</ul>';
        }
        
        // Send to association email
        if (!empty($config['association']['email'])) {
            $subject = 'Alert Scadenze Mezzi - ' . count($expiringItems) . ' scadenze imminenti';
            $emailSender->queue($config['association']['email'], $subject, $body, [], 2);
        }
        
        echo "Sent alerts for " . count($expiringItems) . " expiring items\n";
        
        // Send Telegram notifications
        echo "Sending Telegram notifications...\n";
        try {
            require_once __DIR__ . '/../src/Services/TelegramService.php';
            $telegramService = new \EasyVol\Services\TelegramService($db, $config);
            
            if ($telegramService->isEnabled()) {
                $message = "🚗 <b>Alert Scadenze Mezzi</b>\n\n";
                $message .= "Le seguenti <b>" . count($expiringItems) . " scadenze</b> sono imminenti nei prossimi 30 giorni:\n\n";
                
                foreach ($grouped as $type => $items) {
                    $typeLabels = [
                        'insurance' => '🛡️ Assicurazioni',
                        'inspection' => '🔍 Revisioni'
                    ];
                    
                    $message .= "<b>" . ($typeLabels[$type] ?? ucfirst($type)) . "</b>\n";
                    
                    foreach ($items as $item) {
                        $message .= "• " . htmlspecialchars($item['name']) . " (" . htmlspecialchars($item['license_plate']) . ")\n";
                        $message .= "   📅 Scadenza: " . date('d/m/Y', strtotime($item['scheduled_date'])) . "\n";
                    }
                    
                    $message .= "\n";
                }
                
                $message .= "Controlla il sistema per maggiori dettagli.";
                
                $results = $telegramService->sendNotification('vehicle_expiry', $message);
                $sentCount = count(array_filter($results, fn($r) => $r['success']));
                echo "Sent $sentCount Telegram notifications\n";
            } else {
                echo "Telegram notifications disabled\n";
            }
        } catch (\Exception $e) {
            echo "Telegram notification error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No expiring items found\n";
    }
    
    // Log activity
    $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
            VALUES (NULL, 'cron', 'vehicle_alerts', ?, NOW())";
    $db->execute($sql, ["Checked vehicle expirations, found " . count($expiringItems)]);
    
} catch (\Exception $e) {
    error_log("Vehicle alerts cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
