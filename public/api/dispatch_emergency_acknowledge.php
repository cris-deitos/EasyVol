<?php
require_once '../../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\DispatchController;

header('Content-Type: application/json');

$app = App::getInstance();

// Check authentication
if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accesso negato']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non permesso']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['emergency_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID emergenza mancante']);
        exit;
    }
    
    $controller = new DispatchController($app->getDb(), $app->getConfig());
    $controller->acknowledgeEmergency($data['emergency_id'], $app->getUserId(), $data['notes'] ?? null);
    
    echo json_encode(['success' => true, 'message' => 'Emergenza ricevuta']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
