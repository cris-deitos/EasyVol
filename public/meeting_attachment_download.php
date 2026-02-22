<?php
/**
 * Gestione Riunioni - Download Allegato
 *
 * Scarica un allegato PDF di una riunione/assemblea.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('meetings', 'view')) {
    die('Accesso negato');
}

$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attachmentId <= 0) {
    header('Location: meetings.php');
    exit;
}

$db = $app->getDb();

$attachment = $db->fetchOne("SELECT * FROM meeting_attachments WHERE id = ?", [$attachmentId]);

if (!$attachment) {
    die('Allegato non trovato');
}

$filePath = __DIR__ . '/../' . $attachment['file_path'];

if (!file_exists($filePath)) {
    die('File non trovato sul server');
}

AutoLogger::logAction('meetings', 'download_attachment', 'Scaricato allegato: ' . $attachment['file_name']);

$mimeType = $attachment['file_type'] ?? 'application/pdf';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $attachment['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
