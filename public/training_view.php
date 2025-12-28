<?php
/**
 * Gestione Formazione - Visualizzazione Dettaglio Corso
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

$courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;

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

$pageTitle = 'Dettaglio Corso: ' . $course['course_name'];
$csrfToken = $csrf->generateToken();
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
                        <a href="training.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($course['course_name']); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('training', 'edit')): ?>
                                <a href="training_edit.php?id=<?php echo $course['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="bi bi-printer"></i> Stampa
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Alert container for AJAX messages -->
                <div id="alertContainer"></div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="courseTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Informazioni Corso
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" type="button">
                            <i class="bi bi-calendar-week"></i> Sessioni (<?php echo count($course['sessions'] ?? []); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button">
                            <i class="bi bi-people"></i> Partecipanti (<?php echo count($course['participants']); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button">
                            <i class="bi bi-calendar-check"></i> Presenze
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="courseTabContent">
                    <!-- Tab Informazioni -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Dati Generali</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Nome Corso:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['course_name']); ?></dd>
                                            
                                            <dt class="col-sm-4">Tipo:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['course_type'] ?? '-'); ?></dd>
                                            
                                            <?php if (!empty($course['sspc_course_code'])): ?>
                                            <dt class="col-sm-4">Codice Corso SSPC:</dt>
                                            <dd class="col-sm-8">
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($course['sspc_course_code']); ?></span>
                                            </dd>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($course['sspc_edition_code'])): ?>
                                            <dt class="col-sm-4">Codice Edizione SSPC:</dt>
                                            <dd class="col-sm-8">
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($course['sspc_edition_code']); ?></span>
                                            </dd>
                                            <?php endif; ?>
                                            
                                            <dt class="col-sm-4">Stato:</dt>
                                            <dd class="col-sm-8">
                                                <?php
                                                $statusClass = [
                                                    'pianificato' => 'info',
                                                    'in_corso' => 'warning',
                                                    'completato' => 'success',
                                                    'annullato' => 'danger'
                                                ];
                                                $class = $statusClass[$course['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($course['status'])); ?>
                                                </span>
                                            </dd>
                                            
                                            <dt class="col-sm-4">Data Inizio:</dt>
                                            <dd class="col-sm-8"><?php echo $course['start_date'] ? date('d/m/Y', strtotime($course['start_date'])) : '-'; ?></dd>
                                            
                                            <dt class="col-sm-4">Data Fine:</dt>
                                            <dd class="col-sm-8"><?php echo $course['end_date'] ? date('d/m/Y', strtotime($course['end_date'])) : '-'; ?></dd>
                                            
                                            <dt class="col-sm-4">Ore Totali:</dt>
                                            <dd class="col-sm-8">
                                                <span class="badge bg-info"><?php echo number_format($course['total_hours'] ?? 0, 1); ?> ore</span>
                                            </dd>
                                            
                                            <dt class="col-sm-4">Istruttore:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['instructor'] ?? '-'); ?></dd>
                                            
                                            <dt class="col-sm-4">Luogo:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['location'] ?? '-'); ?></dd>
                                            
                                            <dt class="col-sm-4">Max Partecipanti:</dt>
                                            <dd class="col-sm-8"><?php echo $course['max_participants'] ? htmlspecialchars($course['max_participants']) : 'Illimitato'; ?></dd>
                                        </dl>
                                    </div>
                                </div>
                                
                                <?php if ($course['description']): ?>
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Descrizione</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Statistiche</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-primary"><?php echo $course['stats']['total_partecipanti'] ?? 0; ?></h3>
                                                    <small class="text-muted">Iscritti</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-info"><?php echo count($course['sessions'] ?? []); ?></h3>
                                                    <small class="text-muted">Sessioni</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-success"><?php echo number_format($course['total_hours'] ?? 0, 1); ?></h3>
                                                    <small class="text-muted">Ore Totali</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-warning"><?php echo $course['stats']['certificati_rilasciati'] ?? 0; ?></h3>
                                                    <small class="text-muted">Certificati</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Sessioni -->
                    <div class="tab-pane fade" id="sessions" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calendar-week"></i> Sessioni del Corso
                                </h5>
                                <?php if ($app->checkPermission('training', 'edit')): ?>
                                    <a href="training_attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-calendar-plus"></i> Gestisci Sessioni
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($course['sessions'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-calendar-x fs-1"></i>
                                        <p class="mt-3 mb-0">Nessuna sessione programmata</p>
                                        <p class="small">Vai a "Gestisci Sessioni" per aggiungere le date del corso</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Orario</th>
                                                    <th>Durata</th>
                                                    <th>Descrizione</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($course['sessions'] as $session): ?>
                                                    <?php 
                                                    $duration = round((strtotime($session['end_time']) - strtotime($session['start_time'])) / 3600, 1);
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo date('d/m/Y', strtotime($session['session_date'])); ?></strong></td>
                                                        <td>
                                                            <?php echo date('H:i', strtotime($session['start_time'])); ?> - 
                                                            <?php echo date('H:i', strtotime($session['end_time'])); ?>
                                                        </td>
                                                        <td><span class="badge bg-info"><?php echo $duration; ?>h</span></td>
                                                        <td><?php echo htmlspecialchars($session['description'] ?? '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-info">
                                                    <th colspan="2">Totale</th>
                                                    <th><span class="badge bg-primary"><?php echo number_format($course['total_hours'], 1); ?>h</span></th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Partecipanti -->
                    <div class="tab-pane fade" id="participants" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Elenco Partecipanti</h5>
                                <?php if ($app->checkPermission('training', 'edit')): ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                                        <i class="bi bi-plus-circle"></i> Aggiungi Partecipante
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Matricola</th>
                                                <th>Nome</th>
                                                <th>Ore Presenza</th>
                                                <th>Ore Assenza</th>
                                                <th>Esame</th>
                                                <th>Voto</th>
                                                <th>Certificato</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody id="participantsTable">
                                            <?php if (empty($course['participants'])): ?>
                                                <tr id="noParticipantsRow">
                                                    <td colspan="8" class="text-center">Nessun partecipante registrato</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($course['participants'] as $participant): ?>
                                                    <tr data-participant-id="<?php echo $participant['id']; ?>">
                                                        <td><?php echo htmlspecialchars($participant['registration_number']); ?></td>
                                                        <td>
                                                            <a href="member_view.php?id=<?php echo $participant['member_id']; ?>">
                                                                <?php echo htmlspecialchars($participant['last_name'] . ' ' . $participant['first_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success">
                                                                <?php echo number_format($participant['total_hours_attended'] ?? 0, 1); ?>h
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-danger">
                                                                <?php echo number_format($participant['total_hours_absent'] ?? 0, 1); ?>h
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($participant['exam_passed'] === null): ?>
                                                                <span class="badge bg-secondary">Non sostenuto</span>
                                                            <?php elseif ($participant['exam_passed']): ?>
                                                                <span class="badge bg-success">Superato</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Non superato</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($participant['exam_score']): ?>
                                                                <strong><?php echo $participant['exam_score']; ?>/10</strong>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($participant['certificate_issued']): ?>
                                                                <i class="bi bi-check-circle-fill text-success"></i>
                                                            <?php else: ?>
                                                                <i class="bi bi-x-circle text-muted"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($app->checkPermission('training', 'edit')): ?>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button type="button" class="btn btn-outline-warning" 
                                                                            onclick="editParticipant(<?php echo $participant['id']; ?>)" title="Modifica">
                                                                        <i class="bi bi-pencil"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-danger" 
                                                                            onclick="removeParticipant(<?php echo $participant['id']; ?>)" title="Rimuovi">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Presenze -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Registro Presenze</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <p class="text-muted">
                                            <i class="bi bi-info-circle"></i> 
                                            Il registro presenze permette di tracciare la partecipazione per ogni sessione del corso.
                                            Prima di registrare le presenze, è necessario creare le sessioni con date e orari.
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($app->checkPermission('training', 'edit')): ?>
                                            <a href="training_attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-calendar-check"></i> Gestisci Presenze
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($course['sessions'])): ?>
                                    <hr>
                                    <h6>Riepilogo Sessioni</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="border rounded p-3 text-center">
                                                <h4 class="text-primary"><?php echo count($course['sessions']); ?></h4>
                                                <small>Sessioni Programmate</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3 text-center">
                                                <h4 class="text-info"><?php echo number_format($course['total_hours'], 1); ?>h</h4>
                                                <small>Ore Totali</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3 text-center">
                                                <h4 class="text-success"><?php echo count($course['participants']); ?></h4>
                                                <small>Partecipanti</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Participant Modal -->
    <div class="modal fade" id="addParticipantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Partecipante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="memberSearch" class="form-label">Cerca Socio</label>
                        <input type="text" class="form-control" id="memberSearch" 
                               placeholder="Digita nome, cognome o matricola..." autocomplete="off">
                        <small class="form-text text-muted">Inizia a digitare per cercare</small>
                    </div>
                    <div id="searchResults" class="list-group" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Participant Modal -->
    <div class="modal fade" id="editParticipantModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Partecipante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_participant_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Partecipante</label>
                        <p id="edit_participant_name" class="form-control-plaintext fw-bold"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_attendance_status" class="form-label">Stato</label>
                        <select class="form-select" id="edit_attendance_status">
                            <option value="iscritto">Iscritto</option>
                            <option value="presente">Presente</option>
                            <option value="assente">Assente</option>
                            <option value="ritirato">Ritirato</option>
                        </select>
                    </div>
                    
                    <hr>
                    <h6>Esame Finale</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_exam_passed" class="form-label">Esito Esame</label>
                            <select class="form-select" id="edit_exam_passed">
                                <option value="">Non sostenuto</option>
                                <option value="1">Superato</option>
                                <option value="0">Non superato</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_exam_score" class="form-label">Punteggio (1-10)</label>
                            <input type="number" class="form-control" id="edit_exam_score" 
                                   min="1" max="10" placeholder="1-10">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_final_grade" class="form-label">Valutazione/Note</label>
                        <input type="text" class="form-control" id="edit_final_grade" 
                               placeholder="Es: Ottimo, Buono, Sufficiente...">
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_certificate_issued">
                            <label class="form-check-label" for="edit_certificate_issued">
                                Certificato Rilasciato
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="saveParticipant()">
                        <i class="bi bi-save"></i> Salva
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const courseId = <?php echo $courseId; ?>;
        const csrfToken = '<?php echo $csrfToken; ?>';
        let searchTimeout = null;
        
        // Search members for adding
        document.getElementById('memberSearch').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const search = this.value.trim();
            
            if (search.length < 1) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(function() {
                fetch('training_ajax.php?action=search_members&course_id=' + courseId + '&search=' + encodeURIComponent(search))
                    .then(response => response.json())
                    .then(data => {
                        const resultsDiv = document.getElementById('searchResults');
                        if (data.error) {
                            resultsDiv.innerHTML = '<div class="list-group-item text-danger">' + data.error + '</div>';
                            return;
                        }
                        
                        if (data.members.length === 0) {
                            resultsDiv.innerHTML = '<div class="list-group-item text-muted">Nessun socio trovato</div>';
                            return;
                        }
                        
                        resultsDiv.innerHTML = data.members.map(function(member) {
                            return '<button type="button" class="list-group-item list-group-item-action" onclick="addMember(' + member.id + ')">' +
                                '<strong>' + escapeHtml(member.last_name) + ' ' + escapeHtml(member.first_name) + '</strong>' +
                                ' <span class="text-muted">(' + escapeHtml(member.registration_number || '-') + ')</span>' +
                                '</button>';
                        }).join('');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }, 300);
        });
        
        // Add member to course
        function addMember(memberId) {
            fetch('training_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add_participant',
                    course_id: courseId,
                    member_id: memberId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    // Reload page to show updated participant list
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert('danger', data.error || 'Errore durante l\'aggiunta');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Errore di connessione');
            });
        }
        
        // Edit participant
        function editParticipant(participantId) {
            fetch('training_ajax.php?action=get_participant&participant_id=' + participantId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showAlert('danger', data.error);
                        return;
                    }
                    
                    const p = data.participant;
                    document.getElementById('edit_participant_id').value = p.id;
                    document.getElementById('edit_participant_name').textContent = p.last_name + ' ' + p.first_name + ' (' + (p.registration_number || '-') + ')';
                    document.getElementById('edit_attendance_status').value = p.attendance_status || 'iscritto';
                    document.getElementById('edit_exam_passed').value = p.exam_passed !== null ? p.exam_passed : '';
                    document.getElementById('edit_exam_score').value = p.exam_score || '';
                    document.getElementById('edit_final_grade').value = p.final_grade || '';
                    document.getElementById('edit_certificate_issued').checked = p.certificate_issued == 1;
                    
                    var modal = new bootstrap.Modal(document.getElementById('editParticipantModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'Errore di connessione');
                });
        }
        
        // Save participant
        function saveParticipant() {
            const participantId = document.getElementById('edit_participant_id').value;
            
            const data = {
                action: 'update_participant',
                participant_id: participantId,
                attendance_status: document.getElementById('edit_attendance_status').value,
                exam_passed: document.getElementById('edit_exam_passed').value,
                exam_score: document.getElementById('edit_exam_score').value,
                final_grade: document.getElementById('edit_final_grade').value,
                certificate_issued: document.getElementById('edit_certificate_issued').checked ? 1 : 0,
                csrf_token: csrfToken
            };
            
            fetch('training_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('editParticipantModal')).hide();
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert('danger', data.error || 'Errore durante il salvataggio');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Errore di connessione');
            });
        }
        
        // Remove participant
        function removeParticipant(participantId) {
            if (!confirm('Sei sicuro di voler rimuovere questo partecipante dal corso?')) {
                return;
            }
            
            fetch('training_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'remove_participant',
                    participant_id: participantId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    // Remove row from table
                    const row = document.querySelector('[data-participant-id="' + participantId + '"]');
                    if (row) {
                        row.remove();
                    }
                    // Check if table is empty
                    const tbody = document.getElementById('participantsTable');
                    if (tbody.children.length === 0) {
                        tbody.innerHTML = '<tr id="noParticipantsRow"><td colspan="8" class="text-center">Nessun partecipante registrato</td></tr>';
                    }
                } else {
                    showAlert('danger', data.error || 'Errore durante la rimozione');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Errore di connessione');
            });
        }
        
        // Show alert
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>';
            alertContainer.innerHTML = alertHtml;
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }
        
        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Handle hash navigation for tabs
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash) {
                const tabId = window.location.hash.replace('#', '');
                const tabButton = document.querySelector('[data-bs-target="#' + tabId + '"]');
                if (tabButton) {
                    bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
            }
        });
    </script>
</body>
</html>
