<?php
require_once '../src/Autoloader.php';
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\OperationsCenterController;

$app = new App();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->hasPermission('operations_center', 'edit')) {
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDatabase(), $app->getConfig());

// Get assignment ID
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$notes = $_GET['notes'] ?? '';

if (!$assignmentId) {
    $_SESSION['error'] = 'ID assegnazione non valido';
    header('Location: radio_directory.php');
    exit;
}

$result = $controller->returnRadio($assignmentId, $app->getUserId(), $notes);

if ($result['success']) {
    $_SESSION['success'] = 'Radio restituita con successo';
} else {
    $_SESSION['error'] = $result['message'] ?? 'Errore durante la restituzione';
}

// Redirect back (try to get the referer or default to directory)
$referer = $_SERVER['HTTP_REFERER'] ?? 'radio_directory.php';
header('Location: ' . $referer);
exit;
