<?php
/**
 * Convenzioni - Upload Allegato
 *
 * Gestisce il caricamento di allegati (PDF, Word, immagini, P7M) per una convenzione.
 * Rileva automaticamente le firme digitali PAdES e CAdES.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
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

AutoLogger::logPageAccess();

$conventionId = isset($_POST['convention_id']) ? intval($_POST['convention_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: conventions.php');
    exit;
}

// Verifica CSRF token
if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    header('Location: convention_view.php?id=' . $conventionId . '#allegati');
    exit;
}

if ($conventionId <= 0) {
    $_SESSION['error'] = 'ID convenzione non valido';
    header('Location: conventions.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ConventionController($db, $config);

// Verify the convention exists
$convention = $db->fetchOne("SELECT id FROM conventions WHERE id = ?", [$conventionId]);
if (!$convention) {
    $_SESSION['error'] = 'Convenzione non trovata';
    header('Location: conventions.php');
    exit;
}

$errors = [];

// Check if file was uploaded
if (!isset($_FILES['attachment_file']) || $_FILES['attachment_file']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Nessun file selezionato';
} elseif ($_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Errore durante il caricamento del file (codice: ' . $_FILES['attachment_file']['error'] . ')';
} else {
    $file = $_FILES['attachment_file'];

    // Max 20MB
    $maxSize = 20 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $errors[] = 'Il file supera la dimensione massima di 20MB';
    }

    // Validate MIME type
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

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($errors)) {
        try {
            // Create upload directory
            $uploadDir = __DIR__ . '/../uploads/conventions/' . $conventionId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $filename = uniqid('att_', true) . '.' . $fileExtension;
            $filepath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $data = [
                    'file_name' => $file['name'],
                    'file_path' => 'uploads/conventions/' . $conventionId . '/' . $filename,
                    'file_type' => $mimeType,
                    'file_size' => $file['size'],
                    'title' => !empty($title) ? $title : null,
                    'description' => !empty($description) ? $description : null
                ];

                $attachmentId = $controller->addAttachment($conventionId, $data, $app->getUserId());

                if ($attachmentId) {
                    $_SESSION['success'] = 'Allegato caricato con successo';
                    header('Location: convention_view.php?id=' . $conventionId . '#allegati');
                    exit;
                } else {
                    @unlink($filepath);
                    $_SESSION['error'] = 'Errore durante il salvataggio nel database';
                }
            } else {
                $errors[] = 'Errore durante il salvataggio del file';
            }
        } catch (\Throwable $e) {
            error_log("Errore caricamento allegato convenzione: " . $e->getMessage());
            $_SESSION['error'] = 'Errore durante il caricamento del documento';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
}

header('Location: convention_view.php?id=' . $conventionId . '#allegati');
exit;
