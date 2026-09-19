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
            $errors[] = 'Tipo MIME non consentito per il file selezionato.';
        }

        if (empty($errors)) {
            $uploadDir = __DIR__ . '/../uploads/events/' . $eventId;
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    $errors[] = 'Impossibile creare la cartella di upload';
                }
            }

            if (empty($errors)) {
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
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
    header('Location: event_view.php?id=' . $eventId . '#attachments');
    exit;
}

$result = $controller->updateAttachment($attachmentId, $data, $app->getUserId());

if ($result['success']) {
    if (!empty($result['old_file_path']) && $result['old_file_path'] !== ($result['new_file_path'] ?? null)) {
        $oldPath = __DIR__ . '/../' . $result['old_file_path'];
        $realOldPath = realpath($oldPath);
        $uploadDir = realpath(__DIR__ . '/../uploads/events/');
        if ($realOldPath !== false && $uploadDir !== false
            && strpos($realOldPath, $uploadDir . DIRECTORY_SEPARATOR) === 0
            && is_file($realOldPath)
            && !@unlink($realOldPath)) {
            $rollbackSql = "UPDATE event_attachments SET
                            file_name = ?, file_path = ?, file_type = ?, file_size = ?,
                            title = ?, description = ?, document_type = ?,
                            has_signature = ?, signature_format = ?, signature_count = ?,
                            signature_data = ?, signature_validity = ?, signature_checked_at = ?
                            WHERE id = ?";
            $db->execute($rollbackSql, [
                $attachment['file_name'],
                $attachment['file_path'],
                $attachment['file_type'],
                $attachment['file_size'],
                $attachment['title'] ?? null,
                $attachment['description'] ?? null,
                $attachment['document_type'] ?? null,
                $attachment['has_signature'] ?? 0,
                $attachment['signature_format'] ?? null,
                $attachment['signature_count'] ?? 0,
                $attachment['signature_data'] ?? null,
                $attachment['signature_validity'] ?? 'unknown',
                $attachment['signature_checked_at'] ?? null,
                $attachmentId
            ]);
            if (!empty($uploadedAbsolutePath) && is_file($uploadedAbsolutePath)) {
                @unlink($uploadedAbsolutePath);
            }
            $_SESSION['error'] = 'Impossibile finalizzare la sostituzione file. Operazione annullata.';
            header('Location: event_view.php?id=' . $eventId . '#attachments');
            exit;
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
