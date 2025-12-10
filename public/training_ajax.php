<?php
/**
 * Training Participant AJAX Handler
 * 
 * Handles AJAX requests for training participant management
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\TrainingController;
use EasyVol\Middleware\CsrfProtection;

header('Content-Type: application/json');

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new TrainingController($db, $config);
$csrf = new CsrfProtection();

// Parse JSON input for POST requests - needed to get action from JSON body
$jsonInput = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check content length to prevent memory issues (max 1MB for AJAX requests)
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 1048576) { // 1MB limit
        http_response_code(413);
        echo json_encode(['error' => 'Payload troppo grande']);
        exit;
    }
    
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonInput = json_decode($rawInput, true);
        // Handle JSON parsing errors
        if ($jsonInput === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON non valido']);
            exit;
        }
    }
}

// Get action from JSON body first, then fallback to $_REQUEST
$action = $jsonInput['action'] ?? $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'search_members':
            // Search available members for adding to a course
            if (!$app->checkPermission('training', 'view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            $courseId = intval($_GET['course_id'] ?? 0);
            $search = trim($_GET['search'] ?? '');
            
            if ($courseId <= 0) {
                echo json_encode(['error' => 'ID corso non valido']);
                exit;
            }
            
            $members = $controller->getAvailableMembers($courseId, $search);
            echo json_encode(['success' => true, 'members' => $members]);
            break;
            
        case 'add_participant':
            // Add participant to course
            if (!$app->checkPermission('training', 'edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Metodo non consentito']);
                exit;
            }
            
            // Use already parsed JSON input or fallback to $_POST
            $input = $jsonInput ?? $_POST;
            
            if (!$csrf->validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $courseId = intval($input['course_id'] ?? 0);
            $memberId = intval($input['member_id'] ?? 0);
            
            if ($courseId <= 0 || $memberId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $result = $controller->addParticipant($courseId, $memberId, $app->getUserId());
            
            if ($result === true) {
                echo json_encode(['success' => true, 'message' => 'Partecipante aggiunto con successo']);
            } elseif (is_array($result) && isset($result['error'])) {
                echo json_encode(['error' => $result['error']]);
            } else {
                echo json_encode(['error' => 'Errore durante l\'aggiunta del partecipante']);
            }
            break;
            
        case 'get_participant':
            // Get participant details
            if (!$app->checkPermission('training', 'view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            $participantId = intval($_GET['participant_id'] ?? 0);
            
            if ($participantId <= 0) {
                echo json_encode(['error' => 'ID partecipante non valido']);
                exit;
            }
            
            $participant = $controller->getParticipant($participantId);
            
            if ($participant) {
                echo json_encode(['success' => true, 'participant' => $participant]);
            } else {
                echo json_encode(['error' => 'Partecipante non trovato']);
            }
            break;
            
        case 'update_participant':
            // Update participant
            if (!$app->checkPermission('training', 'edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Metodo non consentito']);
                exit;
            }
            
            // Use already parsed JSON input or fallback to $_POST
            $input = $jsonInput ?? $_POST;
            
            if (!$csrf->validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $participantId = intval($input['participant_id'] ?? 0);
            
            if ($participantId <= 0) {
                echo json_encode(['error' => 'ID partecipante non valido']);
                exit;
            }
            
            // Validate exam_score if provided
            if (isset($input['exam_score']) && $input['exam_score'] !== '') {
                $score = intval($input['exam_score']);
                if ($score < 1 || $score > 10) {
                    echo json_encode(['error' => 'Il punteggio esame deve essere tra 1 e 10']);
                    exit;
                }
            }
            
            $data = [
                'attendance_status' => $input['attendance_status'] ?? 'iscritto',
                'final_grade' => $input['final_grade'] ?? null,
                'exam_passed' => $input['exam_passed'] ?? null,
                'exam_score' => $input['exam_score'] ?? null,
                'certificate_issued' => isset($input['certificate_issued']) ? (int)$input['certificate_issued'] : 0,
                'certificate_file' => $input['certificate_file'] ?? null
            ];
            
            $result = $controller->updateParticipant($participantId, $data, $app->getUserId());
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Partecipante aggiornato con successo']);
            } else {
                echo json_encode(['error' => 'Errore durante l\'aggiornamento']);
            }
            break;
            
        case 'remove_participant':
            // Remove participant
            if (!$app->checkPermission('training', 'edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Metodo non consentito']);
                exit;
            }
            
            // Use already parsed JSON input or fallback to $_POST
            $input = $jsonInput ?? $_POST;
            
            if (!$csrf->validate($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $participantId = intval($input['participant_id'] ?? 0);
            
            if ($participantId <= 0) {
                echo json_encode(['error' => 'ID partecipante non valido']);
                exit;
            }
            
            $result = $controller->removeParticipant($participantId, $app->getUserId());
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Partecipante rimosso con successo']);
            } else {
                echo json_encode(['error' => 'Errore durante la rimozione']);
            }
            break;
            
        case 'get_participant_stats':
            // Get participant statistics
            if (!$app->checkPermission('training', 'view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            $participantId = intval($_GET['participant_id'] ?? 0);
            
            if ($participantId <= 0) {
                echo json_encode(['error' => 'ID partecipante non valido']);
                exit;
            }
            
            $stats = $controller->getParticipantStats($participantId);
            
            if ($stats) {
                echo json_encode(['success' => true, 'stats' => $stats]);
            } else {
                echo json_encode(['error' => 'Statistiche non disponibili']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
    }
    
} catch (Exception $e) {
    error_log("Training AJAX error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}
