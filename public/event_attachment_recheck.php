<?php
/**
 * Eventi - Ricontrollo Firme Digitali Allegati
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

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$attachmentId = isset($_POST['id']) ? intval($_POST['id']) : 0;
$csrfToken = $_POST['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}

if (!CsrfProtection::validateToken($csrfToken)) {
    $_SESSION['error'] = 'Token di sicurezza non valido';
    if ($eventId > 0) {
        header('Location: event_view.php?id=' . $eventId . '#attachments');
    } else {
        header('Location: events.php');
    }
    exit;
}

if ($eventId <= 0) {
    $_SESSION['error'] = 'ID evento non valido';
    header('Location: events.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EventController($db, $config);

$event = $db->fetchOne("SELECT id FROM events WHERE id = ?", [$eventId]);
if (!$event) {
    $_SESSION['error'] = 'Evento non trovato';
    header('Location: events.php');
    exit;
}

if ($attachmentId > 0) {
    $attachment = $controller->getAttachment($attachmentId);
    if (!$attachment) {
        $_SESSION['error'] = 'Allegato non trovato';
        header('Location: event_view.php?id=' . $eventId . '#attachments');
        exit;
    }

    if ($eventId !== intval($attachment['event_id'])) {
        $eventId = intval($attachment['event_id']);
    }

    $result = $controller->recheckAttachmentSignatures($attachmentId, $app->getUserId());
    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }
} else {
    $result = $controller->recheckAllAttachmentSignatures($eventId, $app->getUserId());
    if ($result['success']) {
        $_SESSION['success'] = 'Controllati ' . $result['checked'] . ' documenti, '
            . $result['signatures_found'] . ' con firma digitale rilevata.';
    } else {
        $_SESSION['error'] = 'Controllati ' . $result['checked'] . ' documenti, '
            . $result['failed'] . ' non analizzabili.';
    }
}

header('Location: event_view.php?id=' . $eventId . '#attachments');
exit;
