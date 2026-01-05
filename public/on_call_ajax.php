<?php
/**
 * On-Call Schedule AJAX Handler
 * 
 * Handles AJAX requests for on-call/availability management
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Middleware\CsrfProtection;

header('Content-Type: application/json');

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$db = $app->getDb();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add_on_call':
            // Add volunteer to on-call schedule
            if (!$app->checkPermission('operations_center', 'edit')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permessi insufficienti']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
                exit;
            }
            
            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $memberId = intval($_POST['member_id'] ?? 0);
            $startDatetime = trim($_POST['start_datetime'] ?? '');
            $endDatetime = trim($_POST['end_datetime'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if ($memberId <= 0 || empty($startDatetime) || empty($endDatetime)) {
                echo json_encode(['success' => false, 'message' => 'Parametri non validi']);
                exit;
            }
            
            // Validate dates
            $start = new DateTime($startDatetime);
            $end = new DateTime($endDatetime);
            
            if ($end <= $start) {
                echo json_encode(['success' => false, 'message' => 'La data di fine deve essere successiva alla data di inizio']);
                exit;
            }
            
            // Check for overlapping schedules for the same member
            // Two date ranges overlap if: start1 < end2 AND start2 < end1
            $sql = "SELECT COUNT(*) as count FROM on_call_schedule 
                    WHERE member_id = ? 
                    AND start_datetime < ? 
                    AND end_datetime > ?";
            $result = $db->fetchOne($sql, [
                $memberId,
                $endDatetime,
                $startDatetime
            ]);
            
            if ($result['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Il volontario ha già una reperibilità programmata in questo periodo']);
                exit;
            }
            
            // Insert on-call schedule
            $sql = "INSERT INTO on_call_schedule (member_id, start_datetime, end_datetime, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?)";
            $db->execute($sql, [$memberId, $startDatetime, $endDatetime, $notes, $app->getUserId()]);
            
            echo json_encode(['success' => true, 'message' => 'Reperibilità aggiunta con successo']);
            break;
            
        case 'update_on_call':
            // Update on-call schedule
            if (!$app->checkPermission('operations_center', 'edit')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permessi insufficienti']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
                exit;
            }
            
            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $scheduleId = intval($_POST['schedule_id'] ?? 0);
            $startDatetime = trim($_POST['start_datetime'] ?? '');
            $endDatetime = trim($_POST['end_datetime'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if ($scheduleId <= 0 || empty($startDatetime) || empty($endDatetime)) {
                echo json_encode(['success' => false, 'message' => 'Parametri non validi']);
                exit;
            }
            
            // Validate dates
            $start = new DateTime($startDatetime);
            $end = new DateTime($endDatetime);
            
            if ($end <= $start) {
                echo json_encode(['success' => false, 'message' => 'La data di fine deve essere successiva alla data di inizio']);
                exit;
            }
            
            // Get member_id for overlap check
            $sql = "SELECT member_id FROM on_call_schedule WHERE id = ?";
            $schedule = $db->fetchOne($sql, [$scheduleId]);
            
            if (!$schedule) {
                echo json_encode(['success' => false, 'message' => 'Reperibilità non trovata']);
                exit;
            }
            
            // Check for overlapping schedules for the same member (excluding current schedule)
            // Two date ranges overlap if: start1 < end2 AND start2 < end1
            $sql = "SELECT COUNT(*) as count FROM on_call_schedule 
                    WHERE member_id = ? 
                    AND id != ?
                    AND start_datetime < ? 
                    AND end_datetime > ?";
            $result = $db->fetchOne($sql, [
                $schedule['member_id'],
                $scheduleId,
                $endDatetime,
                $startDatetime
            ]);
            
            if ($result['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Il volontario ha già una reperibilità programmata in questo periodo']);
                exit;
            }
            
            // Update schedule
            $sql = "UPDATE on_call_schedule 
                    SET start_datetime = ?, end_datetime = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?";
            $db->execute($sql, [$startDatetime, $endDatetime, $notes, $scheduleId]);
            
            echo json_encode(['success' => true, 'message' => 'Reperibilità aggiornata con successo']);
            break;
            
        case 'remove_on_call':
            // Remove volunteer from on-call schedule
            if (!$app->checkPermission('operations_center', 'edit')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permessi insufficienti']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
                exit;
            }
            
            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $scheduleId = intval($_POST['schedule_id'] ?? 0);
            
            if ($scheduleId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID non valido']);
                exit;
            }
            
            $sql = "DELETE FROM on_call_schedule WHERE id = ?";
            $db->execute($sql, [$scheduleId]);
            
            echo json_encode(['success' => true, 'message' => 'Reperibilità rimossa']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
    
} catch (Exception $e) {
    error_log("On-call AJAX error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
