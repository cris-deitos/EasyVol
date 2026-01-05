<?php
/**
 * Vehicle Movement API
 * 
 * API endpoints for vehicle movement operations
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\VehicleMovementController;

header('Content-Type: application/json');

$app = App::getInstance();
$db = $app->getDb();
$config = $app->getConfig();

// Check if member is authenticated
if (!isset($_SESSION['vehicle_movement_member'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$controller = new VehicleMovementController($db, $config);

try {
    switch ($action) {
        case 'search_drivers':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => false, 'message' => 'Query too short']);
                exit;
            }
            
            $drivers = $controller->searchMembers($query);
            echo json_encode(['success' => true, 'drivers' => $drivers]);
            break;
            
        case 'get_checklists':
            $vehicleId = intval($_GET['vehicle_id'] ?? 0);
            $timing = $_GET['timing'] ?? null;
            $trailerId = !empty($_GET['trailer_id']) ? intval($_GET['trailer_id']) : null;
            
            if ($vehicleId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID']);
                exit;
            }
            
            $checklists = $controller->getVehicleChecklists($vehicleId, $timing, $trailerId);
            echo json_encode(['success' => true, 'checklists' => $checklists]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
