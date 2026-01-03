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

try {
    $controller = new DispatchController($app->getDb(), $app->getConfig());
    $events = $controller->getRecentEvents(50);
    
    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
