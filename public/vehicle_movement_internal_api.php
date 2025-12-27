<?php
/**
 * Internal Vehicle Movement API
 * 
 * API endpoints for internal vehicle movement operations
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\VehicleMovementController;

header('Content-Type: application/json');

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!$app->checkPermission('vehicles', 'view')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$action = $_GET['action'] ?? '';
$controller = new VehicleMovementController($db, $config);

try {
    switch ($action) {
        case 'complete_without_return':
            if (!$app->checkPermission('vehicles', 'edit')) {
                throw new \Exception('Permission denied');
            }
            
            $movementId = intval($_GET['movement_id'] ?? 0);
            if ($movementId <= 0) {
                throw new \Exception('Invalid movement ID');
            }
            
            $controller->completeWithoutReturn($movementId);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
