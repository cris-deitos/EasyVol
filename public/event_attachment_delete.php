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
    $fileDeleted = true;
    if (!empty($attachment['file_path'])) {
        $filePath = __DIR__ . '/../' . $attachment['file_path'];
        $realPath = realpath($filePath);
        $uploadDir = realpath(__DIR__ . '/../uploads/events/');
        if ($realPath !== false && $uploadDir !== false
            && strpos($realPath, $uploadDir . DIRECTORY_SEPARATOR) === 0
            && is_file($realPath)) {
            $fileDeleted = @unlink($realPath);
        }
    }

    if ($fileDeleted) {
        $_SESSION['success'] = 'Allegato eliminato con successo';
    } else {
        $restoreSql = "INSERT INTO event_attachments
            (event_id, file_name, file_path, file_type, file_size, title, description, document_type, uploaded_by, uploaded_at,
             has_signature, signature_format, signature_count, signature_data, signature_validity, signature_checked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $db->execute($restoreSql, [
            $attachment['event_id'],
            $attachment['file_name'],
            $attachment['file_path'],
            $attachment['file_type'],
            $attachment['file_size'],
            $attachment['title'] ?? null,
            $attachment['description'] ?? null,
            $attachment['document_type'] ?? null,
            $attachment['uploaded_by'] ?? null,
            $attachment['uploaded_at'],
            $attachment['has_signature'] ?? 0,
            $attachment['signature_format'] ?? null,
            $attachment['signature_count'] ?? 0,
            $attachment['signature_data'] ?? null,
            $attachment['signature_validity'] ?? 'unknown',
            $attachment['signature_checked_at'] ?? null
        ]);
        $_SESSION['error'] = 'Impossibile eliminare il file allegato dal disco';
    }
} else {
    $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'eliminazione';
}

header('Location: event_view.php?id=' . $eventId . '#attachments');
exit;
