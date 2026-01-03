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
    
    if (empty($data['radio_dmr_id']) || empty($data['latitude']) || empty($data['longitude'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Parametri mancanti: radio_dmr_id, latitude, longitude richiesti']);
        exit;
    }
    
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    $additionalData = [
        'altitude' => $data['altitude'] ?? null,
        'speed' => $data['speed'] ?? null,
        'heading' => $data['heading'] ?? null,
        'accuracy' => $data['accuracy'] ?? null
    ];
    
    $positionId = $controller->savePosition(
        $data['radio_dmr_id'],
        $data['latitude'],
        $data['longitude'],
        $timestamp,
        $additionalData
    );
    
    // Log event
    $controller->logEvent('position_update', [
        'radio_dmr_id' => $data['radio_dmr_id'],
        'event_data' => [
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'altitude' => $additionalData['altitude'],
            'speed' => $additionalData['speed']
        ],
        'event_timestamp' => $timestamp
    ]);
    
    echo json_encode([
        'success' => true,
        'position_id' => $positionId,
        'message' => 'Posizione salvata'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
