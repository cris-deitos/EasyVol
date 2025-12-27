<?php
/**
 * Gestione Riunioni - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di una riunione/assemblea
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\MeetingController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('meetings', 'view')) {
    die('Accesso negato');
}

$meetingId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($meetingId <= 0) {
    header('Location: meetings.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

$meeting = $controller->get($meetingId);

if (!$meeting) {
    header('Location: meetings.php?error=not_found');
    exit;
}

// Generate page title from meeting type and date
$typeNames = [
    'assemblea_ordinaria' => 'Assemblea dei Soci Ordinaria',
    'assemblea_straordinaria' => 'Assemblea dei Soci Straordinaria',
    'consiglio_direttivo' => 'Consiglio Direttivo',
    'riunione_capisquadra' => 'Riunione dei Capisquadra',
    'riunione_nucleo' => 'Riunione di Nucleo'
];
$meetingTypeName = $typeNames[$meeting['meeting_type']] ?? ucfirst(str_replace('_', ' ', $meeting['meeting_type']));
$meetingDateFormatted = date('d/m/Y', strtotime($meeting['meeting_date']));
$pageTitle = $meetingTypeName . ' - ' . $meetingDateFormatted;
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
                        <a href="meetings.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                <a href="meeting_edit.php?id=<?php echo $meeting['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-printer"></i> Stampa
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="printTemplate('minutes', <?php echo $meeting['id']; ?>); return false;">
                                        <i class="bi bi-file-earmark-text"></i> Verbale
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="printTemplate('attendance', <?php echo $meeting['id']; ?>); return false;">
                                        <i class="bi bi-clipboard-check"></i> Foglio Presenze
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="showPrintModal(); return false;">
                                        <i class="bi bi-gear"></i> Scegli Template...
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="meetingTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Informazioni Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button">
                            <i class="bi bi-people"></i> Partecipanti
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="agenda-tab" data-bs-toggle="tab" data-bs-target="#agenda" type="button">
                            <i class="bi bi-list-ol"></i> Ordine del Giorno
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="meetingTabContent">
                    <!-- Tab Informazioni Generali -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="bi bi-calendar3"></i> Dati Riunione</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Tipo:</th>
                                                <td>
                                                    <?php 
                                                    $types = [
                                                        'assemblea_ordinaria' => 'Assemblea dei Soci Ordinaria',
                                                        'assemblea_straordinaria' => 'Assemblea dei Soci Straordinaria',
                                                        'consiglio_direttivo' => 'Consiglio Direttivo',
                                                        'riunione_capisquadra' => 'Riunione dei Capisquadra',
                                                        'riunione_nucleo' => 'Riunione di Nucleo'
                                                    ];
                                                    $type = $types[$meeting['meeting_type']] ?? $meeting['meeting_type'];
                                                    $typeClass = [
                                                        'assemblea_ordinaria' => 'primary',
                                                        'assemblea_straordinaria' => 'danger',
                                                        'consiglio_direttivo' => 'warning',
                                                        'riunione_capisquadra' => 'info',
                                                        'riunione_nucleo' => 'success'
                                                    ];
                                                    $class = $typeClass[$meeting['meeting_type']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo htmlspecialchars($type); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Data e Ora:</th>
                                                <td>
                                                    <?php 
                                                    echo date('d/m/Y', strtotime($meeting['meeting_date']));
                                                    if (!empty($meeting['start_time'])) {
                                                        echo ' ' . date('H:i', strtotime($meeting['start_time']));
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Località:</th>
                                                <td><?php echo htmlspecialchars($meeting['location'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Convocata da:</th>
                                                <td><?php echo htmlspecialchars($meeting['convened_by'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Presidente:</th>
                                                <td><?php echo htmlspecialchars($meeting['president'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Segretario:</th>
                                                <td><?php echo htmlspecialchars($meeting['secretary'] ?? '-'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Statistiche</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Totale Partecipanti:</th>
                                                <td><strong><?php echo count($meeting['participants'] ?? []); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th>Presenti:</th>
                                                <td>
                                                    <strong class="text-success">
                                                        <?php 
                                                        $present = array_filter($meeting['participants'] ?? [], function($p) {
                                                            return ($p['attendance_status'] ?? '') === 'present';
                                                        });
                                                        echo count($present);
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Assenti:</th>
                                                <td>
                                                    <strong class="text-danger">
                                                        <?php 
                                                        $absent = array_filter($meeting['participants'] ?? [], function($p) {
                                                            return ($p['attendance_status'] ?? '') === 'absent';
                                                        });
                                                        echo count($absent);
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Punti all'OdG:</th>
                                                <td><strong><?php echo count($meeting['agenda'] ?? []); ?></strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if (!empty($meeting['notes'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($meeting['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($meeting['minutes'])): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card mb-3">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="mb-0"><i class="bi bi-file-text"></i> Verbale</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($meeting['minutes'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Partecipanti -->
                    <div class="tab-pane fade" id="participants" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-people"></i> Partecipanti</h5>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <a href="meeting_participants.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-plus-circle"></i> Gestisci Partecipanti
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($meeting['participants'])): ?>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <div class="mb-3 d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-success btn-sm" onclick="bulkUpdateAttendance('present')" id="bulk-present-btn" disabled>
                                                <i class="bi bi-check-circle"></i> Segna Selezionati come Presenti
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="bulkUpdateAttendance('absent')" id="bulk-absent-btn" disabled>
                                                <i class="bi bi-x-circle"></i> Segna Selezionati come Assenti
                                            </button>
                                            <span class="text-muted ms-2" id="selected-count">0 selezionati</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                        <th style="width: 40px;">
                                                            <input type="checkbox" class="form-check-input" id="select-all-participants" title="Seleziona tutti">
                                                        </th>
                                                    <?php endif; ?>
                                                    <th>Nome</th>
                                                    <th>Qualifica</th>
                                                    <th>Presenza</th>
                                                    <th>Note</th>
                                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                        <th>Azioni</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($meeting['participants'] as $participant): ?>
                                                    <tr>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <td>
                                                                <input type="checkbox" class="form-check-input participant-checkbox" 
                                                                       data-participant-id="<?php echo $participant['id']; ?>" 
                                                                       value="<?php echo $participant['id']; ?>">
                                                            </td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <?php 
                                                            // Construct member name based on member type
                                                            $memberName = '';
                                                            if ($participant['member_type'] === 'junior') {
                                                                $firstName = $participant['junior_first_name'] ?? '';
                                                                $lastName = $participant['junior_last_name'] ?? '';
                                                                if ($firstName || $lastName) {
                                                                    $memberName = trim($firstName . ' ' . $lastName);
                                                                }
                                                            } else {
                                                                $firstName = $participant['first_name'] ?? '';
                                                                $lastName = $participant['last_name'] ?? '';
                                                                if ($firstName || $lastName) {
                                                                    $memberName = trim($firstName . ' ' . $lastName);
                                                                }
                                                            }
                                                            // Fallback to participant_name if available
                                                            if (empty($memberName)) {
                                                                $memberName = $participant['participant_name'] ?? 'N/A';
                                                            }
                                                            echo htmlspecialchars($memberName);
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($participant['role'] ?? '-'); ?></td>
                                                        <td>
                                                            <?php 
                                                            $attendance = $participant['attendance_status'] ?? 'invited';
                                                            $attendanceClass = [
                                                                'present' => 'success',
                                                                'absent' => 'danger',
                                                                'delegated' => 'warning',
                                                                'invited' => 'secondary'
                                                            ];
                                                            $attendanceLabels = [
                                                                'present' => 'Presente',
                                                                'absent' => 'Assente',
                                                                'delegated' => 'Delegato',
                                                                'invited' => 'Invitato'
                                                            ];
                                                            $class = $attendanceClass[$attendance] ?? 'secondary';
                                                            $label = $attendanceLabels[$attendance] ?? 'Non definito';
                                                            ?>
                                                            <span class="badge bg-<?php echo $class; ?>">
                                                                <?php echo htmlspecialchars($label); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($participant['notes'] ?? '-'); ?></td>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <td>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" class="btn btn-success" 
                                                                            onclick="updateAttendance(<?php echo $participant['id']; ?>, 'present')"
                                                                            title="Segna come Presente">
                                                                        <i class="bi bi-check-circle"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-danger" 
                                                                            onclick="updateAttendance(<?php echo $participant['id']; ?>, 'absent')"
                                                                            title="Segna come Assente">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun partecipante registrato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Ordine del Giorno -->
                    <div class="tab-pane fade" id="agenda" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-list-ol"></i> Ordine del Giorno</h5>
                                    <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addAgendaModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Punto
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($meeting['agenda'])): ?>
                                    <div class="list-group">
                                        <?php foreach ($meeting['agenda'] as $item): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <h5 class="mb-1">
                                                        <?php echo htmlspecialchars($item['order_number']); ?>. 
                                                        <?php echo htmlspecialchars($item['subject'] ?? ''); ?>
                                                    </h5>
                                                    <div>
                                                        <?php if (!empty($item['voting_result']) && $item['voting_result'] !== 'non_votato'): ?>
                                                            <span class="badge bg-<?php echo $item['voting_result'] == 'approvato' ? 'success' : 'danger'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($item['voting_result'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <a href="meeting_agenda_edit.php?meeting_id=<?php echo $meeting['id']; ?>&agenda_id=<?php echo $item['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary ms-2" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if (!empty($item['description'])): ?>
                                                    <p class="mb-2"><strong>Descrizione:</strong><br><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($item['discussion'])): ?>
                                                    <p class="mb-2"><strong>Discussione:</strong><br><?php echo nl2br(htmlspecialchars($item['discussion'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($item['has_voting']) && $item['has_voting']): ?>
                                                    <div class="mt-2 p-2 bg-light rounded">
                                                        <strong><i class="bi bi-pie-chart"></i> Votazione:</strong><br>
                                                        <small class="text-muted">
                                                            Votanti: <?php echo htmlspecialchars($item['voting_total'] ?? 0); ?> | 
                                                            Favorevoli: <?php echo htmlspecialchars($item['voting_in_favor'] ?? 0); ?> | 
                                                            Contrari: <?php echo htmlspecialchars($item['voting_against'] ?? 0); ?> | 
                                                            Astenuti: <?php echo htmlspecialchars($item['voting_abstentions'] ?? 0); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun punto all'ordine del giorno.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printMinutes() {
            // Deprecated - use printTemplate instead
            printTemplate('minutes', <?php echo $meeting['id']; ?>);
        }
        
        // Print functionality
        function printTemplate(type, recordId) {
            let templateId = null;
            
            // Map template types to default template IDs for meetings
            switch(type) {
                case 'minutes':
                    templateId = 9; // Verbale di Riunione
                    break;
                case 'attendance':
                    templateId = 10; // Foglio Presenze
                    break;
            }
            
            if (templateId) {
                const url = 'print_preview.php?template_id=' + templateId + '&record_id=' + recordId + '&entity=meetings';
                window.open(url, '_blank');
            } else {
                showPrintModal();
            }
        }
        
        function showPrintModal() {
            const modal = new bootstrap.Modal(document.getElementById('printModal'));
            modal.show();
        }
        
        function generateFromModal() {
            const templateId = document.getElementById('templateSelect').value;
            if (templateId) {
                const url = 'print_preview.php?template_id=' + templateId + '&record_id=<?php echo $meeting['id']; ?>&entity=meetings';
                window.open(url, '_blank');
                const modal = bootstrap.Modal.getInstance(document.getElementById('printModal'));
                modal.hide();
            }
        }
        
        // Update attendance status
        function updateAttendance(participantId, status) {
            const statusLabel = status === 'present' ? 'Presente' : 'Assente';
            if (!confirm('Confermi di voler segnare il partecipante come ' + statusLabel + '?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('participant_id', participantId);
            formData.append('status', status);
            
            fetch('meeting_update_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Stato aggiornato con successo');
                    // Stay on the participants tab after reload
                    window.location.href = window.location.pathname + '?id=<?php echo $meetingId; ?>#participants';
                } else {
                    alert('Errore: ' + (data.message || 'Impossibile aggiornare lo stato'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiornamento dello stato');
            });
        }
        
        // Bulk update attendance status
        function bulkUpdateAttendance(status) {
            const checkboxes = document.querySelectorAll('.participant-checkbox:checked');
            const participantIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (participantIds.length === 0) {
                alert('Seleziona almeno un partecipante');
                return;
            }
            
            const statusLabel = status === 'present' ? 'Presenti' : 'Assenti';
            if (!confirm(`Confermi di voler segnare ${participantIds.length} partecipante(i) come ${statusLabel}?`)) {
                return;
            }
            
            // Send bulk update request
            const formData = new FormData();
            formData.append('participant_ids', JSON.stringify(participantIds));
            formData.append('status', status);
            
            fetch('meeting_update_attendance_bulk.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${data.updated} partecipante(i) aggiornato(i) con successo`);
                    // Stay on the participants tab after reload
                    window.location.href = window.location.pathname + '?id=<?php echo $meetingId; ?>#participants';
                } else {
                    alert('Errore: ' + (data.message || 'Impossibile aggiornare lo stato'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiornamento dello stato');
            });
        }
        
        // Restore active tab from URL hash on page load
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash === '#participants') {
                const participantsTab = new bootstrap.Tab(document.getElementById('participants-tab'));
                participantsTab.show();
            } else if (hash === '#agenda') {
                const agendaTab = new bootstrap.Tab(document.getElementById('agenda-tab'));
                agendaTab.show();
            }
            
            // Handle checkbox selection
            const selectAllCheckbox = document.getElementById('select-all-participants');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.participant-checkbox');
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    updateBulkActionButtons();
                });
            }
            
            // Add event listeners to participant checkboxes
            const participantCheckboxes = document.querySelectorAll('.participant-checkbox');
            participantCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    updateBulkActionButtons();
                    
                    // Update select all checkbox state
                    const allChecked = Array.from(participantCheckboxes).every(checkbox => checkbox.checked);
                    const someChecked = Array.from(participantCheckboxes).some(checkbox => checkbox.checked);
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                    }
                });
            });
        });
        
        // Update bulk action buttons state
        function updateBulkActionButtons() {
            const checkedCount = document.querySelectorAll('.participant-checkbox:checked').length;
            const bulkPresentBtn = document.getElementById('bulk-present-btn');
            const bulkAbsentBtn = document.getElementById('bulk-absent-btn');
            const selectedCount = document.getElementById('selected-count');
            
            if (bulkPresentBtn && bulkAbsentBtn && selectedCount) {
                bulkPresentBtn.disabled = checkedCount === 0;
                bulkAbsentBtn.disabled = checkedCount === 0;
                selectedCount.textContent = `${checkedCount} selezionati`;
            }
        }
    </script>

    <!-- Print Template Selection Modal -->
    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleziona Template di Stampa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select id="templateSelect" class="form-select">
                            <option value="9">Verbale di Riunione</option>
                            <option value="10">Foglio Presenze</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="generateFromModal()">
                        <i class="bi bi-printer"></i> Genera
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
