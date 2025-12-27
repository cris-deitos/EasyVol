<?php
/**
 * Event AJAX Handler
 * 
 * Handles AJAX requests for event management (interventions, participants, vehicles)
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\EventController;
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
$controller = new EventController($db, $config);
$csrf = new CsrfProtection();

// Parse JSON input for POST requests
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
        case 'add_intervention':
            // Add intervention to event
            if (!$app->checkPermission('events', 'edit')) {
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
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $eventId = intval($input['event_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $startTime = trim($input['start_time'] ?? '');
            
            if ($eventId <= 0 || empty($title) || empty($startTime)) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $data = [
                'title' => $title,
                'description' => trim($input['description'] ?? ''),
                'start_time' => $startTime,
                'end_time' => !empty($input['end_time']) ? trim($input['end_time']) : null,
                'location' => trim($input['location'] ?? ''),
                'status' => $input['status'] ?? 'in_corso'
            ];
            
            $result = $controller->addIntervention($eventId, $data, $app->getUserId());
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Intervento aggiunto con successo', 'id' => $result]);
            } else {
                echo json_encode(['error' => 'Errore durante l\'aggiunta dell\'intervento']);
            }
            break;
            
        case 'search_members':
            // Search available members for adding to event
            if (!$app->checkPermission('events', 'view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            $eventId = intval($_GET['event_id'] ?? 0);
            $search = trim($_GET['search'] ?? '');
            
            if ($eventId <= 0) {
                echo json_encode(['error' => 'ID evento non valido']);
                exit;
            }
            
            $members = $controller->getAvailableMembers($eventId, $search);
            echo json_encode(['success' => true, 'members' => $members]);
            break;
            
        case 'add_participant':
            // Add participant to event
            if (!$app->checkPermission('events', 'edit')) {
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
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $eventId = intval($input['event_id'] ?? 0);
            $memberId = intval($input['member_id'] ?? 0);
            
            if ($eventId <= 0 || $memberId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $result = $controller->addParticipant($eventId, $memberId, $app->getUserId());
            
            if ($result === true) {
                echo json_encode(['success' => true, 'message' => 'Partecipante aggiunto con successo']);
            } elseif (is_array($result) && isset($result['error'])) {
                echo json_encode(['error' => $result['error']]);
            } else {
                echo json_encode(['error' => 'Errore durante l\'aggiunta del partecipante']);
            }
            break;
            
        case 'search_vehicles':
            // Search available vehicles for adding to event
            if (!$app->checkPermission('events', 'view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            $eventId = intval($_GET['event_id'] ?? 0);
            $search = trim($_GET['search'] ?? '');
            
            if ($eventId <= 0) {
                echo json_encode(['error' => 'ID evento non valido']);
                exit;
            }
            
            $vehicles = $controller->getAvailableVehicles($eventId, $search);
            echo json_encode(['success' => true, 'vehicles' => $vehicles]);
            break;
            
        case 'add_vehicle':
            // Add vehicle to event
            if (!$app->checkPermission('events', 'edit')) {
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
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $eventId = intval($input['event_id'] ?? 0);
            $vehicleId = intval($input['vehicle_id'] ?? 0);
            
            if ($eventId <= 0 || $vehicleId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $result = $controller->addVehicle($eventId, $vehicleId, $app->getUserId());
            
            if ($result === true) {
                echo json_encode(['success' => true, 'message' => 'Veicolo aggiunto con successo']);
            } elseif (is_array($result) && isset($result['error'])) {
                echo json_encode(['error' => $result['error']]);
            } else {
                echo json_encode(['error' => 'Errore durante l\'aggiunta del veicolo']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
    }
    
} catch (Exception $e) {
    error_log("Event AJAX error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}
