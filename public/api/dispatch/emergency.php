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
    
    if (empty($data['radio_dmr_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'radio_dmr_id richiesto']);
        exit;
    }
    
    $emergencyId = $controller->saveEmergencyCode(
        $data['radio_dmr_id'],
        $data['latitude'] ?? null,
        $data['longitude'] ?? null,
        $data['emergency_timestamp'] ?? date('Y-m-d H:i:s')
    );
    
    // Log event
    $controller->logEvent('emergency_code', [
        'radio_dmr_id' => $data['radio_dmr_id'],
        'event_data' => [
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null
        ],
        'event_timestamp' => $data['emergency_timestamp'] ?? date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true,
        'emergency_id' => $emergencyId,
        'message' => 'Emergenza registrata'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
