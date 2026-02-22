<?php
/**
 * Gestione Riunioni - Eliminazione Allegato
 *
 * Elimina un allegato PDF da una riunione/assemblea.
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

$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$meetingId = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;

if ($attachmentId <= 0 || $meetingId <= 0) {
    header('Location: meetings.php');
    exit;
}

// Verify CSRF via GET token (passed in URL)
if (!CsrfProtection::validateToken($_GET['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

$result = $controller->deleteAttachment($attachmentId, $app->getUserId());

if ($result['success']) {
    // Remove the physical file
    if (!empty($result['file_path'])) {
        $fullPath = __DIR__ . '/../' . $result['file_path'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
    $_SESSION['success'] = 'Allegato eliminato con successo';
} else {
    $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'eliminazione';
}

header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
exit;
