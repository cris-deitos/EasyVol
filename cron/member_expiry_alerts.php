#!/usr/bin/env php
<?php
/**
 * Member Expiry Alerts Cron Job
 * 
 * Sends reminder notifications for upcoming member-related expirations:
 * - Driver licenses
 * - Qualifications
 * - Courses
 * 
 * Schedule: Daily at 08:00
 * Crontab: 0 8 * * * php /path/to/easyvol/cron/member_expiry_alerts.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\EmailSender;

try {
    // Initialize app
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    $emailSender = new EmailSender($config, $db);
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting member expiry alerts job...\n";
    
    // Get members with expiring licenses (next 30 days)
    $sql = "SELECT ml.*, m.first_name, m.last_name, m.registration_number,
                   mc.value as email, mct.value as telegram_id
            FROM member_licenses ml
            JOIN members m ON ml.member_id = m.id
            LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'email'
            LEFT JOIN member_contacts mct ON m.id = mct.member_id AND mct.contact_type = 'telegram_id'
            WHERE ml.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND m.member_status = 'attivo'
            ORDER BY ml.expiry_date ASC";
    
    $expiringLicenses = $db->fetchAll($sql);
    
    echo "Found " . count($expiringLicenses) . " expiring licenses\n";
    
    // Get members with expiring qualifications (next 30 days)
    $sql = "SELECT mq.*, m.first_name, m.last_name, m.registration_number,
                   mc.value as email, mct.value as telegram_id
            FROM member_qualifications mq
            JOIN members m ON mq.member_id = m.id
            LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'email'
            LEFT JOIN member_contacts mct ON m.id = mct.member_id AND mct.contact_type = 'telegram_id'
            WHERE mq.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND m.member_status = 'attivo'
            ORDER BY mq.expiry_date ASC";
    
    $expiringQualifications = $db->fetchAll($sql);
    
    echo "Found " . count($expiringQualifications) . " expiring qualifications\n";
    
    // Get members with expiring courses (next 30 days)
    $sql = "SELECT mc.*, m.first_name, m.last_name, m.registration_number,
                   mce.value as email, mct.value as telegram_id
            FROM member_courses mc
            JOIN members m ON mc.member_id = m.id
            LEFT JOIN member_contacts mce ON m.id = mce.member_id AND mce.contact_type = 'email'
            LEFT JOIN member_contacts mct ON m.id = mct.member_id AND mct.contact_type = 'telegram_id'
            WHERE mc.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND m.member_status = 'attivo'
            ORDER BY mc.expiry_date ASC";
    
    $expiringCourses = $db->fetchAll($sql);
    
    echo "Found " . count($expiringCourses) . " expiring courses\n";
    
    // Send email and Telegram notifications for licenses
    if (!empty($expiringLicenses)) {
        $groupedLicenses = [];
        foreach ($expiringLicenses as $license) {
            $memberId = $license['member_id'];
            if (!isset($groupedLicenses[$memberId])) {
                $groupedLicenses[$memberId] = [
                    'member' => [
                        'first_name' => $license['first_name'],
                        'last_name' => $license['last_name'],
                        'email' => $license['email'],
                        'telegram_id' => $license['telegram_id']
                    ],
                    'licenses' => []
                ];
            }
            $groupedLicenses[$memberId]['licenses'][] = $license;
        }
        
        foreach ($groupedLicenses as $memberId => $data) {
            // Email notification
            if (!empty($data['member']['email'])) {
                $subject = 'Alert Scadenza Patenti';
                $body = '<p>Gentile ' . htmlspecialchars($data['member']['first_name']) . ',</p>';
                $body .= '<p>Le seguenti patenti stanno per scadere:</p><ul>';
                
                foreach ($data['licenses'] as $license) {
                    $body .= '<li>' . htmlspecialchars($license['license_type']) . ' - ';
                    $body .= 'Scadenza: ' . date('d/m/Y', strtotime($license['expiry_date'])) . '</li>';
                }
                
                $body .= '</ul><p>Si prega di rinnovare le patenti in scadenza.</p>';
                
                $emailSender->queue($data['member']['email'], $subject, $body);
            }
            
            // Telegram notification
            if (!empty($data['member']['telegram_id'])) {
                try {
                    require_once __DIR__ . '/../src/Services/TelegramService.php';
                    $telegramService = new \EasyVol\Services\TelegramService($db, $config);
                    
                    if ($telegramService->isEnabled()) {
                        $message = "⚠️ <b>Alert Scadenza Patenti</b>\n\n";
                        $message .= "Gentile " . htmlspecialchars($data['member']['first_name']) . ",\n\n";
                        $message .= "Le seguenti <b>patenti</b> stanno per scadere:\n\n";
                        
                        foreach ($data['licenses'] as $license) {
                            $message .= "🚗 <b>" . htmlspecialchars($license['license_type']) . "</b>\n";
                            $message .= "   📅 Scadenza: " . date('d/m/Y', strtotime($license['expiry_date'])) . "\n\n";
                        }
                        
                        $message .= "Si prega di rinnovare le patenti in scadenza.";
                        
                        $telegramService->sendMessage($data['member']['telegram_id'], $message);
                    }
                } catch (\Exception $e) {
                    error_log("Telegram notification error for license expiry: " . $e->getMessage());
                }
            }
        }
        
        // Send summary to configured recipients
        try {
            require_once __DIR__ . '/../src/Services/TelegramService.php';
            $telegramService = new \EasyVol\Services\TelegramService($db, $config);
            
            if ($telegramService->isEnabled()) {
                $message = "🚗 <b>Riepilogo Scadenze Patenti</b>\n\n";
                $message .= "Ci sono <b>" . count($expiringLicenses) . " patenti</b> in scadenza nei prossimi 30 giorni.\n\n";
                $message .= "Controlla il sistema per i dettagli.";
                
                $telegramService->sendNotification('license_expiry', $message);
            }
        } catch (\Exception $e) {
            error_log("Telegram notification error: " . $e->getMessage());
        }
    }
    
    // Send email and Telegram notifications for qualifications
    if (!empty($expiringQualifications)) {
        $groupedQualifications = [];
        foreach ($expiringQualifications as $qualification) {
            $memberId = $qualification['member_id'];
            if (!isset($groupedQualifications[$memberId])) {
                $groupedQualifications[$memberId] = [
                    'member' => [
                        'first_name' => $qualification['first_name'],
                        'last_name' => $qualification['last_name'],
                        'email' => $qualification['email'],
                        'telegram_id' => $qualification['telegram_id']
                    ],
                    'qualifications' => []
                ];
            }
            $groupedQualifications[$memberId]['qualifications'][] = $qualification;
        }
        
        foreach ($groupedQualifications as $memberId => $data) {
            // Email notification
            if (!empty($data['member']['email'])) {
                $subject = 'Alert Scadenza Qualifiche';
                $body = '<p>Gentile ' . htmlspecialchars($data['member']['first_name']) . ',</p>';
                $body .= '<p>Le seguenti qualifiche stanno per scadere:</p><ul>';
                
                foreach ($data['qualifications'] as $qual) {
                    $body .= '<li>' . htmlspecialchars($qual['qualification_type']) . ' - ';
                    $body .= 'Scadenza: ' . date('d/m/Y', strtotime($qual['expiry_date'])) . '</li>';
                }
                
                $body .= '</ul><p>Si prega di rinnovare le qualifiche in scadenza.</p>';
                
                $emailSender->queue($data['member']['email'], $subject, $body);
            }
            
            // Telegram notification
            if (!empty($data['member']['telegram_id'])) {
                try {
                    require_once __DIR__ . '/../src/Services/TelegramService.php';
                    $telegramService = new \EasyVol\Services\TelegramService($db, $config);
                    
                    if ($telegramService->isEnabled()) {
                        $message = "⚠️ <b>Alert Scadenza Qualifiche</b>\n\n";
                        $message .= "Gentile " . htmlspecialchars($data['member']['first_name']) . ",\n\n";
                        $message .= "Le seguenti <b>qualifiche</b> stanno per scadere:\n\n";
                        
                        foreach ($data['qualifications'] as $qual) {
                            $message .= "🎓 <b>" . htmlspecialchars($qual['qualification_type']) . "</b>\n";
                            $message .= "   📅 Scadenza: " . date('d/m/Y', strtotime($qual['expiry_date'])) . "\n\n";
                        }
                        
                        $message .= "Si prega di rinnovare le qualifiche in scadenza.";
                        
                        $telegramService->sendMessage($data['member']['telegram_id'], $message);
                    }
                } catch (\Exception $e) {
                    error_log("Telegram notification error for qualification expiry: " . $e->getMessage());
                }
            }
        }
        
        // Send summary to configured recipients
        try {
            require_once __DIR__ . '/../src/Services/TelegramService.php';
            $telegramService = new \EasyVol\Services\TelegramService($db, $config);
            
            if ($telegramService->isEnabled()) {
                $message = "🎓 <b>Riepilogo Scadenze Qualifiche</b>\n\n";
                $message .= "Ci sono <b>" . count($expiringQualifications) . " qualifiche</b> in scadenza nei prossimi 30 giorni.\n\n";
                $message .= "Controlla il sistema per i dettagli.";
                
                $telegramService->sendNotification('qualification_expiry', $message);
            }
        } catch (\Exception $e) {
            error_log("Telegram notification error: " . $e->getMessage());
        }
    }
    
    // Send email and Telegram notifications for courses
    if (!empty($expiringCourses)) {
        $groupedCourses = [];
        foreach ($expiringCourses as $course) {
            $memberId = $course['member_id'];
            if (!isset($groupedCourses[$memberId])) {
                $groupedCourses[$memberId] = [
                    'member' => [
                        'first_name' => $course['first_name'],
                        'last_name' => $course['last_name'],
                        'email' => $course['email'],
                        'telegram_id' => $course['telegram_id']
                    ],
                    'courses' => []
                ];
            }
            $groupedCourses[$memberId]['courses'][] = $course;
        }
        
        foreach ($groupedCourses as $memberId => $data) {
            // Email notification
            if (!empty($data['member']['email'])) {
                $subject = 'Alert Scadenza Corsi';
                $body = '<p>Gentile ' . htmlspecialchars($data['member']['first_name']) . ',</p>';
                $body .= '<p>I seguenti corsi stanno per scadere:</p><ul>';
                
                foreach ($data['courses'] as $course) {
                    $body .= '<li>' . htmlspecialchars($course['course_name']) . ' - ';
                    $body .= 'Scadenza: ' . date('d/m/Y', strtotime($course['expiry_date'])) . '</li>';
                }
                
                $body .= '</ul><p>Si prega di rinnovare i corsi in scadenza.</p>';
                
                $emailSender->queue($data['member']['email'], $subject, $body);
            }
            
            // Telegram notification
            if (!empty($data['member']['telegram_id'])) {
                try {
                    require_once __DIR__ . '/../src/Services/TelegramService.php';
                    $telegramService = new \EasyVol\Services\TelegramService($db, $config);
                    
                    if ($telegramService->isEnabled()) {
                        $message = "⚠️ <b>Alert Scadenza Corsi</b>\n\n";
                        $message .= "Gentile " . htmlspecialchars($data['member']['first_name']) . ",\n\n";
                        $message .= "I seguenti <b>corsi</b> stanno per scadere:\n\n";
                        
                        foreach ($data['courses'] as $course) {
                            $message .= "📚 <b>" . htmlspecialchars($course['course_name']) . "</b>\n";
                            $message .= "   📅 Scadenza: " . date('d/m/Y', strtotime($course['expiry_date'])) . "\n\n";
                        }
                        
                        $message .= "Si prega di rinnovare i corsi in scadenza.";
                        
                        $telegramService->sendMessage($data['member']['telegram_id'], $message);
                    }
                } catch (\Exception $e) {
                    error_log("Telegram notification error for course expiry: " . $e->getMessage());
                }
            }
        }
        
        // Send summary to configured recipients
        try {
            require_once __DIR__ . '/../src/Services/TelegramService.php';
            $telegramService = new \EasyVol\Services\TelegramService($db, $config);
            
            if ($telegramService->isEnabled()) {
                $message = "📚 <b>Riepilogo Scadenze Corsi</b>\n\n";
                $message .= "Ci sono <b>" . count($expiringCourses) . " corsi</b> in scadenza nei prossimi 30 giorni.\n\n";
                $message .= "Controlla il sistema per i dettagli.";
                
                $telegramService->sendNotification('course_expiry', $message);
            }
        } catch (\Exception $e) {
            error_log("Telegram notification error: " . $e->getMessage());
        }
    }
    
    // Log activity
    $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
            VALUES (NULL, 'cron', 'member_expiry_alerts', ?, NOW())";
    $db->execute($sql, [
        "Checked member expirations: " . count($expiringLicenses) . " licenses, " . 
        count($expiringQualifications) . " qualifications, " . count($expiringCourses) . " courses"
    ]);
    
    echo "[" . date('Y-m-d H:i:s') . "] Member expiry alerts job completed successfully\n";
    
} catch (\Exception $e) {
    error_log("Member expiry alerts cron error: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
