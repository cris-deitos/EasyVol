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

    if (!in_array($fileExtension, $allowedExtensions)) {
        $errors[] = 'Tipo di file non consentito. Formati ammessi: PDF, P7M, Word, immagini, Excel, testo.';
    } elseif ($fileExtension !== 'p7m' && !in_array($mimeType, $allowedMimes)) {
        $errors[] = 'Tipo MIME non consentito per il file selezionato.';
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $documentType = trim($_POST['document_type'] ?? '');

    if (empty($errors)) {
        try {
            $uploadDir = __DIR__ . '/../uploads/events/' . $eventId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
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
