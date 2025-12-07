<?php
/**
 * Gestione Eventi - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un evento/intervento
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\EventController;

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
                                
                                <?php if (!empty($event['notes'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="mb-0"><i class="bi bi-sticky"></i> Note</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($event['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($event['interventions'] as $intervention): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($intervention['title']); ?></td>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($intervention['datetime']))); ?></td>
                                                        <td><?php echo htmlspecialchars(substr($intervention['description'] ?? '', 0, 100)); ?>...</td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $intervention['status'] == 'completato' ? 'success' : 'warning'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($intervention['status'])); ?>
                                                            </span>
                                                        </td>
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
                                                        <td><?php echo htmlspecialchars($participant['member_name']); ?></td>
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
                                                    <th>Mezzo</th>
                                                    <th>Targa</th>
                                                    <th>Conducente</th>
                                                    <th>Ore di Utilizzo</th>
                                                    <th>Km Percorsi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($event['vehicles'] as $vehicle): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($vehicle['vehicle_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? '-'); ?></td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printReport() {
            // Implementare generazione e stampa report
            alert('Funzionalità in sviluppo');
        }
    </script>
</body>
</html>
