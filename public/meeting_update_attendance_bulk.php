<?php
/**
 * Bulk Update Meeting Participant Attendance Status
 * 
 * AJAX endpoint to update the attendance status of multiple participants at once
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
$participantIdsJson = isset($_POST['participant_ids']) ? trim($_POST['participant_ids']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Valida parametri
if (empty($participantIdsJson)) {
    echo json_encode(['success' => false, 'message' => 'Nessun partecipante selezionato']);
    exit;
}

// Decode participant IDs
$participantIds = json_decode($participantIdsJson, true);
if (!is_array($participantIds) || empty($participantIds)) {
    echo json_encode(['success' => false, 'message' => 'ID partecipanti non validi']);
    exit;
}

// Validate status
if (!in_array($status, ['invited', 'present', 'absent', 'delegated'])) {
    echo json_encode(['success' => false, 'message' => 'Stato non valido']);
    exit;
}

try {
    $db = $app->getDb();
    $config = $app->getConfig();
    $controller = new MeetingController($db, $config);
    
    $updated = 0;
    $failed = 0;
    
    // Update each participant
    foreach ($participantIds as $participantId) {
        $participantId = intval($participantId);
        if ($participantId <= 0) {
            $failed++;
            continue;
        }
        
        $result = $controller->updateAttendance($participantId, $status);
        if ($result) {
            $updated++;
        } else {
            $failed++;
        }
    }
    
    if ($updated > 0) {
        $message = "$updated partecipante(i) aggiornato(i) con successo";
        if ($failed > 0) {
            $message .= ", $failed non aggiornato(i)";
        }
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'updated' => $updated,
            'failed' => $failed
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nessun partecipante aggiornato']);
    }
} catch (Exception $e) {
    error_log("Errore aggiornamento presenza bulk: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
