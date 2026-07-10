<?php
/**
 * Gestione Riunioni - Ricontrollo Firme Digitali
 *
 * Ri-analizza un allegato (o tutti gli allegati di una riunione) per estrarre
 * informazioni sulla firma digitale PAdES/CAdES.
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

$meetingId = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;
$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$csrfToken = $_GET['csrf_token'] ?? '';

if (!CsrfProtection::validateToken($csrfToken)) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    if ($meetingId > 0) {
        header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
    } else {
        header('Location: meetings.php');
    }
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

if ($attachmentId > 0) {
    // Re-check a single attachment
    $result = $controller->recheckAttachmentSignatures($attachmentId, $app->getUserId());
    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }
} else {
    // Re-check all attachments for this meeting
    $result = $controller->recheckAllAttachmentSignatures($meetingId, $app->getUserId());
    if ($result['success']) {
        $_SESSION['success'] = 'Controllati ' . $result['checked'] . ' documenti, ' 
            . $result['signatures_found'] . ' con firma digitale rilevata.';
    } else {
        $_SESSION['error'] = 'Errore durante il controllo delle firme';
    }
}

header('Location: meeting_view.php?id=' . $meetingId . '#documenti');
exit;
