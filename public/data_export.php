<?php
/**
 * Universal Data Export API
 * 
 * Handles export of all data tables to Excel/CSV format
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ReportController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(403);
    die(json_encode(['error' => 'Accesso negato']));
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ReportController($db, $config);

// Get parameters
$entity = $_GET['entity'] ?? '';
$format = $_GET['format'] ?? 'excel'; // excel or csv

// Validate format
if (!in_array($format, ['excel', 'csv'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Formato non valido. Usare excel o csv']));
}

try {
    // Check permissions and export based on entity
    switch ($entity) {
        case 'members':
            if (!$app->checkPermission('members', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati dei soci']));
            }
            AutoLogger::logActivity('members', 'export', null, "Export dati soci - formato {$format}");
            $controller->exportMembers($format);
            break;
            
        case 'junior_members':
            if (!$app->checkPermission('junior_members', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati dei cadetti']));
            }
            AutoLogger::logActivity('junior_members', 'export', null, "Export dati cadetti - formato {$format}");
            $controller->exportJuniorMembers($format);
            break;
            
        case 'meetings':
            if (!$app->checkPermission('meetings', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati delle riunioni']));
            }
            AutoLogger::logActivity('meetings', 'export', null, "Export dati riunioni - formato {$format}");
            $controller->exportMeetings($format);
            break;
            
        case 'vehicles':
            if (!$app->checkPermission('vehicles', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati dei mezzi']));
            }
            AutoLogger::logActivity('vehicles', 'export', null, "Export dati mezzi - formato {$format}");
            $controller->exportVehicles($format);
            break;
            
        case 'warehouse':
            if (!$app->checkPermission('warehouse', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati del magazzino']));
            }
            AutoLogger::logActivity('warehouse', 'export', null, "Export dati magazzino - formato {$format}");
            $controller->exportWarehouse($format);
            break;
            
        case 'structures':
            if (!$app->checkPermission('structure_management', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati delle strutture']));
            }
            AutoLogger::logActivity('structure_management', 'export', null, "Export dati strutture - formato {$format}");
            $controller->exportStructures($format);
            break;
            
        case 'training':
            if (!$app->checkPermission('training', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati della formazione']));
            }
            AutoLogger::logActivity('training', 'export', null, "Export dati formazione - formato {$format}");
            $controller->exportTraining($format);
            break;
            
        case 'events':
            if (!$app->checkPermission('events', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare i dati degli eventi']));
            }
            AutoLogger::logActivity('events', 'export', null, "Export dati eventi - formato {$format}");
            $controller->exportEvents($format);
            break;
            
        case 'scheduler':
            if (!$app->checkPermission('scheduler', 'export')) {
                http_response_code(403);
                die(json_encode(['error' => 'Permessi insufficienti per esportare lo scadenzario']));
            }
            AutoLogger::logActivity('scheduler', 'export', null, "Export scadenzario - formato {$format}");
            $controller->exportScheduler($format);
            break;
            
        default:
            http_response_code(400);
            die(json_encode(['error' => 'Entità non valida o non supportata']));
    }
    
} catch (\Exception $e) {
    error_log("Errore export dati: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Errore durante l\'export: ' . $e->getMessage()]));
}
