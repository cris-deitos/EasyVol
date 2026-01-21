<?php
/**
 * Newsletter Delete API
 * 
 * API endpoint to delete a newsletter (drafts only)
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\NewsletterController;
use EasyVol\Utils\AutoLogger;

header('Content-Type: application/json');

$app = App::getInstance();

// Check authentication
if (!$app->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

// Check permissions
if (!$app->checkPermission('newsletters', 'delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$newsletterId = isset($input['id']) ? intval($input['id']) : 0;

if (!$newsletterId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID newsletter non valido']);
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new NewsletterController($db, $config);

// Delete newsletter
$result = $controller->delete($newsletterId);

// Log the action
if ($result['success']) {
    AutoLogger::logAction('newsletters', 'delete', "Eliminata newsletter ID: $newsletterId");
}

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result);
