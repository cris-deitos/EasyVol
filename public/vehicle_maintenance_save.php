<?php
/**
 * Gestione Manutenzioni Mezzi - Save Handler
 * 
 * Handler per salvare le manutenzioni dei mezzi
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\VehicleController;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('vehicles', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$errors = [];
$vehicleId = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token di sicurezza non valido';
        header('Location: vehicle_view.php?id=' . $vehicleId);
        exit;
    }
    
    // Valida campi obbligatori
    if (empty($_POST['maintenance_type'])) {
        $errors[] = 'Tipo manutenzione obbligatorio';
    }
    if (empty($_POST['date'])) {
        $errors[] = 'Data obbligatoria';
    }
    
    if (empty($errors) && $vehicleId > 0) {
        try {
            $db = $app->getDb();
            $config = $app->getConfig();
            $controller = new VehicleController($db, $config);
            
            $data = [
                'maintenance_type' => $_POST['maintenance_type'],
                'date' => $_POST['date'],
                'description' => trim($_POST['description']),
                'cost' => !empty($_POST['cost']) ? floatval($_POST['cost']) : null,
                'performed_by' => trim($_POST['performed_by'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
                'status' => $_POST['vehicle_status'] ?? null
            ];
            
            $maintenanceId = $controller->addMaintenance($vehicleId, $data, $app->getUserId());
            
            if ($maintenanceId) {
                $_SESSION['success'] = 'Manutenzione registrata con successo';
                
                // Messaggio specifico per revisione
                if ($data['maintenance_type'] === 'revisione') {
                    $_SESSION['success'] .= '. La scadenza revisione è stata calcolata automaticamente.';
                }
                
                header('Location: vehicle_view.php?id=' . $vehicleId . '&tab=maintenance');
            } else {
                $_SESSION['error'] = 'Errore durante il salvataggio della manutenzione';
                header('Location: vehicle_view.php?id=' . $vehicleId);
            }
            exit;
            
        } catch (\Exception $e) {
            error_log("Errore salvataggio manutenzione: " . $e->getMessage());
            $_SESSION['error'] = 'Errore durante il salvataggio: ' . $e->getMessage();
            header('Location: vehicle_view.php?id=' . $vehicleId);
            exit;
        }
    } else {
        $_SESSION['error'] = implode(', ', $errors);
        header('Location: vehicle_view.php?id=' . $vehicleId);
        exit;
    }
} else {
    // Non è un POST
    header('Location: vehicles.php');
    exit;
}
