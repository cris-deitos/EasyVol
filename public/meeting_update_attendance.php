<?php
/**
 * Update Meeting Participant Attendance Status
 * 
 * AJAX endpoint to update the attendance status of a participant
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\MeetingController;

header('Content-Type: application/json');

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

// Verifica permessi
if (!$app->checkPermission('meetings', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}

// Verifica metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    exit;
}

// Ottieni parametri
$participantId = isset($_POST['participant_id']) ? intval($_POST['participant_id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Valida parametri
if ($participantId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID partecipante non valido']);
    exit;
}

if (!in_array($status, ['invited', 'present', 'absent', 'delegated'])) {
    echo json_encode(['success' => false, 'message' => 'Stato non valido']);
    exit;
}

try {
    $db = $app->getDb();
    $config = $app->getConfig();
    $controller = new MeetingController($db, $config);
    
    // Update attendance
    $result = $controller->updateAttendance($participantId, $status);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Stato aggiornato con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
    }
} catch (Exception $e) {
    error_log("Errore aggiornamento presenza: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
