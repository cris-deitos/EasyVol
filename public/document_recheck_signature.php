<?php
/**
 * Ri-verifica firme digitali di un documento
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
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

$result = $controller->recheckSignatures($documentId, $app->getUserId());

if ($result['success']) {
    header('Location: document_view.php?id=' . $documentId . '&signature_checked=1');
} else {
    header('Location: document_view.php?id=' . $documentId . '&error=' . urlencode($result['message'] ?? 'Errore'));
}
exit;
