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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non permesso']);
    exit;
}

try {
    $raspberryConfig = $controller->getRaspberryConfig();
    
    // Return configuration without sensitive data
    $publicConfig = [
        'api_enabled' => $raspberryConfig['api_enabled'] ?? '0',
        'audio_storage_path' => $raspberryConfig['audio_storage_path'] ?? 'uploads/dispatch/audio/',
        'max_audio_file_size' => (int)($raspberryConfig['max_audio_file_size'] ?? 10485760),
        'position_update_interval' => (int)($raspberryConfig['position_update_interval'] ?? 60),
        'position_inactive_threshold' => (int)($raspberryConfig['position_inactive_threshold'] ?? 1800)
    ];
    
    echo json_encode([
        'success' => true,
        'config' => $publicConfig
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
