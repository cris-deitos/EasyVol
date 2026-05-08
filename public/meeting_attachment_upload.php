<?php
/**
 * Gestione Riunioni - Upload Allegato PDF
 *
 * Gestisce il caricamento di allegati PDF (verbale firmato o allegato numerato)
 * per una riunione/assemblea.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\MeetingController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('meetings', 'edit')) {
    die('Accesso negato');
}

AutoLogger::logPageAccess();

$meetingId = isset($_POST['meeting_id']) ? intval($_POST['meeting_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: meetings.php');
    exit;
}

// Verifica CSRF token
if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
    exit;
}

if ($meetingId <= 0) {
    $_SESSION['error'] = 'ID riunione non valido';
    header('Location: meetings.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

// Verify the meeting exists
$meeting = $db->fetchOne("SELECT id FROM meetings WHERE id = ?", [$meetingId]);
if (!$meeting) {
    $_SESSION['error'] = 'Riunione non trovata';
    header('Location: meetings.php');
    exit;
}

$attachmentType = $_POST['attachment_type'] ?? '';
if (!in_array($attachmentType, ['verbale', 'allegato'])) {
    $_SESSION['error'] = 'Tipo allegato non valido';
    header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
    exit;
}

$errors = [];

// Check if file was uploaded
if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Nessun file selezionato';
} elseif ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Errore durante il caricamento del file (codice: ' . $_FILES['pdf_file']['error'] . ')';
} else {
    $file = $_FILES['pdf_file'];

    // Max 20MB for PDF documents
    $maxSize = 20 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $errors[] = 'Il file supera la dimensione massima di 20MB';
    }

    // Only PDF is allowed
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf') {
        $errors[] = 'Solo file PDF sono ammessi';
    }

    // Validate allegato-specific fields
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $progressiveNumber = null;

    if ($attachmentType === 'allegato' && empty($title)) {
        $errors[] = 'Il titolo è obbligatorio per gli allegati';
    }

    // Handle progressive number for allegati
    if ($attachmentType === 'allegato') {
        $providedNumber = isset($_POST['progressive_number']) ? trim($_POST['progressive_number']) : '';
        
        if (!empty($providedNumber)) {
            // User provided a number
            if (!is_numeric($providedNumber) || intval($providedNumber) <= 0) {
                $errors[] = 'Il numero progressivo deve essere un numero positivo';
            } else {
                $progressiveNumber = intval($providedNumber);
                
                // Check if this number already exists for this meeting
                $existing = $db->fetchOne(
                    "SELECT id FROM meeting_attachments WHERE meeting_id = ? AND attachment_type = 'allegato' AND progressive_number = ?",
                    [$meetingId, $progressiveNumber]
                );
                
                if ($existing) {
                    $errors[] = 'Il numero progressivo ' . $progressiveNumber . ' è già in uso. Elimina l\'allegato esistente prima di ricaricare con lo stesso numero.';
                }
            }
        } else {
            // Auto-generate next number
            $progressiveNumber = $controller->getNextAttachmentNumber($meetingId);
        }
    }

    if (empty($errors)) {
        try {
            // Create upload directory
            $uploadDir = __DIR__ . '/../uploads/meetings/' . $meetingId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename preserving extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                $extension = 'pdf';
            }
            $filename = uniqid($attachmentType . '_', true) . '.' . $extension;
            $filepath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $data = [
                    'attachment_type' => $attachmentType,
                    'file_name' => $file['name'],
                    'file_path' => 'uploads/meetings/' . $meetingId . '/' . $filename,
                    'file_type' => $mimeType,
                    'title' => $attachmentType === 'allegato' ? $title : null,
                    'description' => !empty($description) ? $description : null,
                    'progressive_number' => $progressiveNumber
                ];

                $attachmentId = $controller->addAttachment($meetingId, $data, $app->getUserId());

                if ($attachmentId) {
                    $_SESSION['success'] = $attachmentType === 'verbale'
                        ? 'Verbale firmato caricato con successo'
                        : 'Allegato caricato con successo';
                    header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
                    exit;
                } else {
                    // Remove uploaded file if DB insert failed
                    @unlink($filepath);
                    $_SESSION['error'] = 'Errore durante il salvataggio nel database';
                }
            } else {
                $errors[] = 'Errore durante il salvataggio del file';
            }
        } catch (\Exception $e) {
            error_log("Errore caricamento allegato riunione: " . $e->getMessage());
            $_SESSION['error'] = 'Errore durante il caricamento del documento';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(', ', $errors);
}

header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
exit;
