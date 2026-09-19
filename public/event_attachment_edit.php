<?php
/**
 * Eventi - Modifica Allegato
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}

$attachmentId = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

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

$errors = [];
$data = [
    'title' => trim($_POST['title'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'document_type' => trim($_POST['document_type'] ?? '')
];
$uploadedAbsolutePath = null;

$replaceFile = isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] !== UPLOAD_ERR_NO_FILE;

if ($replaceFile) {
    if ($_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore durante il caricamento del file (codice: ' . $_FILES['attachment_file']['error'] . ')';
    } else {
        $file = $_FILES['attachment_file'];
        $maxSize = 20 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = 'Il file supera la dimensione massima di 20MB';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'p7m', 'doc', 'docx', 'odt', 'rtf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'xls', 'xlsx', 'csv', 'txt'];
        $allowedMimes = [
            'application/pdf',
            'application/pkcs7-mime', 'application/x-pkcs7-mime', 'application/octet-stream',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text', 'application/rtf', 'text/rtf',
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff', 'image/webp',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv', 'text/plain'
        ];

        if (!in_array($fileExtension, $allowedExtensions) || !in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Tipo di file non consentito. Formati ammessi: PDF, P7M, Word, immagini, Excel, testo.';
        }

        if (empty($errors)) {
            $uploadDir = __DIR__ . '/../uploads/events/' . $eventId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = uniqid('att_', true) . '.' . $fileExtension;
            $filepath = $uploadDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploadedAbsolutePath = $filepath;
                $data['file_name'] = $file['name'];
                $data['file_path'] = 'uploads/events/' . $eventId . '/' . $filename;
                $data['file_type'] = $mimeType;
                $data['file_size'] = $file['size'];
            } else {
                $errors[] = 'Errore durante il salvataggio del file';
            }
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
    header('Location: event_view.php?id=' . $eventId . '#attachments');
    exit;
}

$result = $controller->updateAttachment($attachmentId, $data, $app->getUserId());

if ($result['success']) {
    if (!empty($result['old_file_path'])) {
        $oldPath = __DIR__ . '/../' . $result['old_file_path'];
        $realOldPath = realpath($oldPath);
        $uploadDir = realpath(__DIR__ . '/../uploads/events/');
        if ($realOldPath !== false && $uploadDir !== false
            && strpos($realOldPath, $uploadDir . DIRECTORY_SEPARATOR) === 0
            && is_file($realOldPath)) {
            @unlink($realOldPath);
        }
    }
    $_SESSION['success'] = 'Allegato aggiornato con successo';
} else {
    if (!empty($uploadedAbsolutePath)) {
        $uploadDir = realpath(__DIR__ . '/../uploads/events/');
        if ($uploadDir !== false
            && strpos($uploadedAbsolutePath, $uploadDir . DIRECTORY_SEPARATOR) === 0
            && is_file($uploadedAbsolutePath)) {
            @unlink($uploadedAbsolutePath);
        }
    }
    $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'aggiornamento';
}

header('Location: event_view.php?id=' . $eventId . '#attachments');
exit;
