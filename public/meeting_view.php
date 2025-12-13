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

$pageTitle = 'Dettaglio Riunione: ' . $meeting['title'];
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
                                                <th width="40%">Titolo:</th>
                                                <td><?php echo htmlspecialchars($meeting['title']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Tipo:</th>
                                                <td>
                                                    <?php 
                                                    $types = [
                                                        'assemblea_ordinaria' => 'Assemblea Ordinaria',
                                                        'assemblea_straordinaria' => 'Assemblea Straordinaria',
                                                        'consiglio_direttivo' => 'Consiglio Direttivo',
                                                        'altro' => 'Altro'
                                                    ];
                                                    $type = $types[$meeting['meeting_type']] ?? $meeting['meeting_type'];
                                                    $typeClass = [
                                                        'assemblea_ordinaria' => 'primary',
                                                        'assemblea_straordinaria' => 'danger',
                                                        'consiglio_direttivo' => 'warning',
                                                        'altro' => 'secondary'
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
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($meeting['meeting_date']))); ?></td>
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
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Partecipante
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($meeting['participants'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Nome</th>
                                                    <th>Qualifica</th>
                                                    <th>Presenza</th>
                                                    <th>Note</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($meeting['participants'] as $participant): ?>
                                                    <tr>
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
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1">
                                                        <?php echo htmlspecialchars($item['order_number']); ?>. 
                                                        <?php echo htmlspecialchars($item['subject'] ?? ''); ?>
                                                    </h5>
                                                    <?php if (!empty($item['vote_result'])): ?>
                                                        <span class="badge bg-<?php echo $item['vote_result'] == 'approvato' ? 'success' : 'danger'; ?>">
                                                            <?php echo htmlspecialchars(ucfirst($item['vote_result'])); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($item['description'])): ?>
                                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($item['vote_details'])): ?>
                                                    <small class="text-muted">
                                                        Votazione: <?php echo htmlspecialchars($item['vote_details']); ?>
                                                    </small>
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
