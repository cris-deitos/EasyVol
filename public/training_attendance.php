<?php
/**
 * Gestione Formazione - Registro Presenze
 * 
 * Gestione delle sessioni del corso e registro presenze per ogni sessione
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\TrainingController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('training', 'view')) {
    die('Accesso negato');
}

$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($courseId <= 0) {
    header('Location: training.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new TrainingController($db, $config);
$csrf = new CsrfProtection();

$course = $controller->get($courseId);

if (!$course) {
    header('Location: training.php?error=not_found');
    exit;
}

$errors = [];
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $app->checkPermission('training', 'edit')) {
    if (!$csrf->validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = $app->getUserId();
        
        switch ($action) {
            case 'create_session':
                $data = [
                    'session_date' => $_POST['session_date'] ?? '',
                    'start_time' => $_POST['start_time'] ?? '',
                    'end_time' => $_POST['end_time'] ?? '',
                    'description' => trim($_POST['description'] ?? '')
                ];
                
                if (empty($data['session_date']) || empty($data['start_time']) || empty($data['end_time'])) {
                    $errors[] = 'Data e orari sono obbligatori';
                } elseif ($data['start_time'] >= $data['end_time']) {
                    $errors[] = 'L\'orario di fine deve essere successivo all\'orario di inizio';
                } else {
                    $result = $controller->createSession($courseId, $data, $userId);
                    if ($result) {
                        $success = 'Sessione creata con successo';
                        $course = $controller->get($courseId); // Reload
                    } else {
                        $errors[] = 'Errore durante la creazione della sessione';
                    }
                }
                break;
                
            case 'update_session':
                $sessionIdToUpdate = intval($_POST['session_id'] ?? 0);
                $data = [
                    'session_date' => $_POST['session_date'] ?? '',
                    'start_time' => $_POST['start_time'] ?? '',
                    'end_time' => $_POST['end_time'] ?? '',
                    'description' => trim($_POST['description'] ?? '')
                ];
                
                if (empty($data['session_date']) || empty($data['start_time']) || empty($data['end_time'])) {
                    $errors[] = 'Data e orari sono obbligatori';
                } elseif ($data['start_time'] >= $data['end_time']) {
                    $errors[] = 'L\'orario di fine deve essere successivo all\'orario di inizio';
                } else {
                    $result = $controller->updateSession($sessionIdToUpdate, $data, $userId);
                    if ($result) {
                        $success = 'Sessione aggiornata con successo';
                        $course = $controller->get($courseId); // Reload
                    } else {
                        $errors[] = 'Errore durante l\'aggiornamento della sessione';
                    }
                }
                break;
                
            case 'delete_session':
                $sessionIdToDelete = intval($_POST['session_id'] ?? 0);
                $result = $controller->deleteSession($sessionIdToDelete, $userId);
                if ($result) {
                    $success = 'Sessione eliminata con successo';
                    $course = $controller->get($courseId); // Reload
                    if ($sessionId == $sessionIdToDelete) {
                        $sessionId = 0; // Reset if viewing deleted session
                    }
                } else {
                    $errors[] = 'Errore durante l\'eliminazione della sessione';
                }
                break;
                
            case 'save_attendance':
                $sessionIdForAttendance = intval($_POST['session_id'] ?? 0);
                $attendanceData = $_POST['attendance'] ?? [];
                
                foreach ($attendanceData as $memberId => $data) {
                    $present = isset($data['present']) ? 1 : 0;
                    $notes = trim($data['notes'] ?? '');
                    $controller->recordSessionAttendance($sessionIdForAttendance, $memberId, $present, $userId, $notes);
                }
                
                $success = 'Presenze salvate con successo';
                $course = $controller->get($courseId); // Reload to update stats
                break;
        }
    }
}

// Get participants with attendance for selected session
$participants = [];
$selectedSession = null;
if ($sessionId > 0) {
    $selectedSession = $controller->getSession($sessionId);
    if ($selectedSession && $selectedSession['course_id'] == $courseId) {
        $participants = $controller->getParticipantsWithSessionAttendance($courseId, $sessionId);
    } else {
        $sessionId = 0;
        $selectedSession = null;
    }
}

$pageTitle = 'Registro Presenze: ' . $course['course_name'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <a href="training_view.php?id=<?php echo $courseId; ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-info fs-6">
                            <i class="bi bi-clock"></i> 
                            Ore totali corso: <?php echo number_format($course['total_hours'], 1); ?>h
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Sessions List -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calendar-week"></i> Sessioni del Corso
                                </h5>
                                <?php if ($app->checkPermission('training', 'edit')): ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($course['sessions'])): ?>
                                    <div class="p-3 text-center text-muted">
                                        <i class="bi bi-calendar-x fs-1"></i>
                                        <p class="mb-0">Nessuna sessione programmata</p>
                                        <small>Aggiungi le date del corso per registrare le presenze</small>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($course['sessions'] as $session): ?>
                                            <?php 
                                            $isActive = $sessionId == $session['id'];
                                            $sessionDuration = round((strtotime($session['end_time']) - strtotime($session['start_time'])) / 3600, 1);
                                            ?>
                                            <a href="?course_id=<?php echo $courseId; ?>&session_id=<?php echo $session['id']; ?>" 
                                               class="list-group-item list-group-item-action <?php echo $isActive ? 'active' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo date('d/m/Y', strtotime($session['session_date'])); ?></strong>
                                                        <br>
                                                        <small>
                                                            <?php echo date('H:i', strtotime($session['start_time'])); ?> - 
                                                            <?php echo date('H:i', strtotime($session['end_time'])); ?>
                                                            (<?php echo $sessionDuration; ?>h)
                                                        </small>
                                                        <?php if ($session['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($session['description']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($app->checkPermission('training', 'edit')): ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-warning" 
                                                                    onclick="editSession(<?php echo htmlspecialchars(json_encode($session)); ?>); event.preventDefault();">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteSession(<?php echo $session['id']; ?>); event.preventDefault();">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Form -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-clipboard-check"></i> 
                                    <?php if ($selectedSession): ?>
                                        Presenze del <?php echo date('d/m/Y', strtotime($selectedSession['session_date'])); ?>
                                    <?php else: ?>
                                        Registro Presenze
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!$selectedSession): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-arrow-left-circle fs-1"></i>
                                        <p class="mt-3">Seleziona una sessione dalla lista per registrare le presenze</p>
                                    </div>
                                <?php elseif (empty($course['participants'])): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-people fs-1"></i>
                                        <p class="mt-3">Nessun partecipante iscritto al corso</p>
                                        <a href="training_view.php?id=<?php echo $courseId; ?>#participants" class="btn btn-primary">
                                            Aggiungi Partecipanti
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                                        <input type="hidden" name="action" value="save_attendance">
                                        <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                                        
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 50px;">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleAll(this)">
                                                            </div>
                                                        </th>
                                                        <th>Matricola</th>
                                                        <th>Nome</th>
                                                        <th>Note</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($participants as $participant): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="form-check">
                                                                    <input class="form-check-input attendance-check" type="checkbox" 
                                                                           name="attendance[<?php echo $participant['member_id']; ?>][present]" 
                                                                           value="1"
                                                                           <?php echo $participant['session_present'] ? 'checked' : ''; ?>>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($participant['registration_number']); ?></td>
                                                            <td>
                                                                <a href="member_view.php?id=<?php echo $participant['member_id']; ?>">
                                                                    <?php echo htmlspecialchars($participant['last_name'] . ' ' . $participant['first_name']); ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="attendance[<?php echo $participant['member_id']; ?>][notes]" 
                                                                       value="<?php echo htmlspecialchars($participant['session_notes'] ?? ''); ?>"
                                                                       placeholder="Note opzionali">
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <?php if ($app->checkPermission('training', 'edit')): ?>
                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save"></i> Salva Presenze
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Session Modal -->
    <div class="modal fade" id="addSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                    <input type="hidden" name="action" value="create_session">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Aggiungi Sessione</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="session_date" class="form-label">Data *</label>
                            <input type="date" class="form-control" id="session_date" name="session_date" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label for="start_time" class="form-label">Ora Inizio *</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-6">
                                <label for="end_time" class="form-label">Ora Fine *</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione</label>
                            <input type="text" class="form-control" id="description" name="description" 
                                   placeholder="Es: Lezione teorica, Esercitazione pratica...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Aggiungi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Session Modal -->
    <div class="modal fade" id="editSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                    <input type="hidden" name="action" value="update_session">
                    <input type="hidden" name="session_id" id="edit_session_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Modifica Sessione</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_session_date" class="form-label">Data *</label>
                            <input type="date" class="form-control" id="edit_session_date" name="session_date" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label for="edit_start_time" class="form-label">Ora Inizio *</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                            <div class="col-6">
                                <label for="edit_end_time" class="form-label">Ora Fine *</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Descrizione</label>
                            <input type="text" class="form-control" id="edit_description" name="description">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salva
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Session Form (hidden) -->
    <form id="deleteSessionForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
        <input type="hidden" name="action" value="delete_session">
        <input type="hidden" name="session_id" id="delete_session_id">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(checkbox) {
            document.querySelectorAll('.attendance-check').forEach(function(cb) {
                cb.checked = checkbox.checked;
            });
        }
        
        function editSession(session) {
            document.getElementById('edit_session_id').value = session.id;
            document.getElementById('edit_session_date').value = session.session_date;
            document.getElementById('edit_start_time').value = session.start_time;
            document.getElementById('edit_end_time').value = session.end_time;
            document.getElementById('edit_description').value = session.description || '';
            
            var modal = new bootstrap.Modal(document.getElementById('editSessionModal'));
            modal.show();
        }
        
        function deleteSession(sessionId) {
            if (confirm('Sei sicuro di voler eliminare questa sessione? Le presenze associate verranno eliminate.')) {
                document.getElementById('delete_session_id').value = sessionId;
                document.getElementById('deleteSessionForm').submit();
            }
        }
    </script>
</body>
</html>
