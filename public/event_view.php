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
                                <?php if ($event['status'] !== 'concluso' && $event['status'] !== 'annullato'): ?>
                                    <button type="button" class="btn btn-success" 
                                            onclick="openQuickCloseModal(<?php echo $event['id']; ?>, <?php echo htmlspecialchars(json_encode($event['title']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($event['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="bi bi-check-circle"></i> Chiusura Rapida
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="event_export_excel.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary" target="_blank">
                                <i class="bi bi-file-earmark-excel"></i> Esporta Excel
                            </a>
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
                                            <tr>
                                                <th>Benefici di Legge:</th>
                                                <td>
                                                    <?php 
                                                    $benefitsValue = $event['legal_benefits_recognized'] ?? 'no';
                                                    $benefitsClass = $benefitsValue === 'si' ? 'success' : 'secondary';
                                                    $benefitsLabel = $benefitsValue === 'si' ? 'SI' : 'NO';
                                                    ?>
                                                    <span class="badge bg-<?php echo $benefitsClass; ?>">
                                                        <?php echo htmlspecialchars($benefitsLabel); ?>
                                                    </span>
                                                    <br><small class="text-muted">Art. 39 e 40 D. Lgs. n. 1 del 2018</small>
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
                                
                                <!-- Province Email Status Card -->
                                <div class="card mb-3 <?php echo $event['province_email_sent'] ? 'border-success' : 'border-warning'; ?>">
                                    <div class="card-header <?php echo $event['province_email_sent'] ? 'bg-success' : 'bg-warning'; ?> text-white">
                                        <h5 class="mb-0"><i class="bi bi-envelope"></i> Notifica Provincia</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($event['province_email_sent']): ?>
                                            <div class="alert alert-success mb-2">
                                                <i class="bi bi-check-circle-fill"></i> <strong>Email inviata alla Provincia</strong>
                                            </div>
                                            <table class="table table-sm mb-0">
                                                <tr>
                                                    <th width="40%">Data/Ora Invio:</th>
                                                    <td><?php echo !empty($event['province_email_sent_at']) ? date('d/m/Y H:i', strtotime($event['province_email_sent_at'])) : '-'; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Esito:</th>
                                                    <td>
                                                        <?php if ($event['province_email_status'] === 'success'): ?>
                                                            <span class="badge bg-success">Inviata con successo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Errore: <?php echo htmlspecialchars($event['province_email_status'] ?? 'Sconosciuto'); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Inviata da:</th>
                                                    <td>
                                                        <?php 
                                                        if (!empty($event['province_email_sent_by'])) {
                                                            $sender = $db->fetchOne(
                                                                "SELECT u.username, m.first_name, m.last_name 
                                                                FROM users u 
                                                                LEFT JOIN members m ON u.member_id = m.id 
                                                                WHERE u.id = ?", 
                                                                [$event['province_email_sent_by']]
                                                            );
                                                            if ($sender) {
                                                                if (!empty($sender['first_name']) && !empty($sender['last_name'])) {
                                                                    echo htmlspecialchars($sender['first_name'] . ' ' . $sender['last_name']);
                                                                } else {
                                                                    echo htmlspecialchars($sender['username']);
                                                                }
                                                            } else {
                                                                echo 'Utente sconosciuto';
                                                            }
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        <?php else: ?>
                                            <div class="alert alert-warning mb-2">
                                                <i class="bi bi-exclamation-triangle-fill"></i> <strong>Email non ancora inviata</strong>
                                            </div>
                                            <?php if ($app->checkPermission('events', 'edit')): ?>
                                                <p class="mb-2">Puoi inviare la notifica alla Provincia in qualsiasi momento.</p>
                                                <button type="button" class="btn btn-primary" onclick="sendProvinceEmailNow()">
                                                    <i class="bi bi-send"></i> Invia Email alla Provincia
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
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
                                                    <th>Volontari</th>
                                                    <th>Mezzi</th>
                                                    <th>Stato</th>
                                                    <?php if ($app->checkPermission('events', 'edit')): ?>
                                                        <th width="220">Azioni</th>
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
                                                            <span class="badge bg-info">
                                                                <?php echo isset($intervention['members_count']) ? $intervention['members_count'] : 0; ?> volontari
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary">
                                                                <?php echo isset($intervention['vehicles_count']) ? $intervention['vehicles_count'] : 0; ?> mezzi
                                                            </span>
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
                                                                    <button type="button" class="btn btn-info" 
                                                                            onclick="viewInterventionCard(<?php echo $intervention['id']; ?>)" 
                                                                            title="Visualizza Scheda">
                                                                        <i class="bi bi-eye"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-primary" 
                                                                            onclick="viewInterventionDetails(<?php echo $intervention['id']; ?>)" 
                                                                            title="Gestisci Risorse">
                                                                        <i class="bi bi-people"></i>/<i class="bi bi-truck"></i>
                                                                    </button>
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
                                                                        <button type="button" class="btn btn-secondary" 
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
                                                    <th>Codice Fiscale</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($event['participants'] as $participant): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></td>
                                                        <td><code><?php echo htmlspecialchars($participant['tax_code'] ?? 'N/D'); ?></code></td>
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
                            <input type="text" class="form-control" id="intervention_location" placeholder="es. Via Roma 123, Milano">
                            <small class="form-text text-muted">La georeferenziazione avviene automaticamente durante la digitazione</small>
                        </div>
                        
                        <!-- Geocoding results for intervention -->
                        <div id="intervention-geocoding-results" class="mb-3" style="display: none;">
                            <label class="form-label">Altri indirizzi trovati (opzionale):</label>
                            <div class="list-group" id="intervention-address-suggestions"></div>
                        </div>
                        
                        <!-- Hidden fields for intervention geocoding -->
                        <input type="hidden" id="intervention_latitude">
                        <input type="hidden" id="intervention_longitude">
                        <input type="hidden" id="intervention_full_address">
                        <input type="hidden" id="intervention_municipality">
                        
                        <!-- Selected address display for intervention -->
                        <div id="intervention-selected-address" class="alert alert-info mb-3" style="display: none;">
                            <strong><i class="bi bi-geo-alt"></i> Indirizzo georeferenziato:</strong><br>
                            <span id="intervention-selected-address-text"></span>
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
                        <small class="form-text text-muted">Inizia a digitare per cercare</small>
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
    
    <!-- Intervention Resources Management Modal -->
    <div class="modal fade" id="interventionResourcesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Gestione Risorse Intervento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="resources_intervention_id">
                    
                    <div class="row">
                        <!-- Volontari Section -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="bi bi-people"></i> Volontari Assegnati</h6>
                                </div>
                                <div class="card-body">
                                    <div id="interventionMembersList" style="max-height: 300px; overflow-y: auto;">
                                        <!-- Members will be loaded here -->
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <label class="form-label">Cerca e Aggiungi Volontario</label>
                                        <input type="text" class="form-control" id="interventionMemberSearch" 
                                               placeholder="Digita nome, cognome o matricola..." autocomplete="off">
                                    </div>
                                    <div id="interventionMemberSearchResults" class="list-group" style="max-height: 200px; overflow-y: auto;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vehicles Section -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0"><i class="bi bi-truck"></i> Mezzi Assegnati</h6>
                                </div>
                                <div class="card-body">
                                    <div id="interventionVehiclesList" style="max-height: 300px; overflow-y: auto;">
                                        <!-- Vehicles will be loaded here -->
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <label class="form-label">Seleziona e Aggiungi Mezzo</label>
                                        <select class="form-select" id="interventionVehicleSelect">
                                            <option value="">-- Seleziona un mezzo --</option>
                                            <?php if (!empty($availableVehicles)): ?>
                                                <?php foreach ($availableVehicles as $vehicle): ?>
                                                    <option value="<?php echo htmlspecialchars($vehicle['id']); ?>">
                                                        <?php echo htmlspecialchars(getVehicleLabel($vehicle)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn btn-primary btn-sm mt-2" onclick="addInterventionVehicle()">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Mezzo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Intervention Card Modal (Read-only) -->
    <div class="modal fade" id="viewInterventionCardModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-card-text"></i> Scheda Intervento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Intervention Details -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Dati Intervento</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="40%">Titolo:</th>
                                            <td id="view_intervention_title">-</td>
                                        </tr>
                                        <tr>
                                            <th>Descrizione:</th>
                                            <td id="view_intervention_description">-</td>
                                        </tr>
                                        <tr>
                                            <th>Località:</th>
                                            <td id="view_intervention_location">-</td>
                                        </tr>
                                        <tr>
                                            <th>Indirizzo Completo:</th>
                                            <td id="view_intervention_full_address">-</td>
                                        </tr>
                                        <tr>
                                            <th>Comune:</th>
                                            <td id="view_intervention_municipality">-</td>
                                        </tr>
                                        <tr>
                                            <th>Data/Ora Inizio:</th>
                                            <td id="view_intervention_start_time">-</td>
                                        </tr>
                                        <tr>
                                            <th>Data/Ora Fine:</th>
                                            <td id="view_intervention_end_time">-</td>
                                        </tr>
                                        <tr>
                                            <th>Stato:</th>
                                            <td id="view_intervention_status">-</td>
                                        </tr>
                                        <tr id="view_intervention_report_row" style="display: none;">
                                            <th>Esito:</th>
                                            <td id="view_intervention_report">-</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Volunteers and Vehicles -->
                        <div class="col-md-6">
                            <!-- Volunteers -->
                            <div class="card mb-3">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="bi bi-people"></i> Volontari Assegnati</h6>
                                </div>
                                <div class="card-body">
                                    <div id="view_intervention_members" style="max-height: 200px; overflow-y: auto;">
                                        <p class="text-muted">Caricamento...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicles -->
                            <div class="card mb-3">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="bi bi-truck"></i> Mezzi Assegnati</h6>
                                </div>
                                <div class="card-body">
                                    <div id="view_intervention_vehicles" style="max-height: 200px; overflow-y: auto;">
                                        <p class="text-muted">Caricamento...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
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
        
        // Helper function to save and restore active tab
        function saveActiveTab() {
            const activeTab = document.querySelector('#eventTab .nav-link.active');
            if (activeTab) {
                sessionStorage.setItem('eventViewActiveTab', activeTab.id);
            }
        }
        
        function restoreActiveTab() {
            const savedTab = sessionStorage.getItem('eventViewActiveTab');
            if (savedTab) {
                const tabElement = document.getElementById(savedTab);
                if (tabElement) {
                    const tab = new bootstrap.Tab(tabElement);
                    tab.show();
                }
                // Clear after a short delay to ensure it's been used
                setTimeout(function() {
                    sessionStorage.removeItem('eventViewActiveTab');
                }, 100);
            }
        }
        
        // Restore tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            restoreActiveTab();
        });
        
        // Helper function to reload page while preserving active tab
        function reloadWithActiveTab() {
            saveActiveTab();
            window.location.reload();
        }
        
        // Save intervention
        function saveIntervention() {
            const title = document.getElementById('intervention_title').value.trim();
            const description = document.getElementById('intervention_description').value.trim();
            const startTime = document.getElementById('intervention_start_time').value;
            const endTime = document.getElementById('intervention_end_time').value;
            const interventionLocation = document.getElementById('intervention_location').value.trim();
            const status = document.getElementById('intervention_status').value;
            const latitude = document.getElementById('intervention_latitude').value;
            const longitude = document.getElementById('intervention_longitude').value;
            const fullAddress = document.getElementById('intervention_full_address').value;
            const municipality = document.getElementById('intervention_municipality').value;
            
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
                    latitude: latitude || null,
                    longitude: longitude || null,
                    full_address: fullAddress || null,
                    municipality: municipality || null,
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
            
            if (search.length < 1) {
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
                    reloadWithActiveTab();
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
                    reloadWithActiveTab();
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
        
        // View intervention resources (members and vehicles)
        function viewInterventionDetails(interventionId) {
            document.getElementById('resources_intervention_id').value = interventionId;
            loadInterventionMembers(interventionId);
            loadInterventionVehicles(interventionId);
            
            const modal = new bootstrap.Modal(document.getElementById('interventionResourcesModal'));
            modal.show();
        }

        // View intervention card (read-only) with details, members, and vehicles
        function viewInterventionCard(interventionId) {
            // Fetch intervention data
            fetch('event_ajax.php?action=get_intervention&intervention_id=' + interventionId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Errore: ' + data.error);
                        return;
                    }
                    
                    const intervention = data.intervention;
                    
                    // Populate intervention details
                    document.getElementById('view_intervention_title').textContent = intervention.title || '-';
                    document.getElementById('view_intervention_description').textContent = intervention.description || '-';
                    document.getElementById('view_intervention_location').textContent = intervention.location || '-';
                    document.getElementById('view_intervention_full_address').textContent = intervention.full_address || '-';
                    document.getElementById('view_intervention_municipality').textContent = intervention.municipality || '-';
                    
                    // Format datetime
                    if (intervention.start_time) {
                        const startDate = new Date(intervention.start_time);
                        document.getElementById('view_intervention_start_time').textContent = 
                            startDate.toLocaleString('it-IT', { 
                                day: '2-digit', month: '2-digit', year: 'numeric', 
                                hour: '2-digit', minute: '2-digit' 
                            });
                    } else {
                        document.getElementById('view_intervention_start_time').textContent = '-';
                    }
                    
                    if (intervention.end_time) {
                        const endDate = new Date(intervention.end_time);
                        document.getElementById('view_intervention_end_time').textContent = 
                            endDate.toLocaleString('it-IT', { 
                                day: '2-digit', month: '2-digit', year: 'numeric', 
                                hour: '2-digit', minute: '2-digit' 
                            });
                    } else {
                        document.getElementById('view_intervention_end_time').textContent = '-';
                    }
                    
                    // Status badge
                    const statusBadgeMap = {
                        'in_corso': 'warning',
                        'concluso': 'success',
                        'sospeso': 'secondary'
                    };
                    const badgeClass = statusBadgeMap[intervention.status] || 'secondary';
                    const statusLabel = intervention.status ? intervention.status.replace('_', ' ').toUpperCase() : '-';
                    document.getElementById('view_intervention_status').innerHTML = 
                        `<span class="badge bg-${badgeClass}">${escapeHtml(statusLabel)}</span>`;
                    
                    // Report (if intervention is closed)
                    if (intervention.report) {
                        document.getElementById('view_intervention_report').textContent = intervention.report;
                        document.getElementById('view_intervention_report_row').style.display = '';
                    } else {
                        document.getElementById('view_intervention_report_row').style.display = 'none';
                    }
                    
                    // Load members and vehicles
                    loadViewInterventionMembers(interventionId);
                    loadViewInterventionVehicles(interventionId);
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('viewInterventionCardModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Errore durante il caricamento dei dati');
                });
        }

        // Load intervention members for read-only view
        function loadViewInterventionMembers(interventionId) {
            fetch(`event_ajax.php?action=get_intervention_members&intervention_id=${interventionId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('view_intervention_members');
                    if (data.error || !data.members || data.members.length === 0) {
                        container.innerHTML = '<p class="text-muted">Nessun volontario assegnato</p>';
                        return;
                    }
                    
                    container.innerHTML = '<ul class="list-group">' + 
                        data.members.map(member => `
                            <li class="list-group-item">
                                <i class="bi bi-person"></i> <strong>${escapeHtml(member.first_name)} ${escapeHtml(member.last_name)}</strong>
                                ${member.registration_number ? `<br><small class="text-muted">Matricola: ${escapeHtml(member.registration_number)}</small>` : ''}
                                ${member.role ? `<br><small class="text-muted">Ruolo: ${escapeHtml(member.role)}</small>` : ''}
                            </li>
                        `).join('') +
                    '</ul>';
                })
                .catch(error => {
                    console.error('Error loading members:', error);
                    document.getElementById('view_intervention_members').innerHTML = 
                        '<p class="text-danger">Errore caricamento volontari</p>';
                });
        }

        // Load intervention vehicles for read-only view
        function loadViewInterventionVehicles(interventionId) {
            fetch(`event_ajax.php?action=get_intervention_vehicles&intervention_id=${interventionId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('view_intervention_vehicles');
                    if (data.error || !data.vehicles || data.vehicles.length === 0) {
                        container.innerHTML = '<p class="text-muted">Nessun mezzo assegnato</p>';
                        return;
                    }
                    
                    container.innerHTML = '<ul class="list-group">' + 
                        data.vehicles.map(vehicle => {
                            let label = vehicle.license_plate || vehicle.name || vehicle.serial_number || `Mezzo ID ${vehicle.vehicle_id}`;
                            let details = '';
                            if (vehicle.brand || vehicle.model) {
                                details = `<br><small class="text-muted">${escapeHtml(vehicle.brand || '')} ${escapeHtml(vehicle.model || '')}</small>`;
                            }
                            return `
                                <li class="list-group-item">
                                    <i class="bi bi-truck"></i> <strong>${escapeHtml(label)}</strong>
                                    ${details}
                                </li>
                            `;
                        }).join('') +
                    '</ul>';
                })
                .catch(error => {
                    console.error('Error loading vehicles:', error);
                    document.getElementById('view_intervention_vehicles').innerHTML = 
                        '<p class="text-danger">Errore caricamento mezzi</p>';
                });
        }
        
        // Load intervention members
        function loadInterventionMembers(interventionId) {
            fetch(`event_ajax.php?action=get_intervention_members&intervention_id=${interventionId}`)
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('interventionMembersList');
                    if (data.error || !data.members || data.members.length === 0) {
                        list.innerHTML = '<p class="text-muted">Nessun volontario assegnato</p>';
                        return;
                    }
                    
                    list.innerHTML = data.members.map(member => `
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <div>
                                <strong>${escapeHtml(member.first_name)} ${escapeHtml(member.last_name)}</strong>
                                ${member.registration_number ? `<br><small class="text-muted">Matricola: ${escapeHtml(member.registration_number)}</small>` : ''}
                            </div>
                            <button class="btn btn-sm btn-danger" onclick="removeInterventionMember(${interventionId}, ${member.member_id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `).join('');
                })
                .catch(error => console.error('Error loading members:', error));
        }
        
        // Load intervention vehicles
        function loadInterventionVehicles(interventionId) {
            fetch(`event_ajax.php?action=get_intervention_vehicles&intervention_id=${interventionId}`)
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('interventionVehiclesList');
                    if (data.error || !data.vehicles || data.vehicles.length === 0) {
                        list.innerHTML = '<p class="text-muted">Nessun mezzo assegnato</p>';
                        return;
                    }
                    
                    list.innerHTML = data.vehicles.map(vehicle => {
                        let label = vehicle.license_plate || vehicle.name || vehicle.serial_number || `Mezzo ID ${vehicle.vehicle_id}`;
                        return `
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                <div>
                                    <strong>${escapeHtml(label)}</strong>
                                    ${vehicle.brand || vehicle.model ? `<br><small class="text-muted">${escapeHtml(vehicle.brand || '')} ${escapeHtml(vehicle.model || '')}</small>` : ''}
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="removeInterventionVehicle(${interventionId}, ${vehicle.vehicle_id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => console.error('Error loading vehicles:', error));
        }
        
        // Search members for intervention
        let interventionMemberSearchTimeout;
        document.getElementById('interventionMemberSearch')?.addEventListener('input', function() {
            clearTimeout(interventionMemberSearchTimeout);
            const search = this.value.trim();
            const interventionId = document.getElementById('resources_intervention_id').value;
            
            if (search.length < 1) {
                document.getElementById('interventionMemberSearchResults').innerHTML = '';
                return;
            }
            
            interventionMemberSearchTimeout = setTimeout(function() {
                fetch(`event_ajax.php?action=search_intervention_members&intervention_id=${interventionId}&search=${encodeURIComponent(search)}`)
                    .then(response => response.json())
                    .then(data => {
                        const resultsDiv = document.getElementById('interventionMemberSearchResults');
                        if (data.error || !data.members || data.members.length === 0) {
                            resultsDiv.innerHTML = '<div class="list-group-item text-muted">Nessun volontario trovato</div>';
                            return;
                        }
                        
                        resultsDiv.innerHTML = data.members.map(member => {
                            return `<button type="button" class="list-group-item list-group-item-action" onclick="addInterventionMember(${interventionId}, ${member.id})">
                                <strong>${escapeHtml(member.last_name)} ${escapeHtml(member.first_name)}</strong>
                                <span class="text-muted">(${escapeHtml(member.registration_number || '-')})</span>
                            </button>`;
                        }).join('');
                    })
                    .catch(error => console.error('Error:', error));
            }, 300);
        });
        
        // Add member to intervention
        function addInterventionMember(interventionId, memberId) {
            fetch('event_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_intervention_member',
                    intervention_id: interventionId,
                    member_id: memberId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else {
                    document.getElementById('interventionMemberSearch').value = '';
                    document.getElementById('interventionMemberSearchResults').innerHTML = '';
                    loadInterventionMembers(interventionId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Remove member from intervention
        function removeInterventionMember(interventionId, memberId) {
            if (!confirm('Rimuovere questo volontario dall\'intervento?')) return;
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'remove_intervention_member',
                    intervention_id: interventionId,
                    member_id: memberId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else {
                    loadInterventionMembers(interventionId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Add vehicle to intervention
        function addInterventionVehicle() {
            const interventionId = document.getElementById('resources_intervention_id').value;
            const vehicleId = document.getElementById('interventionVehicleSelect').value;
            
            if (!vehicleId) {
                alert('Seleziona un mezzo');
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_intervention_vehicle',
                    intervention_id: interventionId,
                    vehicle_id: vehicleId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else {
                    document.getElementById('interventionVehicleSelect').value = '';
                    loadInterventionVehicles(interventionId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Remove vehicle from intervention
        function removeInterventionVehicle(interventionId, vehicleId) {
            if (!confirm('Rimuovere questo mezzo dall\'intervento?')) return;
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'remove_intervention_vehicle',
                    intervention_id: interventionId,
                    vehicle_id: vehicleId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Errore: ' + data.error);
                } else {
                    loadInterventionVehicles(interventionId);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Geocoding functionality for interventions
        let interventionGeocodingTimeout = null;
        let interventionCurrentResults = [];
        const interventionLocationInput = document.getElementById('intervention_location');
        const interventionResultsDiv = document.getElementById('intervention-geocoding-results');
        const interventionSuggestionsDiv = document.getElementById('intervention-address-suggestions');
        const interventionSelectedAddressDiv = document.getElementById('intervention-selected-address');
        const interventionSelectedAddressText = document.getElementById('intervention-selected-address-text');
        
        // Listen to intervention location input changes
        if (interventionLocationInput) {
            interventionLocationInput.addEventListener('input', function() {
                clearTimeout(interventionGeocodingTimeout);
                
                const query = this.value.trim();
                
                if (query.length < 3) {
                    interventionResultsDiv.style.display = 'none';
                    clearInterventionGeocodingData();
                    return;
                }
                
                // Debounce: wait 800ms after user stops typing, then auto-geocode
                interventionGeocodingTimeout = setTimeout(() => {
                    searchInterventionAddress(query);
                }, 800);
            });
            
            // Auto-geocode on blur if field has content
            interventionLocationInput.addEventListener('blur', function() {
                // Single timeout with appropriate delay
                setTimeout(() => {
                    const query = this.value.trim();
                    // Store current results to avoid race condition
                    const resultsSnapshot = [...interventionCurrentResults];
                    if (query.length >= 3 && resultsSnapshot.length > 0) {
                        // Auto-select best match if not already selected
                        const latField = document.getElementById('intervention_latitude');
                        if (!latField.value || latField.value === '') {
                            selectInterventionAddress(resultsSnapshot[0], true);
                        }
                    }
                    // Hide suggestions
                    interventionResultsDiv.style.display = 'none';
                }, 300);
            });
        }
        
        // Search address for intervention
        function searchInterventionAddress(query) {
            fetch(`geocoding_api.php?action=search&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.results.length > 0) {
                        interventionCurrentResults = data.results;
                        displayInterventionSuggestions(data.results);
                        // Auto-select the best match (first result)
                        selectInterventionAddress(data.results[0], true);
                    } else {
                        interventionCurrentResults = [];
                        interventionResultsDiv.style.display = 'none';
                        clearInterventionGeocodingData();
                    }
                })
                .catch(error => {
                    console.error('Errore geocoding intervento:', error);
                    interventionCurrentResults = [];
                    interventionResultsDiv.style.display = 'none';
                    clearInterventionGeocodingData();
                });
        }
        
        // Display address suggestions for intervention
        function displayInterventionSuggestions(results) {
            interventionSuggestionsDiv.innerHTML = '';
            
            results.forEach((result, index) => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action' + (index === 0 ? ' active' : '');
                item.innerHTML = `
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${escapeHtml(result.address)}</h6>
                        <small><i class="bi bi-geo-alt"></i> ${index === 0 ? '(selezionato)' : ''}</small>
                    </div>
                    <small class="text-muted">${escapeHtml(result.display_name)}</small>
                `;
                
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectInterventionAddress(result, false);
                    interventionResultsDiv.style.display = 'none';
                });
                
                interventionSuggestionsDiv.appendChild(item);
            });
            
            interventionResultsDiv.style.display = 'block';
        }
        
        // Select an address for intervention
        function selectInterventionAddress(result, isAutomatic) {
            // Update hidden fields
            document.getElementById('intervention_latitude').value = result.latitude;
            document.getElementById('intervention_longitude').value = result.longitude;
            document.getElementById('intervention_full_address').value = result.display_name;
            document.getElementById('intervention_municipality').value = result.municipality;
            
            // Only update visible location field if user manually clicked
            if (!isAutomatic) {
                interventionLocationInput.value = result.address;
            }
            
            // Show selected address
            interventionSelectedAddressText.innerHTML = escapeHtml(result.display_name);
            if (result.municipality) {
                interventionSelectedAddressText.innerHTML += '<br><small>Comune: ' + escapeHtml(result.municipality) + '</small>';
            }
            interventionSelectedAddressDiv.style.display = 'block';
        }
        
        // Clear intervention geocoding data
        function clearInterventionGeocodingData() {
            document.getElementById('intervention_latitude').value = '';
            document.getElementById('intervention_longitude').value = '';
            document.getElementById('intervention_full_address').value = '';
            document.getElementById('intervention_municipality').value = '';
            interventionSelectedAddressDiv.style.display = 'none';
        }
        
        // Quick Close Event functions
        // Note: formatDateTimeLocal function already defined above at line 1268
        
        function openQuickCloseModal(eventId, eventTitle, eventDescription) {
            document.getElementById('quick_close_event_id').value = eventId;
            document.getElementById('quick_close_event_title').textContent = eventTitle;
            document.getElementById('quick_close_description').value = eventDescription || '';
            
            // Set current date/time as default
            const now = new Date();
            document.getElementById('quick_close_end_date').value = formatDateTimeLocal(now);
            
            const modal = new bootstrap.Modal(document.getElementById('quickCloseModal'));
            modal.show();
        }
        
        // Send province email now
        function sendProvinceEmailNow() {
            if (!confirm('Sicuro di voler inviare una mail alla Provincia con le informazioni dell\'Evento?')) {
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send_province_email',
                    event_id: eventId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Email inviata con successo alla Provincia');
                    window.location.reload();
                } else {
                    alert('Errore: ' + (data.error || 'Errore durante l\'invio dell\'email'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'invio dell\'email alla Provincia');
            });
        }
        
        function confirmQuickClose() {
            const eventId = document.getElementById('quick_close_event_id').value;
            const description = document.getElementById('quick_close_description').value.trim();
            const endDate = document.getElementById('quick_close_end_date').value;
            
            if (!endDate) {
                alert('La data di chiusura è obbligatoria');
                return;
            }
            
            fetch('event_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'quick_close_event',
                    event_id: eventId,
                    description: description,
                    end_date: endDate,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Evento chiuso con successo');
                    window.location.reload();
                } else {
                    alert('Errore: ' + (data.error || 'Errore durante la chiusura dell\'evento'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante la chiusura dell\'evento');
            });
        }
    </script>
    
    <!-- Quick Close Event Modal -->
    <div class="modal fade" id="quickCloseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Chiusura Rapida Evento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="quick_close_event_id">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong id="quick_close_event_title"></strong>
                    </div>
                    <div class="mb-3">
                        <label for="quick_close_description" class="form-label">Descrizione Evento</label>
                        <textarea class="form-control" id="quick_close_description" rows="6" 
                                  placeholder="Aggiungi o modifica la descrizione dell'evento..."></textarea>
                        <small class="form-text text-muted">Puoi integrare la descrizione esistente con ulteriori dettagli</small>
                    </div>
                    <div class="mb-3">
                        <label for="quick_close_end_date" class="form-label">Data e Ora Chiusura <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="quick_close_end_date" required>
                        <small class="form-text text-muted">Impostata automaticamente all'ora corrente, modificabile se necessario</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-success" onclick="confirmQuickClose()">
                        <i class="bi bi-check-circle"></i> Chiudi Evento
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
