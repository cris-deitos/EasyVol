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
    // Check if this is a CO user request
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'operations') !== false) {
        header('Location: login_co.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

if (!$app->checkPermission('operations_center', 'edit')) {
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDb(), $app->getConfig());

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token di sicurezza non valido';
        header('Location: radio_directory.php');
        exit;
    }
    
    $radioId = isset($_POST['radio_id']) ? (int)$_POST['radio_id'] : 0;
    $assignmentType = $_POST['assignment_type'] ?? 'member';
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$radioId) {
        $_SESSION['error'] = 'Radio non valida';
        header('Location: radio_directory.php');
        exit;
    }
    
    try {
        if ($assignmentType === 'member') {
            // Assignment to association member
            $memberId = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
            
            if (!$memberId) {
                $_SESSION['error'] = 'Volontario non selezionato';
                header('Location: radio_view.php?id=' . $radioId);
                exit;
            }
            
            $result = $controller->assignRadio($radioId, $memberId, $app->getUserId(), $notes);
        } else {
            // Assignment to external personnel
            $externalData = [
                'last_name' => strtoupper(trim($_POST['external_last_name'] ?? '')),
                'first_name' => strtoupper(trim($_POST['external_first_name'] ?? '')),
                'organization' => trim($_POST['external_organization'] ?? ''),
                'phone' => trim($_POST['external_phone'] ?? '')
            ];
            
            // Validate external data
            if (empty($externalData['last_name']) || empty($externalData['first_name']) || 
                empty($externalData['organization']) || empty($externalData['phone'])) {
                $_SESSION['error'] = 'Tutti i campi per personale esterno sono obbligatori';
                header('Location: radio_view.php?id=' . $radioId);
                exit;
            }
            
            $result = $controller->assignRadioToExternal($radioId, $externalData, $app->getUserId(), $notes);
        }
        
        if ($result['success']) {
            $_SESSION['success'] = 'Radio assegnata con successo';
            header('Location: radio_view.php?id=' . $radioId);
        } else {
            $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'assegnazione';
            header('Location: radio_view.php?id=' . $radioId);
        }
    } catch (\Exception $e) {
        $_SESSION['error'] = 'Errore durante l\'assegnazione: ' . $e->getMessage();
        header('Location: radio_view.php?id=' . $radioId);
    }
    exit;
}

// If GET request (should not happen normally)
header('Location: radio_directory.php');
exit;
