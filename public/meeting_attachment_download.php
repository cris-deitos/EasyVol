<?php
/**
 * Download Allegati Riunioni
 * 
 * Gestisce il download sicuro dei file allegati alle riunioni
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();

// Require authentication
if (!$app->isLoggedIn()) {
    http_response_code(403);
    die('Accesso negato - Autenticazione richiesta');
}

$db = $app->getDb();
$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attachmentId <= 0) {
    http_response_code(400);
    die('Parametri non validi');
}

// Get attachment info
$sql = "SELECT ma.*, m.id as meeting_id FROM meeting_attachments ma 
        JOIN meetings m ON ma.meeting_id = m.id 
        WHERE ma.id = ?";
$attachment = $db->fetchOne($sql, [$attachmentId]);

if (!$attachment) {
    http_response_code(404);
    die('Allegato non trovato');
}

// Check permission
if (!$app->checkPermission('meetings', 'view')) {
    http_response_code(403);
    die('Accesso negato');
}

$filePath = __DIR__ . '/../' . $attachment['file_path'];

// Security check - ensure file path is within uploads directory
$realPath = realpath($filePath);
$uploadDir = realpath(__DIR__ . '/../uploads/meetings/');

if ($realPath === false || strpos($realPath, $uploadDir) !== 0 || !file_exists($filePath)) {
    http_response_code(404);
    die('File non trovato o accesso negato');
}

// Log download
try {
    $user = $app->getCurrentUser();
    $logSql = "INSERT INTO activity_logs (user_id, module, action, record_id, description, ip_address, user_agent, created_at) 
               VALUES (?, 'meetings', 'attachment_download', ?, ?, ?, ?, NOW())";
    $db->execute($logSql, [
        $user['id'],
        $attachment['id'],
        'Download allegato: ' . $attachment['file_name'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
} catch (\Exception $e) {
    error_log('Errore log download allegato: ' . $e->getMessage());
}

// Serve file
$filename = $attachment['file_name'] ?? 'allegato.pdf';
$filesize = filesize($filePath);
$mimeType = $attachment['file_type'] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

readfile($filePath);
exit;
