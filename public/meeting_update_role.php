<?php
/**
 * Update Meeting Participant Role
 * 
 * AJAX endpoint to update the role of a participant
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
$role = isset($_POST['role']) ? trim($_POST['role']) : '';

// Valida parametri
if ($participantId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID partecipante non valido']);
    exit;
}

// Validate role
$validRoles = ['', 'Presidente', 'Segretario', 'Uditore', 'Scrutatore', 'Presidente del Seggio Elettorale'];
if (!in_array($role, $validRoles)) {
    echo json_encode(['success' => false, 'message' => 'Ruolo non valido']);
    exit;
}

try {
    $db = $app->getDb();
    $config = $app->getConfig();
    $controller = new MeetingController($db, $config);
    
    // Update role
    $result = $controller->updateParticipantRole($participantId, $role ?: null);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Ruolo aggiornato con successo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
    }
} catch (Exception $e) {
    error_log("Errore aggiornamento ruolo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
}
