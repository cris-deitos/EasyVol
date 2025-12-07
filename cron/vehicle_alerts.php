<?php
/**
 * Cron Job: Vehicle Alerts
 * 
 * Controlla scadenze mezzi e invia alert
 * Eseguire giornalmente: 0 8 * * * php /path/to/easyvol/cron/vehicle_alerts.php
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Utils\EmailSender;

try {
    $app = new App(false);
    $db = $app->getDatabase();
    $config = $app->getConfig();
    
    // Get vehicles with upcoming expirations (next 30 days)
    $sql = "SELECT v.*, 
            vm.maintenance_type, vm.scheduled_date, vm.status,
            'maintenance' as alert_type
            FROM vehicles v
            JOIN vehicle_maintenance vm ON v.id = vm.vehicle_id
            WHERE vm.status = 'scheduled'
            AND vm.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            
            UNION
            
            SELECT v.*, 
            NULL as maintenance_type, v.insurance_expiry as scheduled_date, 'active' as status,
            'insurance' as alert_type
            FROM vehicles v
            WHERE v.insurance_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            
            UNION
            
            SELECT v.*, 
            NULL as maintenance_type, v.inspection_expiry as scheduled_date, 'active' as status,
            'inspection' as alert_type
            FROM vehicles v
            WHERE v.inspection_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    
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
                'maintenance' => 'Manutenzioni',
                'insurance' => 'Assicurazioni',
                'inspection' => 'Revisioni'
            ];
            
            $body .= '<h3>' . ($typeLabels[$type] ?? ucfirst($type)) . '</h3>';
            $body .= '<ul>';
            
            foreach ($items as $item) {
                $body .= '<li>';
                $body .= '<strong>' . htmlspecialchars($item['vehicle_name']) . '</strong> ';
                $body .= '(' . htmlspecialchars($item['plate_number']) . ') - ';
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
