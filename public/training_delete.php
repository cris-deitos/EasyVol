<?php
/**
 * Delete Training Course
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\TrainingController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('training', 'delete')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$trainingId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($trainingId <= 0) {
    header('Location: training.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new TrainingController($db, $config);

// Check if training course exists
$training = $controller->get($trainingId);
if (!$training) {
    header('Location: training.php?error=not_found');
    exit;
}

$userId = $app->getUserId();

// Delete training course
$result = $controller->delete($trainingId, $userId);

if ($result === true) {
    header('Location: training.php?success=deleted');
} elseif (is_array($result) && isset($result['error'])) {
    header('Location: training.php?error=' . urlencode($result['error']));
} else {
    header('Location: training.php?error=delete_failed');
}
exit;
