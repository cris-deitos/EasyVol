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

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

// Log page access
AutoLogger::logPageAccess();

// Get dashboard data
$dashboard = $controller->getDashboard();
$counts = $controller->getCounts();

$pageTitle = 'Centrale Operativa';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/easyco.css">
    <style>
        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
        .card-stat {
            border-left: 4px solid;
        }
        .card-stat.success { border-left-color: #28a745; }
        .card-stat.warning { border-left-color: #ffc107; }
        .card-stat.danger { border-left-color: #dc3545; }
        .card-stat.info { border-left-color: #17a2b8; }
        .refresh-indicator {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .resource-item {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 0.25rem;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php 
    // Use EasyCO components if user is CO user
    $user = $app->getCurrentUser();
    $isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
    
    if ($isCoUser) {
        include '../src/Views/includes/navbar_operations.php';
    } else {
        include '../src/Views/includes/navbar.php';
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            if ($isCoUser) {
                include '../src/Views/includes/sidebar_operations.php';
            } else {
                include '../src/Views/includes/sidebar.php';
            }
            ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-broadcast"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <?php if ($app->checkPermission('gate_management', 'view')): ?>
            <a href="gate_management.php" class="btn btn-sm btn-primary">
                <i class="bi bi-door-open"></i> Gestione Varchi
            </a>
        <?php endif; ?>
		    <a href="dispatch.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-broadcast-pin"></i> Consolle Radio
            </a>
        <a href="weather_radar_fullscreen.php" class="btn btn-sm btn-outline-info" target="_blank">
                <i class="bi bi-cloud-rain"></i> Radar Meteo
            </a>
        <a href="earthquakes_fullscreen.php" class="btn btn-sm btn-outline-danger" target="_blank">
                <i class="bi bi-globe"></i> Terremoti
            </a>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Aggiorna
        </button>
    </div>
</div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stat danger">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Eventi Attivi</h6>
                                <h2 class="card-title mb-0" data-stat="active_events"><?php echo $counts['active_events']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat success">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Volontari Disponibili</h6>
                                <h2 class="card-title mb-0" data-stat="available_members"><?php echo $counts['available_members']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat info">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Mezzi Operativi</h6>
                                <h2 class="card-title mb-0" data-stat="available_vehicles"><?php echo $counts['available_vehicles']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat warning">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Radio Disponibili</h6>
                                <h2 class="card-title mb-0" data-stat="available_radios"><?php echo $counts['available_radios']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-lightning"></i> Azioni Rapide</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if ($app->checkPermission('events', 'create')): ?>
                                        <div class="col-md-3 mb-2">
                                            <a href="event_edit.php" class="btn btn-danger w-100">
                                                <i class="bi bi-exclamation-triangle"></i> Nuovo Evento
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($app->checkPermission('operations_center', 'view')): ?>
                                        <div class="col-md-3 mb-2">
                                            <a href="radio_directory.php" class="btn btn-primary w-100">
                                                <i class="bi bi-broadcast"></i> Rubrica Radio
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($app->checkPermission('vehicles', 'view')): ?>
                                        <div class="col-md-3 mb-2">
                                            <a href="vehicles.php" class="btn btn-info w-100">
                                                <i class="bi bi-truck"></i> Gestione Mezzi
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($app->checkPermission('members', 'view')): ?>
                                        <div class="col-md-3 mb-2">
                                            <a href="members.php" class="btn btn-success w-100">
                                                <i class="bi bi-people"></i> Rubrica Soci
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Dashboard -->
                <div class="row">
                    <!-- Active Events -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Eventi Attivi</h5>
                                <div>
                                    <?php if ($app->checkPermission('events', 'view')): ?>
                                        <a href="event_map.php" class="btn btn-sm btn-outline-info me-2" target="_blank">
                                            <i class="bi bi-map"></i> Mappa Tempo Reale
                                        </a>
                                        <a href="events.php" class="btn btn-sm btn-outline-primary">Vedi tutti</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($dashboard['active_events'])): ?>
                                    <p class="text-muted mb-0">Nessun evento attivo al momento</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($dashboard['active_events'] as $event): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($event['start_date'])); ?>
                                                        </small>
                                                        <br>
                                                        <small>
                                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($event['event_type']); ?></span>
                                                            <?php if ($event['num_members'] > 0): ?>
                                                                <i class="bi bi-people"></i> <?php echo $event['num_members']; ?>
                                                            <?php endif; ?>
                                                            <?php if ($event['num_vehicles'] > 0): ?>
                                                                <i class="bi bi-truck"></i> <?php echo $event['num_vehicles']; ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($app->checkPermission('events', 'view')): ?>
                                                        <a href="event_view.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Available Resources -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-broadcast-pin"></i> Gestione Radio</h5>
                                <div>
                                    <?php if ($app->checkPermission('operations_center', 'edit')): ?>
                                        <a href="radio_directory.php" class="btn btn-sm btn-primary">
                                            <i class="bi bi-list"></i> Gestisci Radio
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($dashboard['available_radios'])): ?>
                                    <p class="text-muted mb-0">Nessuna radio disponibile</p>
                                <?php else: ?>
                                    <?php foreach ($dashboard['available_radios'] as $radio): ?>
                                        <div class="resource-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($radio['name']); ?></strong>
                                                    <?php if (!empty($radio['identifier'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($radio['identifier']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge bg-success">Disponibile</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Available Vehicles -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-truck"></i> Mezzi Operativi</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($dashboard['available_vehicles'])): ?>
                                    <p class="text-muted mb-0">Nessun mezzo operativo</p>
                                <?php else: ?>
                                    <?php foreach ($dashboard['available_vehicles'] as $vehicle): ?>
                                        <div class="resource-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>
                                                        <?php 
                                                        if (!empty($vehicle['license_plate'])) {
                                                            echo htmlspecialchars($vehicle['license_plate']);
                                                        } elseif (!empty($vehicle['brand']) || !empty($vehicle['model'])) {
                                                            echo htmlspecialchars(trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '')));
                                                        } elseif (!empty($vehicle['serial_number'])) {
                                                            echo htmlspecialchars($vehicle['serial_number']);
                                                        } else {
                                                            echo 'Mezzo ID ' . $vehicle['id'];
                                                        }
                                                        ?>
                                                    </strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></small>
                                                </div>
                                                <span class="badge bg-success">Operativo</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Available Members -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-people"></i> Volontari Reperibili</h5>
                                <div>
                                    <?php if ($app->checkPermission('operations_center', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOnCallModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi
                                        </button>
                                        <a href="on_call_history.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-clock-history"></i> Storico
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($dashboard['available_members'])): ?>
                                    <p class="text-muted mb-0">Nessun volontario reperibile</p>
                                <?php else: ?>
                                    <?php foreach ($dashboard['available_members'] as $member): ?>
                                        <div class="resource-item" id="oncall-<?php echo $member['schedule_id']; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <strong>
                                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                    </strong>
                                                    <?php if (!empty($member['badge_number'])): ?>
                                                        <br><small class="text-muted">Matricola: <?php echo htmlspecialchars($member['badge_number']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['phone'])): ?>
                                                        <br><small><i class="bi bi-telephone-fill text-primary"></i> <?php echo htmlspecialchars($member['phone']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['radio_name'])): ?>
                                                        <br><small><i class="bi bi-broadcast text-success"></i> Radio: <?php echo htmlspecialchars($member['radio_name']); ?>
                                                        <?php if (!empty($member['radio_identifier'])): ?>
                                                            (<?php echo htmlspecialchars($member['radio_identifier']); ?>)
                                                        <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['on_call_notes'])): ?>
                                                        <br><small class="text-muted"><i class="bi bi-sticky"></i> <?php echo htmlspecialchars($member['on_call_notes']); ?></small>
                                                    <?php endif; ?>
                                                    <br><small class="text-muted">
                                                        <i class="bi bi-clock"></i> 
                                                        <?php echo date('d/m/Y H:i', strtotime($member['start_datetime'])); ?> - 
                                                        <?php echo date('d/m/Y H:i', strtotime($member['end_datetime'])); ?>
                                                    </small>
                                                </div>
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="badge bg-success">Reperibile</span>
                                                    <?php if ($app->checkPermission('operations_center', 'edit')): ?>
                                                        <button type="button" class="btn btn-sm btn-warning" 
                                                                onclick="editOnCall(<?php echo $member['schedule_id']; ?>)" 
                                                                title="Modifica">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="removeOnCall(<?php echo $member['schedule_id']; ?>)" 
                                                                title="Rimuovi">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add On-Call Volunteer Modal -->
    <div class="modal fade" id="addOnCallModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Aggiungi Volontario Reperibile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addOnCallForm" action="on_call_ajax.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_on_call">
                        <input type="hidden" name="csrf_token" value="<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Volontario <span class="text-danger">*</span></label>
                            <input type="hidden" name="member_id" id="member_id" required>
                            <input type="text" class="form-control" id="member_search" 
                                   placeholder="Cerca per matricola, nome o cognome..." 
                                   autocomplete="off" required>
                            <div id="member_search_results" class="list-group position-absolute" style="z-index: 1050; max-height: 300px; overflow-y: auto; display: none;"></div>
                            <small class="text-muted">Digita almeno 1 carattere per cercare</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data e Ora Inizio Reperibilità <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="start_datetime" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            <small class="text-muted">Impostato automaticamente all'ora attuale</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data e Ora Fine Reperibilità <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="end_datetime" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Note sulla reperibilità..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Aggiungi Reperibilità
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit On-Call Volunteer Modal -->
    <div class="modal fade" id="editOnCallModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Modifica Reperibilità</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editOnCallForm" action="on_call_ajax.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_on_call">
                        <input type="hidden" name="schedule_id" id="edit_schedule_id">
                        <input type="hidden" name="csrf_token" value="<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Volontario</label>
                            <input type="text" class="form-control" id="edit_member_name" readonly disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data e Ora Inizio Reperibilità <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="start_datetime" id="edit_start_datetime" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data e Ora Fine Reperibilità <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="end_datetime" id="edit_end_datetime" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="2" 
                                      placeholder="Note sulla reperibilità..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save"></i> Salva Modifiche
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 60 seconds (can be disabled by user if needed)
        // Note: In production, consider implementing AJAX updates instead
        const autoRefreshEnabled = true;
        if (autoRefreshEnabled) {
            setTimeout(function() {
                location.reload();
            }, 60000);
        }
        
        // Member search autocomplete
        let searchTimeout;
        const memberSearchInput = document.getElementById('member_search');
        const memberSearchResults = document.getElementById('member_search_results');
        const memberIdInput = document.getElementById('member_id');
        
        memberSearchInput?.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 1) {
                memberSearchResults.style.display = 'none';
                memberSearchResults.innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(async function() {
                try {
                    const response = await fetch('members_search_ajax.php?q=' + encodeURIComponent(query));
                    const results = await response.json();
                    
                    if (results.length > 0) {
                        memberSearchResults.innerHTML = results.map(member => 
                            `<button type="button" class="list-group-item list-group-item-action" 
                                     data-member-id="${member.id}" 
                                     data-member-name="${member.value}">
                                ${member.label}
                            </button>`
                        ).join('');
                        memberSearchResults.style.display = 'block';
                    } else {
                        memberSearchResults.innerHTML = '<div class="list-group-item">Nessun volontario trovato</div>';
                        memberSearchResults.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Errore nella ricerca:', error);
                }
            }, 300);
        });
        
        // Handle member selection
        memberSearchResults?.addEventListener('click', function(e) {
            const button = e.target.closest('button[data-member-id]');
            if (button) {
                memberIdInput.value = button.dataset.memberId;
                memberSearchInput.value = button.dataset.memberName;
                memberSearchResults.style.display = 'none';
            }
        });
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!memberSearchInput?.contains(e.target) && !memberSearchResults?.contains(e.target)) {
                memberSearchResults.style.display = 'none';
            }
        });
        
        // Handle on-call form submission
        document.getElementById('addOnCallForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('on_call_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Errore: ' + result.message);
                }
            } catch (error) {
                alert('Errore durante il salvataggio: ' + error.message);
            }
        });
        
        // Edit on-call schedule
        const editOnCallModal = new bootstrap.Modal(document.getElementById('editOnCallModal'));
        let currentScheduleData = {};
        
        window.editOnCall = async function(scheduleId) {
            // Find the schedule data from the page
            const scheduleElement = document.getElementById('oncall-' + scheduleId);
            if (!scheduleElement) return;
            
            // Get all available members data to find the current one
            <?php echo "const availableMembers = " . json_encode($dashboard['available_members'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";"; ?>
            const scheduleData = availableMembers.find(m => m.schedule_id == scheduleId);
            
            if (!scheduleData) {
                alert('Reperibilità non trovata');
                return;
            }
            
            currentScheduleData = scheduleData;
            
            // Populate form
            document.getElementById('edit_schedule_id').value = scheduleId;
            document.getElementById('edit_member_name').value = scheduleData.first_name + ' ' + scheduleData.last_name;
            
            // Convert datetime strings to input format
            const startDate = new Date(scheduleData.start_datetime);
            const endDate = new Date(scheduleData.end_datetime);
            
            document.getElementById('edit_start_datetime').value = formatDateTimeLocal(startDate);
            document.getElementById('edit_end_datetime').value = formatDateTimeLocal(endDate);
            document.getElementById('edit_notes').value = scheduleData.on_call_notes || '';
            
            editOnCallModal.show();
        };
        
        // Format date for datetime-local input
        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }
        
        // Handle edit form submission
        document.getElementById('editOnCallForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('on_call_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Errore: ' + result.message);
                }
            } catch (error) {
                alert('Errore durante il salvataggio: ' + error.message);
            }
        });
        
        // Remove on-call schedule
        window.removeOnCall = async function(scheduleId) {
            if (!confirm('Sei sicuro di voler rimuovere questa reperibilità?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'remove_on_call');
            formData.append('schedule_id', scheduleId);
            formData.append('csrf_token', '<?php echo \EasyVol\Middleware\CsrfProtection::generateToken(); ?>');
            
            try {
                const response = await fetch('on_call_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Errore: ' + result.message);
                }
            } catch (error) {
                alert('Errore durante la rimozione: ' + error.message);
            }
        };
    </script>
    <script src="../assets/js/notifications-auto-update.js"></script>
</body>
</html>