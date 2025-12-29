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

// Maximum AJAX payload size (1MB)
const MAX_AJAX_PAYLOAD_SIZE = 1048576;

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
    // Check content length to prevent memory issues
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > MAX_AJAX_PAYLOAD_SIZE) {
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
                'status' => $input['status'] ?? 'in_corso',
                'latitude' => !empty($input['latitude']) ? floatval($input['latitude']) : null,
                'longitude' => !empty($input['longitude']) ? floatval($input['longitude']) : null,
                'full_address' => trim($input['full_address'] ?? ''),
                'municipality' => trim($input['municipality'] ?? '')
            ];
            
            $result = $controller->addIntervention($eventId, $data, $app->getUserId());
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Intervento aggiunto con successo', 
                    'id' => $result
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Errore durante l\'aggiunta dell\'intervento. Verificare i log del server.'
                ]);
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
            
        case 'add_intervention_participant':
            // Add participant to intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $memberId = intval($input['member_id'] ?? 0);
            $role = trim($input['role'] ?? '');
            
            if ($interventionId <= 0 || $memberId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $result = $controller->addInterventionParticipant($interventionId, $memberId, $role, $app->getUserId());
            
            if ($result === true) {
                echo json_encode(['success' => true, 'message' => 'Partecipante aggiunto all\'intervento']);
            } elseif (is_array($result) && isset($result['error'])) {
                echo json_encode(['error' => $result['error']]);
            } else {
                echo json_encode(['error' => 'Errore durante l\'aggiunta del partecipante']);
            }
            break;
            
        case 'add_intervention_vehicle':
            // Add vehicle to intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $vehicleId = intval($input['vehicle_id'] ?? 0);
            
            if ($interventionId <= 0 || $vehicleId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $result = $controller->addInterventionVehicle($interventionId, $vehicleId, $app->getUserId());
            
            if ($result === true) {
                echo json_encode(['success' => true, 'message' => 'Veicolo aggiunto all\'intervento']);
            } elseif (is_array($result) && isset($result['error'])) {
                echo json_encode(['error' => $result['error']]);
            } else {
                echo json_encode(['error' => 'Errore durante l\'aggiunta del veicolo']);
            }
            break;
            
        case 'get_intervention':
            // Get intervention details
            if (!$app->checkPermission('events', 'view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            $interventionId = intval($_GET['intervention_id'] ?? 0);
            
            if ($interventionId <= 0) {
                echo json_encode(['error' => 'ID intervento non valido']);
                exit;
            }
            
            $intervention = $controller->getIntervention($interventionId);
            
            if (!$intervention) {
                http_response_code(404);
                echo json_encode(['error' => 'Intervento non trovato']);
                exit;
            }
            
            // Verify user has access to the event containing this intervention
            $event = $controller->get($intervention['event_id']);
            if (!$event) {
                http_response_code(404);
                echo json_encode(['error' => 'Evento non trovato']);
                exit;
            }
            
            echo json_encode(['success' => true, 'intervention' => $intervention]);
            break;
            
        case 'update_intervention':
            // Update intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $startTime = trim($input['start_time'] ?? '');
            
            if ($interventionId <= 0 || empty($title) || empty($startTime)) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $data = [
                'title' => $title,
                'description' => trim($input['description'] ?? ''),
                'start_time' => $startTime,
                'end_time' => !empty($input['end_time']) ? trim($input['end_time']) : null,
                'location' => trim($input['location'] ?? ''),
                'status' => $input['status'] ?? 'in_corso',
                'latitude' => !empty($input['latitude']) ? floatval($input['latitude']) : null,
                'longitude' => !empty($input['longitude']) ? floatval($input['longitude']) : null,
                'full_address' => trim($input['full_address'] ?? ''),
                'municipality' => trim($input['municipality'] ?? '')
            ];
            
            $result = $controller->updateIntervention($interventionId, $data, $app->getUserId());
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Intervento aggiornato con successo']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Errore durante l\'aggiornamento dell\'intervento']);
            }
            break;
            
        case 'close_intervention':
            // Close intervention with report
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $report = trim($input['report'] ?? '');
            
            if ($interventionId <= 0 || empty($report)) {
                echo json_encode(['error' => 'ID intervento e esito sono obbligatori']);
                exit;
            }
            
            $endTime = !empty($input['end_time']) ? trim($input['end_time']) : null;
            
            $result = $controller->closeIntervention($interventionId, $report, $endTime, $app->getUserId());
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Intervento chiuso con successo']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Errore durante la chiusura dell\'intervento']);
            }
            break;
            
        case 'reopen_intervention':
            // Reopen a closed intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            
            if ($interventionId <= 0) {
                echo json_encode(['error' => 'ID intervento non valido']);
                exit;
            }
            
            $result = $controller->reopenIntervention($interventionId, $app->getUserId());
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Intervento riaperto con successo']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Errore durante la riapertura dell\'intervento']);
            }
            break;
            
        case 'get_intervention_members':
            // Get members assigned to an intervention
            $interventionId = intval($_GET['intervention_id'] ?? 0);
            
            if ($interventionId <= 0) {
                echo json_encode(['error' => 'ID intervento non valido']);
                exit;
            }
            
            $sql = "SELECT im.*, m.first_name, m.last_name, m.registration_number 
                    FROM intervention_members im
                    JOIN members m ON im.member_id = m.id
                    WHERE im.intervention_id = ?
                    ORDER BY m.last_name, m.first_name";
            $members = $db->fetchAll($sql, [$interventionId]);
            
            echo json_encode(['success' => true, 'members' => $members]);
            break;
            
        case 'get_intervention_vehicles':
            // Get vehicles assigned to an intervention
            $interventionId = intval($_GET['intervention_id'] ?? 0);
            
            if ($interventionId <= 0) {
                echo json_encode(['error' => 'ID intervento non valido']);
                exit;
            }
            
            $sql = "SELECT iv.*, v.* 
                    FROM intervention_vehicles iv
                    JOIN vehicles v ON iv.vehicle_id = v.id
                    WHERE iv.intervention_id = ?
                    ORDER BY v.name, v.license_plate";
            $vehicles = $db->fetchAll($sql, [$interventionId]);
            
            echo json_encode(['success' => true, 'vehicles' => $vehicles]);
            break;
            
        case 'add_intervention_member':
            // Add member to intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $memberId = intval($input['member_id'] ?? 0);
            
            if ($interventionId <= 0 || $memberId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            // Check if already assigned
            $sql = "SELECT COUNT(*) as count FROM intervention_members WHERE intervention_id = ? AND member_id = ?";
            $result = $db->fetchOne($sql, [$interventionId, $memberId]);
            
            if ($result['count'] > 0) {
                echo json_encode(['error' => 'Volontario già assegnato a questo intervento']);
                exit;
            }
            
            // Insert
            $sql = "INSERT INTO intervention_members (intervention_id, member_id) VALUES (?, ?)";
            $db->execute($sql, [$interventionId, $memberId]);
            
            echo json_encode(['success' => true, 'message' => 'Volontario aggiunto all\'intervento']);
            break;
            
        case 'remove_intervention_member':
            // Remove member from intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $memberId = intval($input['member_id'] ?? 0);
            
            if ($interventionId <= 0 || $memberId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $sql = "DELETE FROM intervention_members WHERE intervention_id = ? AND member_id = ?";
            $db->execute($sql, [$interventionId, $memberId]);
            
            echo json_encode(['success' => true, 'message' => 'Volontario rimosso dall\'intervento']);
            break;
            
        case 'add_intervention_vehicle':
            // Add vehicle to intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $vehicleId = intval($input['vehicle_id'] ?? 0);
            
            if ($interventionId <= 0 || $vehicleId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            // Check if already assigned
            $sql = "SELECT COUNT(*) as count FROM intervention_vehicles WHERE intervention_id = ? AND vehicle_id = ?";
            $result = $db->fetchOne($sql, [$interventionId, $vehicleId]);
            
            if ($result['count'] > 0) {
                echo json_encode(['error' => 'Mezzo già assegnato a questo intervento']);
                exit;
            }
            
            // Insert
            $sql = "INSERT INTO intervention_vehicles (intervention_id, vehicle_id) VALUES (?, ?)";
            $db->execute($sql, [$interventionId, $vehicleId]);
            
            echo json_encode(['success' => true, 'message' => 'Mezzo aggiunto all\'intervento']);
            break;
            
        case 'remove_intervention_vehicle':
            // Remove vehicle from intervention
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $interventionId = intval($input['intervention_id'] ?? 0);
            $vehicleId = intval($input['vehicle_id'] ?? 0);
            
            if ($interventionId <= 0 || $vehicleId <= 0) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            $sql = "DELETE FROM intervention_vehicles WHERE intervention_id = ? AND vehicle_id = ?";
            $db->execute($sql, [$interventionId, $vehicleId]);
            
            echo json_encode(['success' => true, 'message' => 'Mezzo rimosso dall\'intervento']);
            break;
            
        case 'check_active_interventions':
            // Check if event has active interventions before closing
            if (!$app->checkPermission('events', 'edit')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permesso negato']);
                exit;
            }
            
            $eventId = intval($_GET['event_id'] ?? 0);
            
            if ($eventId <= 0) {
                echo json_encode(['error' => 'ID evento non valido']);
                exit;
            }
            
            // Optimize: fetch interventions directly instead of counting first
            $activeInterventions = $controller->getActiveInterventions($eventId);
            $hasActive = !empty($activeInterventions);
            
            echo json_encode([
                'success' => true, 
                'has_active' => $hasActive,
                'interventions' => $activeInterventions
            ]);
            break;
            
        case 'quick_close_event':
            // Quick close event with description update
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
            
            $input = $jsonInput ?? $_POST;
            
            if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token di sicurezza non valido']);
                exit;
            }
            
            $eventId = intval($input['event_id'] ?? 0);
            $description = trim($input['description'] ?? '');
            $endDate = trim($input['end_date'] ?? '');
            
            if ($eventId <= 0 || empty($endDate)) {
                echo json_encode(['error' => 'Parametri non validi']);
                exit;
            }
            
            try {
                $result = $controller->quickClose($eventId, $description, $endDate, $app->getUserId());
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Evento chiuso con successo']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Errore durante la chiusura dell\'evento']);
                }
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
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
