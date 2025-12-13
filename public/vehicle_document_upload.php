<?php
/**
 * Vehicle Document Upload Handler
 * 
 * Handles document uploads for vehicles
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
    
    // Validate and sanitize vehicle ID - must be a positive integer
    if ($vehicleId <= 0 || !is_numeric($_POST['vehicle_id'] ?? '')) {
        $_SESSION['error'] = 'ID veicolo non valido';
        header('Location: vehicles.php');
        exit;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Nessun file selezionato';
    } elseif ($_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore durante il caricamento del file';
    } else {
        $file = $_FILES['document_file'];
        
        // Validate file
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            $errors[] = 'Il file supera la dimensione massima di 10MB';
        }
        
        // Allowed file types
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                       'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                       'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Tipo di file non supportato. Sono ammessi solo immagini, PDF e documenti Office.';
        }
        
        // Validate required fields
        if (empty($_POST['document_type'])) {
            $errors[] = 'Tipo documento obbligatorio';
        }
        
        if (empty($errors)) {
            try {
                // Create upload directory if it doesn't exist (vehicleId already validated and sanitized above)
                $uploadDir = sprintf('%s/../uploads/vehicles/%d', __DIR__, $vehicleId);
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . time() . '.' . $extension;
                $filepath = $uploadDir . '/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $db = $app->getDb();
                    $config = $app->getConfig();
                    $controller = new VehicleController($db, $config);
                    
                    $expiryDate = trim($_POST['expiry_date'] ?? '');
                    
                    $data = [
                        'document_type' => $_POST['document_type'],
                        'file_name' => $file['name'],
                        'file_path' => sprintf('../uploads/vehicles/%d/%s', $vehicleId, $filename),
                        'expiry_date' => $expiryDate !== '' ? $expiryDate : null
                    ];
                    
                    $documentId = $controller->addDocument($vehicleId, $data, $app->getUserId());
                    
                    if ($documentId) {
                        $_SESSION['success'] = 'Documento caricato con successo';
                        header('Location: vehicle_view.php?id=' . $vehicleId . '&tab=documents');
                    } else {
                        $_SESSION['error'] = 'Errore durante il salvataggio del documento';
                        header('Location: vehicle_view.php?id=' . $vehicleId);
                    }
                    exit;
                    
                } else {
                    $errors[] = 'Errore durante il salvataggio del file';
                }
            } catch (\Exception $e) {
                error_log("Errore caricamento documento: " . $e->getMessage());
                $_SESSION['error'] = 'Errore durante il caricamento: ' . $e->getMessage();
                header('Location: vehicle_view.php?id=' . $vehicleId);
                exit;
            }
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode(', ', $errors);
        header('Location: vehicle_view.php?id=' . $vehicleId);
        exit;
    }
} else {
    // Non è un POST
    header('Location: vehicles.php');
    exit;
}
