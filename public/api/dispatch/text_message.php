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
    
    if (empty($data['slot']) || empty($data['from_radio_dmr_id']) || empty($data['message_text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Parametri mancanti: slot, from_radio_dmr_id, message_text richiesti']);
        exit;
    }
    
    $messageId = $controller->saveTextMessage(
        $data['slot'],
        $data['from_radio_dmr_id'],
        $data['to_radio_dmr_id'] ?? null,
        $data['to_talkgroup_id'] ?? null,
        $data['message_text'],
        $data['message_timestamp'] ?? date('Y-m-d H:i:s')
    );
    
    // Log event
    $controller->logEvent('text_message', [
        'slot' => $data['slot'],
        'radio_dmr_id' => $data['from_radio_dmr_id'],
        'talkgroup_id' => $data['to_talkgroup_id'] ?? null,
        'event_data' => [
            'to_radio_dmr_id' => $data['to_radio_dmr_id'] ?? null,
            'message_preview' => substr($data['message_text'], 0, 50)
        ],
        'event_timestamp' => $data['message_timestamp'] ?? date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'message' => 'Messaggio salvato'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
