<?php
/**
 * Elimina Allegati Riunioni
 * 
 * Gestisce l'eliminazione sicura dei file allegati alle riunioni
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Require authentication
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permission
if (!$app->checkPermission('meetings', 'edit')) {
    $_SESSION['error'] = 'Accesso negato';
    header('Location: meetings.php');
    exit;
}

$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$meetingId = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;

if ($attachmentId <= 0 || $meetingId <= 0) {
    $_SESSION['error'] = 'Parametri non validi';
    header('Location: meetings.php');
    exit;
}

// Verify CSRF token
if (!CsrfProtection::validateToken($_GET['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
    exit;
}

$db = $app->getDb();
$user = $app->getCurrentUser();

// Get attachment info
$sql = "SELECT * FROM meeting_attachments WHERE id = ? AND meeting_id = ?";
$attachment = $db->fetchOne($sql, [$attachmentId, $meetingId]);

if (!$attachment) {
    $_SESSION['error'] = 'Allegato non trovato';
    header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
    exit;
}

try {
    $filePath = __DIR__ . '/../' . $attachment['file_path'];
    
    // Security check
    $realPath = realpath($filePath);
    $uploadDir = realpath(__DIR__ . '/../uploads/meetings/');
    
    if ($realPath === false || strpos($realPath, $uploadDir) !== 0) {
        throw new Exception('Percorso file non valido');
    }
    
    // Delete file from disk
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception('Impossibile eliminare il file dal disco');
        }
    }
    
    // Delete record from database
    $sql = "DELETE FROM meeting_attachments WHERE id = ?";
    $db->execute($sql, [$attachmentId]);
    
    // Log deletion
    $logSql = "INSERT INTO activity_logs (user_id, module, action, record_id, description, ip_address, user_agent, created_at) 
               VALUES (?, 'meetings', 'attachment_deleted', ?, ?, ?, ?, NOW())";
    $db->execute($logSql, [
        $user['id'],
        $attachmentId,
        'Eliminato allegato: ' . $attachment['file_name'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    $_SESSION['success'] = 'Allegato eliminato con successo';
    
} catch (\Exception $e) {
    error_log('Errore eliminazione allegato: ' . $e->getMessage());
    $_SESSION['error'] = 'Errore durante l\'eliminazione dell\'allegato: ' . $e->getMessage();
}

header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
exit;
