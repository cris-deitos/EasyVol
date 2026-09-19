<?php
/**
 * Eventi - Upload Allegato
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
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

AutoLogger::logPageAccess();

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

if ($eventId <= 0) {
    $_SESSION['error'] = 'ID evento non valido';
    header('Location: events.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EventController($db, $config);

$event = $db->fetchOne("SELECT id FROM events WHERE id = ?", [$eventId]);
if (!$event) {
    $_SESSION['error'] = 'Evento non trovato';
    header('Location: events.php');
    exit;
}

$errors = [];

if (!isset($_FILES['attachment_file']) || $_FILES['attachment_file']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Nessun file selezionato';
} elseif ($_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Errore durante il caricamento del file (codice: ' . $_FILES['attachment_file']['error'] . ')';
} else {
    $file = $_FILES['attachment_file'];

    $maxSize = 20 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $errors[] = 'Il file supera la dimensione massima di 20MB';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        $errors[] = 'Impossibile determinare il tipo MIME del file';
        $mimeType = 'application/octet-stream';
    } else {
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }

    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedMimesByExtension = [
        'pdf' => ['application/pdf', 'application/octet-stream'],
        'p7m' => ['application/pkcs7-mime', 'application/x-pkcs7-mime', 'application/octet-stream', 'text/plain'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/octet-stream'],
        'rtf' => ['application/rtf', 'text/rtf', 'application/octet-stream'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'bmp' => ['image/bmp'],
        'tiff' => ['image/tiff'],
        'tif' => ['image/tiff'],
        'webp' => ['image/webp'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/octet-stream'],
        'txt' => ['text/plain', 'application/octet-stream']
    ];
    $allowedExtensions = array_keys($allowedMimesByExtension);

    if (!in_array($fileExtension, $allowedExtensions)) {
        $errors[] = 'Tipo di file non consentito. Formati ammessi: PDF, P7M, Word, immagini, Excel, testo.';
    } elseif (!in_array($mimeType, $allowedMimesByExtension[$fileExtension] ?? [], true)) {
        $errors[] = 'Tipo MIME non consentito per "' . $file['name'] . '" (rilevato: ' . $mimeType . ').';
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $documentType = trim($_POST['document_type'] ?? '');

    if (empty($errors)) {
        try {
            $uploadDir = __DIR__ . '/../uploads/events/' . $eventId;
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new \RuntimeException('Impossibile creare la cartella di upload');
                }
            }

            $filename = uniqid('att_', true) . '.' . $fileExtension;
            $filepath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $data = [
                    'file_name' => $file['name'],
                    'file_path' => 'uploads/events/' . $eventId . '/' . $filename,
                    'file_type' => $mimeType,
                    'file_size' => $file['size'],
                    'title' => !empty($title) ? $title : null,
                    'description' => !empty($description) ? $description : null,
                    'document_type' => !empty($documentType) ? $documentType : null
                ];

                $attachmentId = $controller->addAttachment($eventId, $data, $app->getUserId());

                if ($attachmentId) {
                    $_SESSION['success'] = 'Allegato caricato con successo';
                    header('Location: event_view.php?id=' . $eventId . '#attachments');
                    exit;
                }

                @unlink($filepath);
                $_SESSION['error'] = 'Errore durante il salvataggio nel database';
            } else {
                $errors[] = 'Errore durante il salvataggio del file';
            }
        } catch (\Throwable $e) {
            error_log("Errore caricamento allegato evento: " . $e->getMessage());
            $_SESSION['error'] = 'Errore durante il caricamento del documento';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
}

header('Location: event_view.php?id=' . $eventId . '#attachments');
exit;
