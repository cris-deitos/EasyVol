<?php
/**
 * Delete Member (Set status to 'dimesso')
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Models\Member;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('members', 'delete')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($memberId <= 0) {
    header('Location: members.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$memberModel = new Member($db);

// Check if member exists
$member = $memberModel->getById($memberId);
if (!$member) {
    header('Location: members.php?error=not_found');
    exit;
}

// Delete (set status to 'dimesso')
$result = $memberModel->delete($memberId);

if ($result) {
    header('Location: members.php?success=deleted');
} else {
    header('Location: members.php?error=delete_failed');
}
exit;
