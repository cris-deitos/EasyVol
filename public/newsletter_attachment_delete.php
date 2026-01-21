<?php
/**
 * Newsletter Attachment Delete API
 * 
 * API endpoint to delete a newsletter attachment
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
if (!$app->checkPermission('newsletters', 'edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$attachmentId = isset($input['id']) ? intval($input['id']) : 0;

if (!$attachmentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID allegato non valido']);
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new NewsletterController($db, $config);

// Delete attachment
$result = $controller->deleteAttachment($attachmentId);

// Log the action
if ($result['success']) {
    AutoLogger::logAction('newsletters', 'delete_attachment', "Eliminato allegato ID: $attachmentId");
}

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result);
