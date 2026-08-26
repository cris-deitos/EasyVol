<?php
/**
 * Convenzioni - Elimina Allegato
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\ConventionController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('conventions', 'edit')) {
    die('Accesso negato');
}

$attachmentId = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
$conventionId = isset($_POST['convention_id']) ? intval($_POST['convention_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: conventions.php');
    exit;
}

if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    header('Location: convention_view.php?id=' . $conventionId . '#allegati');
    exit;
}

if ($attachmentId <= 0 || $conventionId <= 0) {
    $_SESSION['error'] = 'Parametri non validi';
    header('Location: conventions.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ConventionController($db, $config);

$result = $controller->deleteAttachment($attachmentId, $app->getUserId());

if ($result['success']) {
    // Remove file from disk
    if (!empty($result['file_path'])) {
        $filePath = __DIR__ . '/../' . $result['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
    $_SESSION['success'] = 'Allegato eliminato con successo';
} else {
    $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'eliminazione';
}

header('Location: convention_view.php?id=' . $conventionId . '#allegati');
exit;
