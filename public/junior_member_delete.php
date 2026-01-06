<?php
/**
 * Delete Junior Member (Set status to 'dimesso')
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Models\JuniorMember;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('junior_members', 'delete')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($memberId <= 0) {
    header('Location: junior_members.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$memberModel = new JuniorMember($db);

// Check if junior member exists
$member = $memberModel->getById($memberId);
if (!$member) {
    header('Location: junior_members.php?error=not_found');
    exit;
}

// Delete (set status to 'dimesso')
$result = $memberModel->delete($memberId);

if ($result) {
    header('Location: junior_members.php?success=deleted');
} else {
    header('Location: junior_members.php?error=delete_failed');
}
exit;
