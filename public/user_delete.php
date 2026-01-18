<?php
/**
 * Delete User
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\UserController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('users', 'delete')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId <= 0) {
    header('Location: users.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new UserController($db, $config);

// Check if user exists
$user = $controller->get($userId);
if (!$user) {
    header('Location: users.php?error=not_found');
    exit;
}

$currentUserId = $app->getUserId();

// Delete user
$result = $controller->delete($userId, $currentUserId);

if ($result === true) {
    header('Location: users.php?success=deleted');
} elseif (is_array($result) && isset($result['error'])) {
    header('Location: users.php?error=' . urlencode($result['error']));
} else {
    header('Location: users.php?error=delete_failed');
}
exit;