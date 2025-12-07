<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\OperationsCenterController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'edit')) {

// Log page access
AutoLogger::logPageAccess();
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !$csrf->validateToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Token di sicurezza non valido';
        header('Location: radio_directory.php');
        exit;
    }
    
    $radioId = isset($_POST['radio_id']) ? (int)$_POST['radio_id'] : 0;
    $memberId = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$radioId || !$memberId) {
        $_SESSION['error'] = 'Dati non validi';
        header('Location: radio_directory.php');
        exit;
    }
    
    $result = $controller->assignRadio($radioId, $memberId, $app->getUserId(), $notes);
    
    if ($result['success']) {
        $_SESSION['success'] = 'Radio assegnata con successo';
        header('Location: radio_view.php?id=' . $radioId);
    } else {
        $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'assegnazione';
        header('Location: radio_view.php?id=' . $radioId);
    }
    exit;
}

// If GET request (should not happen normally)
header('Location: radio_directory.php');
exit;
