<?php
/**
 * Test Telegram Bot Connection
 * AJAX endpoint to test Telegram bot configuration
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Services\TelegramService;

header('Content-Type: application/json');

$app = App::getInstance();

// Verifica autenticazione e permessi
if (!$app->isLoggedIn() || !$app->checkPermission('settings', 'edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['token']) || empty($data['token'])) {
    echo json_encode(['success' => false, 'message' => 'Token non fornito']);
    exit;
}

try {
    $token = $data['token'];
    
    // Make direct API call to test the token
    $url = "https://api.telegram.org/bot{$token}/getMe";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo json_encode(['success' => false, 'message' => 'Errore di connessione: ' . $error]);
        exit;
    }
    
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'message' => 'HTTP Error ' . $httpCode]);
        exit;
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['ok']) && $result['ok'] === true) {
        echo json_encode([
            'success' => true,
            'bot_info' => $result['result']
        ]);
    } else {
        $errorMsg = isset($result['description']) ? $result['description'] : 'Errore sconosciuto';
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
