<?php
/**
 * Gestione Partecipanti Riunione
 * 
 * Pagina per aggiungere partecipanti e inviare convocazioni
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\MeetingController;
use EasyVol\Controllers\MemberController;
use EasyVol\Controllers\JuniorMemberController;
use EasyVol\Middleware\CsrfProtection;

$app = new App();

// Verifica autenticazione
if (!$app->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!$app->hasPermission('meetings', 'edit')) {
    die('Accesso negato');
}

$meetingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$meetingId) {
    header('Location: meetings.php');
    exit;
}

$db = $app->getDatabase();
$config = $app->getConfig();
$meetingController = new MeetingController($db, $config);
$memberController = new MemberController($db, $config);
$juniorMemberController = new JuniorMemberController($db, $config);

$meeting = $meetingController->get($meetingId);
if (!$meeting) {
    header('Location: meetings.php?error=not_found');
    exit;
}

$errors = [];
$success = false;
$message = '';

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        switch ($action) {
            case 'add_all_members':
                $includeAdults = isset($_POST['include_adults']);
                $includeJuniors = isset($_POST['include_juniors']);
                
                if ($meetingController->addParticipantsFromMembers($meetingId, $includeAdults, $includeJuniors)) {
                    $success = true;
                    $message = 'Partecipanti aggiunti con successo';
                    // Reload meeting data
                    $meeting = $meetingController->get($meetingId);
                } else {
                    $errors[] = 'Errore durante l\'aggiunta dei partecipanti';
                }
                break;
                
            case 'add_single':
                $memberId = intval($_POST['member_id'] ?? 0);
                $memberType = $_POST['member_type'] ?? 'adult';
                $role = trim($_POST['role'] ?? '');
                
                if ($memberId && $meetingController->addParticipant($meetingId, $memberId, $memberType, $role)) {
                    $success = true;
                    $message = 'Partecipante aggiunto con successo';
                    // Reload meeting data
                    $meeting = $meetingController->get($meetingId);
                } else {
                    $errors[] = 'Errore durante l\'aggiunta del partecipante';
                }
                break;
                
            case 'update_attendance':
                $participantId = intval($_POST['participant_id'] ?? 0);
                $status = $_POST['status'] ?? 'invited';
                $delegatedTo = !empty($_POST['delegated_to']) ? intval($_POST['delegated_to']) : null;
                
                if ($participantId && $meetingController->updateAttendance($participantId, $status, $delegatedTo)) {
                    $success = true;
                    $message = 'Presenza aggiornata con successo';
                    // Reload meeting data
                    $meeting = $meetingController->get($meetingId);
                } else {
                    $errors[] = 'Errore durante l\'aggiornamento della presenza';
                }
                break;
                
            case 'send_invitations':
                $result = $meetingController->sendInvitations($meetingId, $app->getUserId());
                if ($result['success']) {
                    $success = true;
                    $message = "Convocazioni inviate: {$result['sent']} riuscite";
                    if ($result['failed'] > 0) {
                        $message .= ", {$result['failed']} fallite";
                    }
                    // Reload meeting data
                    $meeting = $meetingController->get($meetingId);
                } else {
                    $errors[] = $result['message'] ?? 'Errore durante l\'invio delle convocazioni';
                }
                break;
        }
    }
}

// Get active members for dropdown
$activeMembers = $memberController->index(['member_status' => 'attivo'], 1, 500);
$activeJuniors = $juniorMemberController->index(['member_status' => 'attivo'], 1, 500);

$pageTitle = 'Gestione Partecipanti - ' . htmlspecialchars($meeting['title']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <?php include '../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-people"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="meeting_view.php?id=<?php echo $meetingId; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna alla riunione
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Errore:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success && $message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Aggiungi Partecipanti -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person-plus"></i> Aggiungi Partecipanti</h5>
                            </div>
                            <div class="card-body">
                                <!-- Aggiungi tutti i soci -->
                                <form method="POST" class="mb-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    <input type="hidden" name="action" value="add_all_members">
                                    
                                    <h6>Aggiungi tutti i soci attivi</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_adults" id="include_adults" checked>
                                        <label class="form-check-label" for="include_adults">
                                            Soci Maggiorenni
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="include_juniors" id="include_juniors" checked>
                                        <label class="form-check-label" for="include_juniors">
                                            Soci Minorenni (Cadetti)
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-people-fill"></i> Aggiungi Tutti
                                    </button>
                                </form>
                                
                                <hr>
                                
                                <!-- Aggiungi singolo socio -->
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    <input type="hidden" name="action" value="add_single">
                                    
                                    <h6>Aggiungi singolo socio</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Tipo Socio</label>
                                        <select class="form-select" name="member_type" id="member_type_select" required>
                                            <option value="adult">Maggiorenne</option>
                                            <option value="junior">Minorenne</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="adult_select">
                                        <label class="form-label">Socio Maggiorenne</label>
                                        <select class="form-select" name="member_id" id="adult_member_id">
                                            <option value="">Seleziona...</option>
                                            <?php foreach ($activeMembers as $member): ?>
                                                <option value="<?php echo $member['id']; ?>">
                                                    <?php echo htmlspecialchars($member['last_name'] . ' ' . $member['first_name'] . ' (' . $member['registration_number'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="junior_select" style="display: none;">
                                        <label class="form-label">Socio Minorenne</label>
                                        <select class="form-select" name="member_id" id="junior_member_id" disabled>
                                            <option value="">Seleziona...</option>
                                            <?php foreach ($activeJuniors as $junior): ?>
                                                <option value="<?php echo $junior['id']; ?>">
                                                    <?php echo htmlspecialchars($junior['last_name'] . ' ' . $junior['first_name'] . ' (' . $junior['registration_number'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Ruolo (opzionale)</label>
                                        <input type="text" class="form-control" name="role" placeholder="es. Presidente, Segretario">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-person-plus"></i> Aggiungi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invia Convocazioni -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-envelope"></i> Convocazione</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($meeting['convocation_sent_at'])): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> Convocazione già inviata il 
                                        <?php echo date('d/m/Y H:i', strtotime($meeting['convocation_sent_at'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <p>Invia la convocazione via email a tutti i partecipanti che hanno un indirizzo email registrato.</p>
                                
                                <?php
                                $participantsWithEmail = 0;
                                $participantsWithoutEmail = 0;
                                if (!empty($meeting['participants'])) {
                                    foreach ($meeting['participants'] as $p) {
                                        if (!empty($p['invitation_sent_at'])) {
                                            continue; // Already sent
                                        }
                                        // Check if has email (simplified check)
                                        if ($p['member_type'] === 'adult') {
                                            $participantsWithEmail++;
                                        } else {
                                            $participantsWithEmail++;
                                        }
                                    }
                                }
                                ?>
                                
                                <div class="mb-3">
                                    <p><strong>Partecipanti totali:</strong> <?php echo count($meeting['participants'] ?? []); ?></p>
                                    <p><strong>Email da inviare:</strong> <?php echo $participantsWithEmail; ?></p>
                                </div>
                                
                                <form method="POST" onsubmit="return confirm('Confermi l\'invio delle convocazioni via email?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    <input type="hidden" name="action" value="send_invitations">
                                    
                                    <button type="submit" class="btn btn-primary" <?php echo empty($meeting['participants']) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-send"></i> Invia Convocazioni
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista Partecipanti -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Partecipanti (<?php echo count($meeting['participants'] ?? []); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($meeting['participants'])): ?>
                            <p class="text-muted">Nessun partecipante aggiunto. Utilizza i form sopra per aggiungere i partecipanti.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Matricola</th>
                                            <th>Tipo</th>
                                            <th>Ruolo</th>
                                            <th>Stato</th>
                                            <th>Email Inviata</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($meeting['participants'] as $participant): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    if ($participant['member_type'] === 'adult') {
                                                        echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']);
                                                    } else {
                                                        echo htmlspecialchars($participant['junior_first_name'] . ' ' . $participant['junior_last_name']);
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    echo htmlspecialchars($participant['registration_number'] ?? $participant['junior_registration_number'] ?? '-');
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $participant['member_type'] === 'adult' ? 'primary' : 'info'; ?>">
                                                        <?php echo $participant['member_type'] === 'adult' ? 'Maggiorenne' : 'Minorenne'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($participant['role'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'invited' => 'secondary',
                                                        'present' => 'success',
                                                        'absent' => 'danger',
                                                        'delegated' => 'warning'
                                                    ];
                                                    $statusLabel = [
                                                        'invited' => 'Invitato',
                                                        'present' => 'Presente',
                                                        'absent' => 'Assente',
                                                        'delegated' => 'Delegato'
                                                    ];
                                                    $status = $participant['attendance_status'] ?? 'invited';
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass[$status]; ?>">
                                                        <?php echo $statusLabel[$status]; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($participant['invitation_sent_at'])): ?>
                                                        <i class="bi bi-check-circle text-success"></i>
                                                        <?php echo date('d/m H:i', strtotime($participant['invitation_sent_at'])); ?>
                                                    <?php else: ?>
                                                        <i class="bi bi-x-circle text-muted"></i> No
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#updateAttendanceModal"
                                                                data-participant-id="<?php echo $participant['id']; ?>"
                                                                data-participant-name="<?php 
                                                                    echo htmlspecialchars($participant['first_name'] ?? $participant['junior_first_name']); 
                                                                    echo ' ';
                                                                    echo htmlspecialchars($participant['last_name'] ?? $participant['junior_last_name']); 
                                                                ?>"
                                                                data-current-status="<?php echo $participant['attendance_status'] ?? 'invited'; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal per aggiornamento presenza -->
    <div class="modal fade" id="updateAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Aggiorna Presenza</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                        <input type="hidden" name="action" value="update_attendance">
                        <input type="hidden" name="participant_id" id="modal_participant_id">
                        
                        <p>Partecipante: <strong id="modal_participant_name"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">Stato Presenza</label>
                            <select class="form-select" name="status" id="modal_status" required>
                                <option value="invited">Invitato</option>
                                <option value="present">Presente</option>
                                <option value="absent">Assente</option>
                                <option value="delegated">Delegato</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="delegated_to_field" style="display: none;">
                            <label class="form-label">Delegato a (Matricola o Nome)</label>
                            <input type="text" class="form-control" name="delegated_to" placeholder="Inserisci matricola o nome">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle member type select
        document.getElementById('member_type_select').addEventListener('change', function() {
            const adultSelect = document.getElementById('adult_select');
            const juniorSelect = document.getElementById('junior_select');
            const adultInput = document.getElementById('adult_member_id');
            const juniorInput = document.getElementById('junior_member_id');
            
            if (this.value === 'adult') {
                adultSelect.style.display = 'block';
                juniorSelect.style.display = 'none';
                adultInput.disabled = false;
                juniorInput.disabled = true;
            } else {
                adultSelect.style.display = 'none';
                juniorSelect.style.display = 'block';
                adultInput.disabled = true;
                juniorInput.disabled = false;
            }
        });
        
        // Modal for updating attendance
        const updateAttendanceModal = document.getElementById('updateAttendanceModal');
        if (updateAttendanceModal) {
            updateAttendanceModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const participantId = button.getAttribute('data-participant-id');
                const participantName = button.getAttribute('data-participant-name');
                const currentStatus = button.getAttribute('data-current-status');
                
                document.getElementById('modal_participant_id').value = participantId;
                document.getElementById('modal_participant_name').textContent = participantName;
                document.getElementById('modal_status').value = currentStatus;
                
                // Show/hide delegated field based on status
                if (currentStatus === 'delegated') {
                    document.getElementById('delegated_to_field').style.display = 'block';
                }
            });
        }
        
        // Show/hide delegated field
        document.getElementById('modal_status').addEventListener('change', function() {
            const delegatedField = document.getElementById('delegated_to_field');
            if (this.value === 'delegated') {
                delegatedField.style.display = 'block';
            } else {
                delegatedField.style.display = 'none';
            }
        });
    </script>
</body>
</html>
