<?php
/**
 * Gestione Documenti - Download
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\DocumentController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('documents', 'view')) {
    die('Accesso negato');
}

$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($documentId <= 0) {
    header('Location: documents.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new DocumentController($db, $config);

$userId = $app->getUserId();
$downloadInfo = $controller->download($documentId, $userId);

if (!$downloadInfo) {
    die('File non trovato');
}

// Invia il file al browser
header('Content-Type: ' . ($downloadInfo['mime_type'] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $downloadInfo['file_name'] . '"');
header('Content-Length: ' . filesize($downloadInfo['file_path']));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

readfile($downloadInfo['file_path']);
exit;
