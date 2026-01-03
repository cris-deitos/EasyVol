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
    // Check if file is uploaded
    if (empty($_FILES['audio'])) {
        http_response_code(400);
        echo json_encode(['error' => 'File audio mancante']);
        exit;
    }
    
    // Get POST data
    $slot = $_POST['slot'] ?? null;
    $radioDmrId = $_POST['radio_dmr_id'] ?? null;
    $talkgroupId = $_POST['talkgroup_id'] ?? null;
    $durationSeconds = $_POST['duration_seconds'] ?? null;
    $recordedAt = $_POST['recorded_at'] ?? date('Y-m-d H:i:s');
    
    if (empty($slot) || empty($radioDmrId) || empty($talkgroupId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parametri mancanti: slot, radio_dmr_id, talkgroup_id richiesti']);
        exit;
    }
    
    // Validate file
    $file = $_FILES['audio'];
    $maxSize = $config['max_audio_file_size'] ?? 10485760; // 10MB default
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Errore caricamento file: ' . $file['error']]);
        exit;
    }
    
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File troppo grande. Massimo: ' . $maxSize . ' bytes']);
        exit;
    }
    
    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../../../' . ($config['audio_storage_path'] ?? 'uploads/dispatch/audio/');
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = sprintf(
        '%s_%s_%s_%s.%s',
        date('Y-m-d_H-i-s'),
        $slot,
        $radioDmrId,
        uniqid(),
        $extension
    );
    
    $filePath = $uploadDir . $filename;
    $relativeFilePath = ($config['audio_storage_path'] ?? 'uploads/dispatch/audio/') . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Impossibile salvare il file']);
        exit;
    }
    
    // Save to database
    $audioId = $controller->saveAudioRecording(
        $slot,
        $radioDmrId,
        $talkgroupId,
        $relativeFilePath,
        $durationSeconds,
        $recordedAt
    );
    
    // Log event
    $controller->logEvent('audio_recording', [
        'slot' => $slot,
        'radio_dmr_id' => $radioDmrId,
        'talkgroup_id' => $talkgroupId,
        'event_data' => [
            'duration_seconds' => $durationSeconds,
            'file_path' => $relativeFilePath
        ],
        'event_timestamp' => $recordedAt
    ]);
    
    echo json_encode([
        'success' => true,
        'audio_id' => $audioId,
        'file_path' => $relativeFilePath,
        'message' => 'Audio salvato'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore del server', 'message' => $e->getMessage()]);
}
