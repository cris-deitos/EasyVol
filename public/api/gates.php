<?php
require_once '../../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\GateController;

header('Content-Type: application/json');

$app = App::getInstance();
$controller = new GateController($app->getDb(), $app->getConfig());

// Get request data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: [];

// GET action from query string or POST data
$action = $_GET['action'] ?? $data['action'] ?? '';

// Handle different actions
try {
    switch ($action) {
        case 'list':
            // Public endpoint - no authentication required for list (used by public pages)
            $gates = $controller->getAllGates();
            $systemStatus = $controller->getSystemStatus();
            echo json_encode([
                'success' => true,
                'gates' => $gates,
                'system_active' => $systemStatus['is_active']
            ]);
            break;

        case 'get':
            // Public endpoint for getting single gate
            $gateId = $_GET['id'] ?? $data['id'] ?? null;
            if (!$gateId) {
                echo json_encode(['success' => false, 'message' => 'ID varco richiesto']);
                exit;
            }
            
            $gate = $controller->getGateById($gateId);
            if ($gate) {
                echo json_encode(['success' => true, 'gate' => $gate]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Varco non trovato']);
            }
            break;

        case 'system_status':
            // Public endpoint for checking system status
            $status = $controller->getSystemStatus();
            echo json_encode([
                'success' => true,
                'is_active' => $status['is_active']
            ]);
            break;

        case 'total_count':
            // Public endpoint for total count
            $total = $controller->getTotalPeopleCount();
            echo json_encode([
                'success' => true,
                'total' => $total
            ]);
            break;

        // Admin actions - require authentication
        case 'toggle_system':
            if (!$app->isLoggedIn() || !$app->checkPermission('gate_management', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $isActive = $data['is_active'] ?? false;
            $userId = $app->getCurrentUser()['id'] ?? null;
            $result = $controller->setSystemStatus($isActive, $userId);
            
            echo json_encode(['success' => $result]);
            break;

        case 'create':
            if (!$app->isLoggedIn() || !$app->checkPermission('gate_management', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $gateId = $controller->createGate($data);
            if ($gateId) {
                echo json_encode(['success' => true, 'id' => $gateId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore nella creazione del varco']);
            }
            break;

        case 'update':
            if (!$app->isLoggedIn() || !$app->checkPermission('gate_management', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $gateId = $data['id'] ?? null;
            if (!$gateId) {
                echo json_encode(['success' => false, 'message' => 'ID varco richiesto']);
                exit;
            }
            
            $result = $controller->updateGate($gateId, $data);
            echo json_encode(['success' => $result]);
            break;

        case 'delete':
            if (!$app->isLoggedIn() || !$app->checkPermission('gate_management', 'delete')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $gateId = $data['id'] ?? null;
            if (!$gateId) {
                echo json_encode(['success' => false, 'message' => 'ID varco richiesto']);
                exit;
            }
            
            $result = $controller->deleteGate($gateId);
            echo json_encode(['success' => $result]);
            break;

        // Public actions for gate management
        case 'add_person':
            $gateId = $data['id'] ?? null;
            if (!$gateId) {
                echo json_encode(['success' => false, 'message' => 'ID varco richiesto']);
                exit;
            }
            
            // Check system status
            $status = $controller->getSystemStatus();
            if (!$status['is_active']) {
                echo json_encode(['success' => false, 'message' => 'Sistema disattivato']);
                exit;
            }
            
            $result = $controller->addPerson($gateId);
            if ($result) {
                $gate = $controller->getGateById($gateId);
                echo json_encode(['success' => true, 'gate' => $gate]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiunta persona']);
            }
            break;

        case 'remove_person':
            $gateId = $data['id'] ?? null;
            if (!$gateId) {
                echo json_encode(['success' => false, 'message' => 'ID varco richiesto']);
                exit;
            }
            
            // Check system status
            $status = $controller->getSystemStatus();
            if (!$status['is_active']) {
                echo json_encode(['success' => false, 'message' => 'Sistema disattivato']);
                exit;
            }
            
            $result = $controller->removePerson($gateId);
            if ($result) {
                $gate = $controller->getGateById($gateId);
                echo json_encode(['success' => true, 'gate' => $gate]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore nella rimozione persona']);
            }
            break;

        case 'open_gate':
            $gateId = $data['id'] ?? null;
            if (!$gateId) {
                echo json_encode(['success' => false, 'message' => 'ID varco richiesto']);
                exit;
            }
            
            // Check system status
            $status = $controller->getSystemStatus();
            if (!$status['is_active']) {
                echo json_encode(['success' => false, 'message' => 'Sistema disattivato']);
                exit;
            }
            
            $result = $controller->openGate($gateId);
            if ($result) {
                $gate = $controller->getGateById($gateId);
                echo json_encode(['success' => true, 'gate' => $gate]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore nell\'apertura varco']);
            }
            break;

        case 'close_gate':
            $gateId = $data['id'] ?? null;
            if (!$gateId) {
                echo json_encode(['success' => false, 'message' => 'ID varco richiesto']);
                exit;
            }
            
            // Check system status
            $status = $controller->getSystemStatus();
            if (!$status['is_active']) {
                echo json_encode(['success' => false, 'message' => 'Sistema disattivato']);
                exit;
            }
            
            $result = $controller->closeGate($gateId);
            if ($result) {
                $gate = $controller->getGateById($gateId);
                echo json_encode(['success' => true, 'gate' => $gate]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore nella chiusura varco']);
            }
            break;

        case 'set_count':
            if (!$app->isLoggedIn() || !$app->checkPermission('gate_management', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $gateId = $data['id'] ?? null;
            $count = $data['count'] ?? null;
            
            if (!$gateId || $count === null) {
                echo json_encode(['success' => false, 'message' => 'ID varco e conteggio richiesti']);
                exit;
            }
            
            $result = $controller->setManualCount($gateId, $count);
            echo json_encode(['success' => $result]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
