<?php
/**
 * Eventi - Elimina Allegato
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\EventController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('events', 'edit')) {
    die('Accesso negato');
}

$attachmentId = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}

if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    header('Location: event_view.php?id=' . $eventId . '#attachments');
    exit;
}

if ($attachmentId <= 0 || $eventId <= 0) {
    $_SESSION['error'] = 'Parametri non validi';
    header('Location: events.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EventController($db, $config);

$attachment = $controller->getAttachment($attachmentId);
if (!$attachment || intval($attachment['event_id']) !== $eventId) {
    $_SESSION['error'] = 'Allegato non trovato';
    header('Location: event_view.php?id=' . $eventId . '#attachments');
    exit;
}

$result = $controller->deleteAttachment($attachmentId, $app->getUserId());

if ($result['success']) {
    if (!empty($result['file_path'])) {
        $filePath = __DIR__ . '/../' . $result['file_path'];
        $realPath = realpath($filePath);
        $uploadDir = realpath(__DIR__ . '/../uploads/events/');
        if ($realPath !== false && $uploadDir !== false
            && ($realPath === $uploadDir || strpos($realPath, $uploadDir . DIRECTORY_SEPARATOR) === 0)
            && file_exists($realPath)) {
            @unlink($realPath);
        }
    }
    $_SESSION['success'] = 'Allegato eliminato con successo';
} else {
    $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'eliminazione';
}

header('Location: event_view.php?id=' . $eventId . '#attachments');
exit;
