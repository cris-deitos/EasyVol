<?php
/**
 * Gestione Documenti - Eliminazione
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

if (!$app->checkPermission('documents', 'delete')) {
    die('Accesso negato');
}

$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($documentId <= 0) {
    header('Location: documents.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new DocumentController($db, $config);

$userId = $app->getUserId();
$result = $controller->delete($documentId, $userId);

if ($result) {
    header('Location: documents.php?success=deleted');
} else {
    header('Location: documents.php?error=delete_failed');
}
exit;
