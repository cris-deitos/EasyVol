<?php
require_once '../../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\StructureController;

header('Content-Type: application/json');

$app = App::getInstance();
$controller = new StructureController($app->getDb(), $app->getConfig());

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
            // Check authentication and permissions
            if (!$app->isLoggedIn() || !$app->checkPermission('structure_management', 'view')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $structures = $controller->getAllStructures();
            echo json_encode([
                'success' => true,
                'structures' => $structures
            ]);
            break;

        case 'get':
            // Check authentication and permissions
            if (!$app->isLoggedIn() || !$app->checkPermission('structure_management', 'view')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $structureId = $_GET['id'] ?? $data['id'] ?? null;
            if (!$structureId) {
                echo json_encode(['success' => false, 'message' => 'ID struttura richiesto']);
                exit;
            }
            
            $structure = $controller->getStructureById($structureId);
            if ($structure) {
                echo json_encode(['success' => true, 'structure' => $structure]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Struttura non trovata']);
            }
            break;

        case 'create':
            // Check authentication and permissions
            if (!$app->isLoggedIn() || !$app->checkPermission('structure_management', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            // Validate required fields
            if (empty($data['name'])) {
                echo json_encode(['success' => false, 'message' => 'Il campo Nome è obbligatorio']);
                exit;
            }
            
            try {
                $userId = $app->getCurrentUser()['id'] ?? null;
                $structureId = $controller->createStructure($data, $userId);
                
                if ($structureId) {
                    // Log activity
                    $app->logActivity('create', 'structure_management', $structureId, 
                        "Creata nuova struttura: {$data['name']}");
                    echo json_encode(['success' => true, 'id' => $structureId]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Errore nella creazione della struttura']);
                }
            } catch (\InvalidArgumentException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'update':
            // Check authentication and permissions
            if (!$app->isLoggedIn() || !$app->checkPermission('structure_management', 'edit')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $structureId = $data['id'] ?? null;
            if (!$structureId) {
                echo json_encode(['success' => false, 'message' => 'ID struttura richiesto']);
                exit;
            }
            
            // Validate required fields
            if (empty($data['name'])) {
                echo json_encode(['success' => false, 'message' => 'Il campo Nome è obbligatorio']);
                exit;
            }
            
            try {
                $userId = $app->getCurrentUser()['id'] ?? null;
                $result = $controller->updateStructure($structureId, $data, $userId);
                
                if ($result) {
                    // Log activity
                    $app->logActivity('update', 'structure_management', $structureId, 
                        "Aggiornata struttura: {$data['name']}");
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiornamento della struttura']);
                }
            } catch (\InvalidArgumentException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'delete':
            // Check authentication and permissions
            if (!$app->isLoggedIn() || !$app->checkPermission('structure_management', 'delete')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $structureId = $data['id'] ?? null;
            if (!$structureId) {
                echo json_encode(['success' => false, 'message' => 'ID struttura richiesto']);
                exit;
            }
            
            // Get structure details for logging before deletion
            $structure = $controller->getStructureById($structureId);
            
            $result = $controller->deleteStructure($structureId);
            
            if ($result) {
                // Log activity
                $structureName = $structure['name'] ?? 'Sconosciuta';
                $app->logActivity('delete', 'structure_management', $structureId, 
                    "Eliminata struttura: {$structureName}");
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore nell\'eliminazione della struttura']);
            }
            break;

        case 'get_with_coordinates':
            // Check authentication and permissions
            if (!$app->isLoggedIn() || !$app->checkPermission('structure_management', 'view')) {
                echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                exit;
            }
            
            $structures = $controller->getStructuresWithCoordinates();
            echo json_encode([
                'success' => true,
                'structures' => $structures
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
            break;
    }
    
} catch (\Exception $e) {
    error_log("Errore API structures: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
