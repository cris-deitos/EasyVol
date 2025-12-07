<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\SchedulerController;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('scheduler', 'edit')) {

// Log page access
AutoLogger::logPageAccess();
    die('Accesso negato');
}

$controller = new SchedulerController($app->getDb(), $app->getConfig());

// Get item ID
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$itemId) {
    $_SESSION['error'] = 'ID scadenza non valido';
    header('Location: scheduler.php');
    exit;
}

$result = $controller->complete($itemId, $app->getUserId());

if ($result) {
    $_SESSION['success'] = 'Scadenza segnata come completata';
} else {
    $_SESSION['error'] = 'Errore durante il completamento';
}

header('Location: scheduler.php');
exit;
