<?php
/**
 * API Endpoint: Send Fee Payment Reminders
 * 
 * Crea batch di promemoria per soci con quote non versate
 */

require_once __DIR__ . '/../../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\FeePaymentController;
use EasyVol\Middleware\CsrfProtection;

header('Content-Type: application/json');

try {
    $app = App::getInstance();
    
    // Verifica autenticazione
    if (!$app->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autenticato']);
        exit;
    }
    
    // Verifica permessi
    if (!$app->checkPermission('members', 'edit')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permesso negato']);
        exit;
    }
    
    // Handle POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verify CSRF token
    if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token di sicurezza non valido']);
        exit;
    }
    
    $year = intval($input['year'] ?? date('Y'));
    
    $db = $app->getDb();
    $config = $app->getConfig();
    $controller = new FeePaymentController($db, $config);
    
    // Check if can send reminders
    $checkResult = $controller->canSendReminders($year);
    
    if (!$checkResult['can_send']) {
        http_response_code(400);
        $daysSince = $checkResult['days_since'];
        $daysRemaining = 20 - $daysSince;
        echo json_encode([
            'success' => false, 
            'error' => "Promemoria già inviato {$daysSince} giorni fa. Potrai inviare nuovamente tra {$daysRemaining} giorni.",
            'last_sent' => $checkResult['last_sent'],
            'days_since' => $daysSince
        ]);
        exit;
    }
    
    // Create reminder batch
    $reminderId = $controller->createReminderBatch($year, $app->getUserId());
    
    if (!$reminderId) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Nessun socio trovato con quota non versata o errore durante la creazione del batch'
        ]);
        exit;
    }
    
    // Get batch details
    $stmt = $db->query(
        "SELECT fpr.*, COUNT(frm.id) as total_members
         FROM fee_payment_reminders fpr
         LEFT JOIN fee_payment_reminder_members frm ON fpr.id = frm.reminder_id
         WHERE fpr.id = ?
         GROUP BY fpr.id",
        [$reminderId]
    );
    $batch = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => "Batch di promemoria creato con successo. {$batch['total_members']} email saranno inviate tramite cron.",
        'reminder_id' => $reminderId,
        'total_members' => $batch['total_members']
    ]);
    
} catch (\Exception $e) {
    error_log("Error in send_fee_reminders API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore del server']);
}
