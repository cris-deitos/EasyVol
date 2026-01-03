<?php
require_once '../../../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../../../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\DispatchController;

header('Content-Type: application/json');

// API Key authentication for Raspberry Pi
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$app = App::getInstance();
$controller = new DispatchController($app->getDb(), $app->getConfig());
$config = $controller->getRaspberryConfig();

if (empty($config['api_enabled']) || $config['api_enabled'] !== '1') {
    http_response_code(503);
    echo json_encode(['error' => 'API non attiva']);
    exit;
}

if (!empty($config['api_key']) && $apiKey !== $config['api_key']) {
    http_response_code(401);
    echo json_encode(['error' => 'API key non valida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non permesso']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['event_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'event_type richiesto']);
        exit;
    }
    
    $eventData = [
        'slot' => $data['slot'] ?? null,
        'radio_dmr_id' => $data['radio_dmr_id'] ?? null,
        'talkgroup_id' => $data['talkgroup_id'] ?? null,
        'event_data' => $data['event_data'] ?? null,
        'event_timestamp' => $data['event_timestamp'] ?? date('Y-m-d H:i:s')
    ];
    
    $eventId = $controller->logEvent($data['event_type'], $eventData);
    
    echo json_encode([
        'success' => true,
        'event_id' => $eventId,
        'message' => 'Evento registrato'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
