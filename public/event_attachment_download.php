<?php
/**
 * Eventi - Download Allegato
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

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
$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attachmentId <= 0) {
    http_response_code(400);
    die('Parametri non validi');
}

$sql = "SELECT ea.* FROM event_attachments ea
        JOIN events e ON ea.event_id = e.id
        WHERE ea.id = ?";
$attachment = $db->fetchOne($sql, [$attachmentId]);

if (!$attachment) {
    http_response_code(404);
    die('Allegato non trovato');
}

$filePath = __DIR__ . '/../' . $attachment['file_path'];
$realPath = realpath($filePath);
$uploadDir = realpath(__DIR__ . '/../uploads/events/');

if ($realPath === false || $uploadDir === false || strpos($realPath, $uploadDir) !== 0 || !file_exists($filePath)) {
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
$filesize = filesize($filePath);
$mimeType = $attachment['file_type'] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

readfile($filePath);
exit;
