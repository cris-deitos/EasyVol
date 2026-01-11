#!/usr/bin/env php
<?php
/**
 * Health Surveillance Expiry Alerts Cron Job
 * 
 * Sends reminder notifications for upcoming health surveillance visit expirations
 * 
 * Schedule: Daily at 08:00
 * Crontab: 0 8 * * * php /path/to/easyvol/cron/health_surveillance_alerts.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\EmailSender;

try {
    // Initialize
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    $emailSender = new EmailSender($config, $db);
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting health surveillance expiry alerts job...\n";
    // Get adult members with expiring health surveillance visits (next 30 days)
    $sql = "SELECT mhs.*, m.first_name, m.last_name, m.registration_number,
                   mc.value as email, mct.value as telegram_id
            FROM member_health_surveillance mhs
            JOIN members m ON mhs.member_id = m.id
            LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'email'
            LEFT JOIN member_contacts mct ON m.id = mct.member_id AND mct.contact_type = 'telegram_id'
            WHERE mhs.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND m.member_status = 'attivo'
            ORDER BY mhs.expiry_date ASC";
    
    $expiringMemberSurveillance = $db->fetchAll($sql);
    
    echo "Found " . count($expiringMemberSurveillance) . " expiring adult member health surveillance visits\n";
    
    // Get junior members with expiring health surveillance visits (next 30 days)
    $sql = "SELECT jmhs.*, jm.first_name, jm.last_name, jm.registration_number,
                   jmc.value as email, jmct.value as telegram_id,
                   jmg.email as guardian_email
            FROM junior_member_health_surveillance jmhs
            JOIN junior_members jm ON jmhs.junior_member_id = jm.id
            LEFT JOIN junior_member_contacts jmc ON jm.id = jmc.junior_member_id AND jmc.contact_type = 'email'
            LEFT JOIN junior_member_contacts jmct ON jm.id = jmct.junior_member_id AND jmct.contact_type = 'telegram_id'
            LEFT JOIN junior_member_guardians jmg ON jm.id = jmg.junior_member_id AND jmg.guardian_type = 'padre'
            WHERE jmhs.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND jm.member_status = 'attivo'
            ORDER BY jmhs.expiry_date ASC";
    
    $expiringJuniorSurveillance = $db->fetchAll($sql);
    
    echo "Found " . count($expiringJuniorSurveillance) . " expiring junior member health surveillance visits\n";
    
    // Send email notifications for adult members
    if (!empty($expiringMemberSurveillance)) {
        $groupedBySurveillance = [];
        foreach ($expiringMemberSurveillance as $surveillance) {
            $memberId = $surveillance['member_id'];
            if (!isset($groupedBySurveillance[$memberId])) {
                $groupedBySurveillance[$memberId] = [
                    'member' => [
                        'first_name' => $surveillance['first_name'],
                        'last_name' => $surveillance['last_name'],
                        'registration_number' => $surveillance['registration_number'],
                        'email' => $surveillance['email'],
                        'telegram_id' => $surveillance['telegram_id']
                    ],
                    'visits' => []
                ];
            }
            $groupedBySurveillance[$memberId]['visits'][] = $surveillance;
        }
        
        foreach ($groupedBySurveillance as $memberId => $data) {
            // Email notification
            if (!empty($data['member']['email'])) {
                $subject = 'Promemoria Scadenza Sorveglianza Sanitaria';
                $body = '<p>Gentile ' . htmlspecialchars($data['member']['first_name']) . ' ' . htmlspecialchars($data['member']['last_name']) . ',</p>';
                $body .= '<p>La presente per ricordare che le seguenti visite di sorveglianza sanitaria stanno per scadere:</p><ul>';
                
                foreach ($data['visits'] as $visit) {
                    $body .= '<li>Visita del ' . date('d/m/Y', strtotime($visit['visit_date']));
                    $body .= ' - Esito: ' . htmlspecialchars($visit['result']);
                    $body .= ' - <b>Scadenza: ' . date('d/m/Y', strtotime($visit['expiry_date'])) . '</b></li>';
                }
                
                $body .= '</ul><p>Si prega di programmare una nuova visita medica prima della data di scadenza.</p>';
                $body .= '<p>Cordiali saluti,<br>L\'Amministrazione</p>';
                
                $emailSender->queue($data['member']['email'], $subject, $body);
            }
            
            // Telegram notification
            if (!empty($data['member']['telegram_id'])) {
                try {
                    require_once __DIR__ . '/../src/Services/TelegramService.php';
                    $telegramService = new \EasyVol\Services\TelegramService($db, $config);
                    
                    if ($telegramService->isEnabled()) {
                        $message = "⚕️ <b>Promemoria Scadenza Sorveglianza Sanitaria</b>\n\n";
                        $message .= "Gentile " . htmlspecialchars($data['member']['first_name']) . " " . htmlspecialchars($data['member']['last_name']) . ",\n\n";
                        $message .= "Le seguenti <b>visite di sorveglianza sanitaria</b> stanno per scadere:\n\n";
                        
                        foreach ($data['visits'] as $visit) {
                            $message .= "📅 <b>Visita del " . date('d/m/Y', strtotime($visit['visit_date'])) . "</b>\n";
                            $message .= "   ✅ Esito: " . htmlspecialchars($visit['result']) . "\n";
                            $message .= "   ⏰ Scadenza: " . date('d/m/Y', strtotime($visit['expiry_date'])) . "\n\n";
                        }
                        
                        $message .= "Si prega di programmare una nuova visita medica prima della data di scadenza.";
                        
                        $telegramService->sendMessage($data['member']['telegram_id'], $message);
                    }
                } catch (\Exception $e) {
                    error_log("Telegram notification error for health surveillance expiry: " . $e->getMessage());
                }
            }
        }
    }
    
    // Send email notifications for junior members
    if (!empty($expiringJuniorSurveillance)) {
        $groupedJuniorBySurveillance = [];
        foreach ($expiringJuniorSurveillance as $surveillance) {
            $memberId = $surveillance['junior_member_id'];
            if (!isset($groupedJuniorBySurveillance[$memberId])) {
                $groupedJuniorBySurveillance[$memberId] = [
                    'member' => [
                        'first_name' => $surveillance['first_name'],
                        'last_name' => $surveillance['last_name'],
                        'registration_number' => $surveillance['registration_number'],
                        'email' => $surveillance['email'],
                        'guardian_email' => $surveillance['guardian_email'],
                        'telegram_id' => $surveillance['telegram_id']
                    ],
                    'visits' => []
                ];
            }
            $groupedJuniorBySurveillance[$memberId]['visits'][] = $surveillance;
        }
        
        foreach ($groupedJuniorBySurveillance as $memberId => $data) {
            // Use guardian email if available, otherwise member email
            $emailAddress = !empty($data['member']['guardian_email']) ? $data['member']['guardian_email'] : $data['member']['email'];
            
            // Email notification
            if (!empty($emailAddress)) {
                $subject = 'Promemoria Scadenza Sorveglianza Sanitaria - Socio Minorenne';
                $body = '<p>Gentile Genitore/Tutore,</p>';
                $body .= '<p>La presente per ricordare che le seguenti visite di sorveglianza sanitaria del socio minorenne ';
                $body .= '<b>' . htmlspecialchars($data['member']['first_name']) . ' ' . htmlspecialchars($data['member']['last_name']) . '</b> ';
                $body .= 'stanno per scadere:</p><ul>';
                
                foreach ($data['visits'] as $visit) {
                    $body .= '<li>Visita del ' . date('d/m/Y', strtotime($visit['visit_date']));
                    $body .= ' - Esito: ' . htmlspecialchars($visit['result']);
                    $body .= ' - <b>Scadenza: ' . date('d/m/Y', strtotime($visit['expiry_date'])) . '</b></li>';
                }
                
                $body .= '</ul><p>Si prega di programmare una nuova visita medica prima della data di scadenza.</p>';
                $body .= '<p>Cordiali saluti,<br>L\'Amministrazione</p>';
                
                $emailSender->queue($emailAddress, $subject, $body);
            }
            
            // Telegram notification
            if (!empty($data['member']['telegram_id'])) {
                try {
                    require_once __DIR__ . '/../src/Services/TelegramService.php';
                    $telegramService = new \EasyVol\Services\TelegramService($db, $config);
                    
                    if ($telegramService->isEnabled()) {
                        $message = "⚕️ <b>Promemoria Scadenza Sorveglianza Sanitaria</b>\n";
                        $message .= "<i>(Socio Minorenne)</i>\n\n";
                        $message .= "Gentile Genitore/Tutore,\n\n";
                        $message .= "Le seguenti visite di sorveglianza sanitaria di <b>" . htmlspecialchars($data['member']['first_name']) . " " . htmlspecialchars($data['member']['last_name']) . "</b> stanno per scadere:\n\n";
                        
                        foreach ($data['visits'] as $visit) {
                            $message .= "📅 <b>Visita del " . date('d/m/Y', strtotime($visit['visit_date'])) . "</b>\n";
                            $message .= "   ✅ Esito: " . htmlspecialchars($visit['result']) . "\n";
                            $message .= "   ⏰ Scadenza: " . date('d/m/Y', strtotime($visit['expiry_date'])) . "\n\n";
                        }
                        
                        $message .= "Si prega di programmare una nuova visita medica prima della data di scadenza.";
                        
                        $telegramService->sendMessage($data['member']['telegram_id'], $message);
                    }
                } catch (\Exception $e) {
                    error_log("Telegram notification error for junior member health surveillance expiry: " . $e->getMessage());
                }
            }
        }
    }
    
    // Send summary notification to configured recipients
    $totalExpiring = count($expiringMemberSurveillance) + count($expiringJuniorSurveillance);
    
    if ($totalExpiring > 0) {
        try {
            require_once __DIR__ . '/../src/Services/TelegramService.php';
            $telegramService = new \EasyVol\Services\TelegramService($db, $config);
            
            if ($telegramService->isEnabled()) {
                $message = "⚕️ <b>Riepilogo Scadenze Sorveglianza Sanitaria</b>\n\n";
                $message .= "Ci sono <b>" . $totalExpiring . " visite mediche</b> in scadenza nei prossimi 30 giorni:\n\n";
                $message .= "• <b>" . count($expiringMemberSurveillance) . "</b> soci maggiorenni\n";
                $message .= "• <b>" . count($expiringJuniorSurveillance) . "</b> soci minorenni\n\n";
                $message .= "Controlla il sistema per i dettagli.";
                
                $telegramService->sendNotification('health_surveillance_expiry', $message);
            }
        } catch (\Exception $e) {
            error_log("Telegram notification error: " . $e->getMessage());
        }
        
        // Also send email summary to admin
        try {
            $adminEmail = $config['admin_email'] ?? null;
            if (!empty($adminEmail)) {
                $subject = 'Riepilogo Scadenze Sorveglianza Sanitaria';
                $body = '<h2>Riepilogo Scadenze Sorveglianza Sanitaria</h2>';
                $body .= '<p>Ci sono <b>' . $totalExpiring . ' visite mediche</b> in scadenza nei prossimi 30 giorni:</p>';
                $body .= '<ul>';
                $body .= '<li><b>' . count($expiringMemberSurveillance) . '</b> soci maggiorenni</li>';
                $body .= '<li><b>' . count($expiringJuniorSurveillance) . '</b> soci minorenni</li>';
                $body .= '</ul>';
                $body .= '<p>Accedere al sistema per maggiori dettagli.</p>';
                
                $emailSender->queue($adminEmail, $subject, $body);
            }
        } catch (\Exception $e) {
            error_log("Admin email notification error: " . $e->getMessage());
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Health surveillance expiry alerts job completed successfully\n";
    echo "Total notifications sent: " . $totalExpiring . "\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("Health surveillance expiry alerts error: " . $e->getMessage());
    exit(1);
}

exit(0);
