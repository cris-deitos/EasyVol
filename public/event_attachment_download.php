<?php
/**
 * Eventi - Download Allegato
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\EventController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato - Autenticazione richiesta');
}

if (!$app->checkPermission('events', 'view')) {
    http_response_code(403);
    die('Accesso negato');
}

$db = $app->getDb();
$controller = new EventController($db, $app->getConfig());
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metodo non consentito');
}

if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Token di sicurezza non valido');
}

$attachmentId = isset($_POST['id']) ? intval($_POST['id']) : 0;
$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

if ($attachmentId <= 0 || $eventId <= 0) {
    http_response_code(400);
    die('Parametri non validi');
}

$sql = "SELECT ea.* FROM event_attachments ea
        JOIN events e ON ea.event_id = e.id
        WHERE ea.id = ? AND ea.event_id = ?";
$attachment = $db->fetchOne($sql, [$attachmentId, $eventId]);

if (!$attachment) {
    http_response_code(404);
    die('Allegato non trovato');
}

$event = $controller->get($eventId);
if (!$event) {
    http_response_code(404);
    die('Evento non trovato o accesso negato');
}

$filePath = __DIR__ . '/../' . $attachment['file_path'];
$realPath = realpath($filePath);
$uploadDir = realpath(__DIR__ . '/../uploads/events/');

if ($realPath === false || $uploadDir === false
    || ($realPath !== $uploadDir && strpos($realPath, $uploadDir . DIRECTORY_SEPARATOR) !== 0)
    || !file_exists($realPath)) {
    http_response_code(404);
    die('File non trovato o accesso negato');
}

try {
    $user = $app->getCurrentUser();
    $logSql = "INSERT INTO activity_logs (user_id, module, action, record_id, description, ip_address, user_agent, created_at)
               VALUES (?, 'events', 'attachment_download', ?, ?, ?, ?, NOW())";
    $db->execute($logSql, [
        $user['id'],
        $attachment['id'],
        'Download allegato evento: ' . $attachment['file_name'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
} catch (\Exception $e) {
    error_log('Errore log download allegato evento: ' . $e->getMessage());
}

$filename = $attachment['file_name'] ?? 'allegato';
$fallbackFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
$fallbackFilename = trim((string)$fallbackFilename, '._');
if ($fallbackFilename === '') {
    $fallbackFilename = 'allegato';
}
$utf8Filename = rawurlencode($filename);
$filesize = filesize($realPath);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = ($finfo !== false) ? (finfo_file($finfo, $realPath) ?: 'application/octet-stream') : 'application/octet-stream';
if ($finfo !== false) {
    finfo_close($finfo);
}

header('Content-Type: ' . $mimeType);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $fallbackFilename . '"; filename*=UTF-8\'\'' . $utf8Filename);
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

readfile($realPath);
exit;
