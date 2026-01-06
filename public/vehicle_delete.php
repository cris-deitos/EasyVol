<?php
/**
 * Delete Vehicle (Set status to 'dismesso')
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\VehicleController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('vehicles', 'delete')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($vehicleId <= 0) {
    header('Location: vehicles.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new VehicleController($db, $config);

// Check if vehicle exists
$vehicle = $controller->get($vehicleId);
if (!$vehicle) {
    header('Location: vehicles.php?error=not_found');
    exit;
}

$userId = $app->getUserId();

// Delete (set status to 'dismesso')
$result = $controller->delete($vehicleId, $userId);

if ($result) {
    header('Location: vehicles.php?success=deleted');
} else {
    header('Location: vehicles.php?error=delete_failed');
}
exit;
