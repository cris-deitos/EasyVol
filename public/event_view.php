<?php
/**
 * Gestione Eventi - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un evento/intervento
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\EventController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('events', 'view')) {
    die('Accesso negato');
}

$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($eventId <= 0) {
    header('Location: events.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EventController($db, $config);

$event = $controller->get($eventId);

if (!$event) {
    header('Location: events.php?error=not_found');
    exit;
}

// Carica i mezzi disponibili per il dropdown
$availableVehicles = $controller->getAvailableVehicles($eventId);

// Helper function per creare l'etichetta del veicolo
function getVehicleLabel($vehicle) {
    // Identificatore principale (targa, nome o matricola)
    if (!empty($vehicle['license_plate'])) {
        $label = $vehicle['license_plate'];
    } elseif (!empty($vehicle['name'])) {
        $label = $vehicle['name'];
    } elseif (!empty($vehicle['serial_number'])) {
        $label = $vehicle['serial_number'];
    } else {
        $label = 'Mezzo ID ' . $vehicle['id'];
    }
    
    // Aggiungi marca/modello se disponibili
    $brandModel = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
    if (!empty($brandModel)) {
        $label .= ' - ' . $brandModel;
    }
    
    // Aggiungi tipo veicolo
    if (!empty($vehicle['vehicle_type'])) {
        $label .= ' (' . ucfirst($vehicle['vehicle_type']) . ')';
    }
    
    return $label;
}

$csrfToken = CsrfProtection::generateToken();

$pageTitle = 'Dettaglio Evento: ' . $event['title'];
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
                        <a href="events.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('events', 'edit')): ?>
                                <a href="event_edit.php?id=<?php echo $event['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="printReport()">
                                <i class="bi bi-printer"></i> Stampa Report
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="eventTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Informazioni Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="interventions-tab" data-bs-toggle="tab" data-bs-target="#interventions" type="button">
                            <i class="bi bi-list-check"></i> Interventi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button">
                            <i class="bi bi-people"></i> Partecipanti
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles" type="button">
                            <i class="bi bi-truck"></i> Mezzi
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="eventTabContent">
                    <!-- Tab Informazioni Generali -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Dati Evento</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Titolo:</th>
                                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Tipo Evento:</th>
                                                <td>
                                                    <?php 
                                                    $types = [
                                                        'emergenza' => 'Emergenza',
                                                        'esercitazione' => 'Esercitazione',
                                                        'attivita' => 'Attività',
                                                        'altro' => 'Altro'
                                                    ];
                                                    $type = $types[$event['event_type']] ?? $event['event_type'];
                                                    $typeClass = [
                                                        'emergenza' => 'danger',
                                                        'esercitazione' => 'warning',
                                                        'attivita' => 'info',
                                                        'altro' => 'secondary'
                                                    ];
                                                    $class = $typeClass[$event['event_type']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo htmlspecialchars($type); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Data Inizio:</th>
                                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($event['start_date']))); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Data Fine:</th>
                                                <td>
                                                    <?php 
                                                    echo !empty($event['end_date']) 
                                                        ? htmlspecialchars(date('d/m/Y H:i', strtotime($event['end_date']))) 
                                                        : '-'; 
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Località:</th>
                                                <td><?php echo htmlspecialchars($event['location'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Stato:</th>
                                                <td>
                                                    <?php 
                                                    $statusClass = [
                                                        'aperto' => 'success',
                                                        'in_corso' => 'warning',
                                                        'concluso' => 'secondary'
                                                    ];
                                                    $class = $statusClass[$event['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($event['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if (!empty($event['description'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Descrizione</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Statistiche</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Totale Interventi:</th>
                                                <td><strong><?php echo count($event['interventions'] ?? []); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th>Volontari Coinvolti:</th>
                                                <td><strong><?php echo count($event['participants'] ?? []); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th>Mezzi Utilizzati:</th>
                                                <td><strong><?php echo count($event['vehicles'] ?? []); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th>Ore Totali:</th>
                                                <td>
                                                    <strong>
                                                        <?php 
                                                        $totalHours = 0;
                                                        foreach ($event['participants'] ?? [] as $p) {
                                                            $totalHours += $p['hours'] ?? 0;
                                                        }
                                                        echo number_format($totalHours, 1);
                                                        ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Interventi -->
                    <div class="tab-pane fade" id="interventions" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Interventi</h5>
                                    <?php if ($app->checkPermission('events', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addInterventionModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Intervento
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($event['interventions'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Titolo</th>
                                                    <th>Data/Ora</th>
                                                    <th>Descrizione</th>
                                                    <th>Stato</th>
                                                    <?php if ($app->checkPermission('events', 'edit')): ?>
                                                        <th width="180">Azioni</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($event['interventions'] as $intervention): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($intervention['title']); ?></td>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($intervention['start_time']))); ?></td>
                                                        <td>
                                                            <?php 
                                                            $desc = $intervention['description'] ?? '';
                                                            echo htmlspecialchars(mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc);
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $statusBadgeMap = [
                                                                'in_corso' => 'warning',
                                                                'concluso' => 'success',
                                                                'sospeso' => 'secondary'
                                                            ];
                                                            $badgeClass = $statusBadgeMap[$intervention['status']] ?? 'secondary';
                                                            $statusLabel = str_replace('_', ' ', ucfirst($intervention['status']));
                                                            ?>
                                                            <span class="badge bg-<?php echo $badgeClass; ?>">
                                                                <?php echo htmlspecialchars($statusLabel); ?>
                                                            </span>
                                                        </td>
                                                        <?php if ($app->checkPermission('events', 'edit')): ?>
                                                            <td>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" class="btn btn-warning" 
                                                                            onclick="editIntervention(<?php echo $intervention['id']; ?>)" 
                                                                            title="Modifica">
                                                                        <i class="bi bi-pencil"></i>
                                                                    </button>
                                                                    <?php if ($intervention['status'] !== 'concluso'): ?>
                                                                        <button type="button" class="btn btn-success" 
                                                                                onclick="closeIntervention(<?php echo $intervention['id']; ?>)" 
                                                                                title="Chiudi Intervento">
                                                                            <i class="bi bi-check-circle"></i>
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-info" 
                                                                                onclick="reopenIntervention(<?php echo $intervention['id']; ?>)" 
                                                                                title="Riapri Intervento">
                                                                            <i class="bi bi-arrow-clockwise"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun intervento registrato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Partecipanti -->
                    <div class="tab-pane fade" id="participants" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-people"></i> Volontari Partecipanti</h5>
                                    <?php if ($app->checkPermission('events', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Partecipante
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($event['participants'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Volontario</th>
                                                    <th>Ruolo</th>
                                                    <th>Ore di Servizio</th>
                                                    <th>Note</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($event['participants'] as $participant): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($participant['role'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($participant['hours'] ?? 0); ?></td>
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
                    
                    <!-- Tab Mezzi -->
                    <div class="tab-pane fade" id="vehicles" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-truck"></i> Mezzi Utilizzati</h5>
                                    <?php if ($app->checkPermission('events', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Mezzo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($event['vehicles'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Targa/Matricola</th>
                                                    <th>Marca/Modello</th>
                                                    <th>Conducente</th>
                                                    <th>Ore di Utilizzo</th>
                                                    <th>Km Percorsi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($event['vehicles'] as $vehicle): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? $vehicle['serial_number'] ?? '-'); ?></td>
                                                        <td>
                                                            <?php 
                                                            $brandModel = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
                                                            echo htmlspecialchars($brandModel ?: '-'); 
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($vehicle['driver_name'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($vehicle['hours'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($vehicle['km_traveled'] ?? 0); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun mezzo registrato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Intervention Modal -->
    <div class="modal fade" id="addInterventionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Intervento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="interventionForm">
                        <div class="mb-3">
                            <label for="intervention_title" class="form-label">Titolo Intervento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="intervention_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="intervention_description" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="intervention_description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="intervention_start_time" class="form-label">Data e Ora Inizio <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="intervention_start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="intervention_end_time" class="form-label">Data e Ora Fine</label>
                                <input type="datetime-local" class="form-control" id="intervention_end_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="intervention_location" class="form-label">Località</label>
                            <input type="text" class="form-control" id="intervention_location">
                        </div>
                        <div class="mb-3">
                            <label for="intervention_status" class="form-label">Stato</label>
                            <select class="form-select" id="intervention_status">
                                <option value="in_corso">In Corso</option>
                                <option value="concluso">Concluso</option>
                                <option value="sospeso">Sospeso</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" onclick="saveIntervention()">
                        <i class="bi bi-save"></i> Salva
                    </button>
                </div>
            </div>
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
                        <label for="memberSearch" class="form-label">Cerca Volontario</label>
                        <input type="text" class="form-control" id="memberSearch" 
                               placeholder="Digita nome, cognome o matricola..." autocomplete="off">
                        <small class="form-text text-muted">Digita almeno 2 caratteri per cercare</small>
                    </div>
                    <div id="memberSearchResults" class="list-group" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Mezzo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addVehicleForm">
                        <div class="mb-3">
                            <label for="vehicleSelect" class="form-label">Seleziona Mezzo <span class="text-danger">*</span></label>
                            <select class="form-select" id="vehicleSelect" required>
                                <option value="">-- Seleziona un mezzo --</option>
                                <?php if (!empty($availableVehicles)): ?>
                                    <?php foreach ($availableVehicles as $vehicle): ?>
                                        <option value="<?php echo htmlspecialchars($vehicle['id']); ?>">
                                            <?php echo htmlspecialchars(getVehicleLabel($vehicle)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>Nessun mezzo disponibile</option>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">Seleziona il mezzo da assegnare all'evento</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" onclick="addVehicleFromDropdown()" <?php echo empty($availableVehicles) ? 'disabled' : ''; ?>>
                        <i class="bi bi-plus-circle"></i> Aggiungi Mezzo
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Intervention Modal -->
    <div class="modal fade" id="editInterventionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Intervento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editInterventionForm">
                        <input type="hidden" id="edit_intervention_id">
                        <div class="mb-3">
                            <label for="edit_intervention_title" class="form-label">Titolo Intervento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_intervention_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_intervention_description" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="edit_intervention_description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_intervention_start_time" class="form-label">Data e Ora Inizio <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="edit_intervention_start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_intervention_end_time" class="form-label">Data e Ora Fine</label>
                                <input type="datetime-local" class="form-control" id="edit_intervention_end_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_intervention_location" class="form-label">Località</label>
                            <input type="text" class="form-control" id="edit_intervention_location">
                        </div>
                        <div class="mb-3">
                            <label for="edit_intervention_status" class="form-label">Stato</label>
                            <select class="form-select" id="edit_intervention_status">
                                <option value="in_corso">In Corso</option>
                                <option value="concluso">Concluso</option>
                                <option value="sospeso">Sospeso</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-warning" onclick="updateIntervention()">
                        <i class="bi bi-save"></i> Salva Modifiche
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Close Intervention Modal -->
    <div class="modal fade" id="closeInterventionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Chiudi Intervento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="closeInterventionForm">
                        <input type="hidden" id="close_intervention_id">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            Stai per chiudere definitivamente questo intervento. Inserisci l'esito e le note finali.
                        </div>
                        <div class="mb-3">
                            <label for="intervention_report" class="form-label">Esito Intervento <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="intervention_report" rows="6" required 
                                      placeholder="Descrivi l'esito dell'intervento, le attività svolte e gli eventuali risultati ottenuti..."></textarea>
                            <small class="form-text text-muted">Questo campo è obbligatorio per chiudere l'intervento</small>
                        </div>
                        <div class="mb-3">
                            <label for="close_intervention_end_time" class="form-label">Data e Ora Fine</label>
                            <input type="datetime-local" class="form-control" id="close_intervention_end_time">
                            <small class="form-text text-muted">Se lasciato vuoto, verrà usata la data/ora corrente</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" onclick="confirmCloseIntervention()">
                        <i class="bi bi-check-circle"></i> Chiudi Intervento
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const eventId = <?php echo json_encode($eventId); ?>;
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        let memberSearchTimeout = null;
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Save intervention
        function saveIntervention() {
            const title = document.getElementById('intervention_title').value.trim();
            const description = document.getElementById('intervention_description').value.trim();
            const startTime = document.getElementById('intervention_start_time').value;
            const endTime = document.getElementById('intervention_end_time').value;
            const interventionLocation = document.getElementById('intervention_location').value.trim();
            const status = document.getElementById('intervention_status').value;
            
            if (!title || !startTime) {
                alert('Titolo e data/ora inizio sono obbligatori');
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add_intervention',
                    event_id: eventId,
                    title: title,
                    description: description,
                    start_time: startTime,
                    end_time: endTime,
                    location: interventionLocation,
                    status: status,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else if (data.success) {
                    alert(data.message || 'Intervento aggiunto con successo');
                    window.location.reload();
                } else {
                    alert('Risposta non valida dal server');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante il salvataggio: ' + error.message);
            });
        }
        
        // Search members for adding
        document.getElementById('memberSearch').addEventListener('input', function() {
            clearTimeout(memberSearchTimeout);
            const search = this.value.trim();
            
            if (search.length < 2) {
                document.getElementById('memberSearchResults').innerHTML = '';
                return;
            }
            
            memberSearchTimeout = setTimeout(function() {
                fetch('event_ajax.php?action=search_members&event_id=' + eventId + '&search=' + encodeURIComponent(search))
                    .then(response => response.json())
                    .then(data => {
                        const resultsDiv = document.getElementById('memberSearchResults');
                        if (data.error) {
                            resultsDiv.innerHTML = '<div class="list-group-item text-danger">' + data.error + '</div>';
                            return;
                        }
                        
                        if (data.members.length === 0) {
                            resultsDiv.innerHTML = '<div class="list-group-item text-muted">Nessun volontario trovato</div>';
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
        
        // Add member to event
        function addMember(memberId) {
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add_participant',
                    event_id: eventId,
                    member_id: memberId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else {
                    alert(data.message);
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiunta');
            });
        }
        
        // Add vehicle from dropdown
        function addVehicleFromDropdown() {
            const vehicleSelect = document.getElementById('vehicleSelect');
            const vehicleId = parseInt(vehicleSelect.value, 10);
            
            if (isNaN(vehicleId) || vehicleId <= 0) {
                alert('Seleziona un mezzo dalla lista');
                return;
            }
            
            // Usa la funzione esistente addVehicle
            addVehicle(vehicleId);
        }
        
        // Add vehicle to event
        function addVehicle(vehicleId) {
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add_vehicle',
                    event_id: eventId,
                    vehicle_id: vehicleId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else {
                    alert(data.message);
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiunta');
            });
        }
        
        function printReport() {
            // Implementare generazione e stampa report
            alert('Funzionalità in sviluppo');
        }
        
        // Edit intervention
        function editIntervention(interventionId) {
            // Fetch intervention data
            fetch('event_ajax.php?action=get_intervention&intervention_id=' + interventionId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Errore: ' + data.error);
                        return;
                    }
                    
                    const intervention = data.intervention;
                    
                    // Populate form
                    document.getElementById('edit_intervention_id').value = intervention.id;
                    document.getElementById('edit_intervention_title').value = intervention.title || '';
                    document.getElementById('edit_intervention_description').value = intervention.description || '';
                    document.getElementById('edit_intervention_location').value = intervention.location || '';
                    document.getElementById('edit_intervention_status').value = intervention.status || 'in_corso';
                    
                    // Format datetime for datetime-local input
                    if (intervention.start_time) {
                        const startTime = new Date(intervention.start_time);
                        document.getElementById('edit_intervention_start_time').value = formatDateTimeLocal(startTime);
                    }
                    
                    if (intervention.end_time) {
                        const endTime = new Date(intervention.end_time);
                        document.getElementById('edit_intervention_end_time').value = formatDateTimeLocal(endTime);
                    } else {
                        document.getElementById('edit_intervention_end_time').value = '';
                    }
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('editInterventionModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Errore durante il caricamento dei dati');
                });
        }
        
        // Update intervention
        function updateIntervention() {
            const interventionId = document.getElementById('edit_intervention_id').value;
            const title = document.getElementById('edit_intervention_title').value.trim();
            const description = document.getElementById('edit_intervention_description').value.trim();
            const startTime = document.getElementById('edit_intervention_start_time').value;
            const endTime = document.getElementById('edit_intervention_end_time').value;
            const interventionLocation = document.getElementById('edit_intervention_location').value.trim();
            const status = document.getElementById('edit_intervention_status').value;
            
            if (!title || !startTime) {
                alert('Titolo e data/ora inizio sono obbligatori');
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update_intervention',
                    intervention_id: interventionId,
                    title: title,
                    description: description,
                    start_time: startTime,
                    end_time: endTime || null,
                    location: interventionLocation,
                    status: status,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else if (data.success) {
                    alert(data.message || 'Intervento aggiornato con successo');
                    window.location.reload();
                } else {
                    alert('Risposta non valida dal server');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'aggiornamento: ' + error.message);
            });
        }
        
        // Close intervention
        function closeIntervention(interventionId) {
            document.getElementById('close_intervention_id').value = interventionId;
            document.getElementById('intervention_report').value = '';
            
            // Set current datetime as default end time
            const now = new Date();
            document.getElementById('close_intervention_end_time').value = formatDateTimeLocal(now);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('closeInterventionModal'));
            modal.show();
        }
        
        // Confirm close intervention
        function confirmCloseIntervention() {
            const interventionId = document.getElementById('close_intervention_id').value;
            const report = document.getElementById('intervention_report').value.trim();
            const endTime = document.getElementById('close_intervention_end_time').value;
            
            if (!report) {
                alert('L\'esito dell\'intervento è obbligatorio');
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'close_intervention',
                    intervention_id: interventionId,
                    report: report,
                    end_time: endTime,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else if (data.success) {
                    alert(data.message || 'Intervento chiuso con successo');
                    window.location.reload();
                } else {
                    alert('Risposta non valida dal server');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante la chiusura: ' + error.message);
            });
        }
        
        // Reopen intervention
        function reopenIntervention(interventionId) {
            if (!confirm('Sei sicuro di voler riaprire questo intervento?')) {
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reopen_intervention',
                    intervention_id: interventionId,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else if (data.success) {
                    alert(data.message || 'Intervento riaperto con successo');
                    window.location.reload();
                } else {
                    alert('Risposta non valida dal server');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante la riapertura: ' + error.message);
            });
        }
        
        // Helper function to format date for datetime-local input
        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }
    </script>
</body>
</html>
