<?php
/**
 * Gestione Mezzi - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un mezzo
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\VehicleController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('vehicles', 'view')) {
    die('Accesso negato');
}

$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($vehicleId <= 0) {
    header('Location: vehicles.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new VehicleController($db, $config);

$vehicle = $controller->get($vehicleId);

if (!$vehicle) {
    header('Location: vehicles.php?error=not_found');
    exit;
}

$pageTitle = 'Dettaglio Mezzo: ' . $vehicle['name'];
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
                        <a href="vehicles.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('vehicles', 'edit')): ?>
                                <a href="vehicle_edit.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="printQrCode()">
                                <i class="bi bi-qr-code"></i> Stampa QR Code
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="vehicleTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Informazioni Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button">
                            <i class="bi bi-wrench"></i> Manutenzioni
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">
                            <i class="bi bi-file-earmark"></i> Documenti
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="vehicleTabContent">
                    <!-- Tab Informazioni Generali -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="bi bi-truck"></i> Dati Mezzo</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Nome:</th>
                                                <td><?php echo htmlspecialchars($vehicle['name']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Tipo:</th>
                                                <td>
                                                    <?php 
                                                    $types = ['veicolo' => 'Veicolo', 'natante' => 'Natante', 'rimorchio' => 'Rimorchio'];
                                                    echo htmlspecialchars($types[$vehicle['vehicle_type']] ?? $vehicle['vehicle_type']);
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Targa:</th>
                                                <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Marca:</th>
                                                <td><?php echo htmlspecialchars($vehicle['brand'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Modello:</th>
                                                <td><?php echo htmlspecialchars($vehicle['model'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Anno:</th>
                                                <td><?php echo htmlspecialchars($vehicle['year'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Numero Telaio:</th>
                                                <td><?php echo htmlspecialchars($vehicle['serial_number'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Stato:</th>
                                                <td>
                                                    <?php 
                                                    $statusClass = [
                                                        'operativo' => 'success',
                                                        'in_manutenzione' => 'warning',
                                                        'fuori_servizio' => 'danger'
                                                    ];
                                                    $class = $statusClass[$vehicle['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $vehicle['status']))); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Scadenze</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Scadenza Assicurazione:</th>
                                                <td>
                                                    <?php 
                                                    if (!empty($vehicle['insurance_expiry'])) {
                                                        $date = new DateTime($vehicle['insurance_expiry']);
                                                        $now = new DateTime();
                                                        $diff = $now->diff($date)->days;
                                                        $isExpired = $date < $now;
                                                        
                                                        $class = $isExpired ? 'danger' : ($diff <= 30 ? 'warning' : 'success');
                                                        echo '<span class="badge bg-' . $class . '">' . 
                                                             htmlspecialchars($date->format('d/m/Y')) . '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Scadenza Revisione:</th>
                                                <td>
                                                    <?php 
                                                    if (!empty($vehicle['inspection_expiry'])) {
                                                        $date = new DateTime($vehicle['inspection_expiry']);
                                                        $now = new DateTime();
                                                        $diff = $now->diff($date)->days;
                                                        $isExpired = $date < $now;
                                                        
                                                        $class = $isExpired ? 'danger' : ($diff <= 30 ? 'warning' : 'success');
                                                        echo '<span class="badge bg-' . $class . '">' . 
                                                             htmlspecialchars($date->format('d/m/Y')) . '</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if (!empty($vehicle['notes'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($vehicle['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Manutenzioni -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-wrench"></i> Storico Manutenzioni</h5>
                                    <?php if ($app->checkPermission('vehicles', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Manutenzione
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($vehicle['maintenances'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Tipo</th>
                                                    <th>Descrizione</th>
                                                    <th>Costo</th>
                                                    <th>Eseguita da</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($vehicle['maintenances'] as $maintenance): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($maintenance['date']))); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $maintenance['maintenance_type'] == 'ordinaria' ? 'info' : 'warning'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($maintenance['maintenance_type'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($maintenance['description']); ?></td>
                                                        <td><?php echo !empty($maintenance['cost']) ? '€ ' . number_format($maintenance['cost'], 2) : '-'; ?></td>
                                                        <td><?php echo htmlspecialchars($maintenance['performed_by'] ?? '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessuna manutenzione registrata.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Documenti -->
                    <div class="tab-pane fade" id="documents" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-file-earmark"></i> Documenti</h5>
                                    <?php if ($app->checkPermission('vehicles', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                            <i class="bi bi-upload"></i> Carica Documento
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($vehicle['documents'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Nome File</th>
                                                    <th>Tipo Documento</th>
                                                    <th>Data Caricamento</th>
                                                    <th>Azioni</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($vehicle['documents'] as $doc): ?>
                                                    <tr>
                                                        <td><i class="bi bi-file-pdf"></i> <?php echo htmlspecialchars($doc['file_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($doc['uploaded_at']))); ?></td>
                                                        <td>
                                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" class="btn btn-sm btn-info" target="_blank">
                                                                <i class="bi bi-download"></i> Scarica
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun documento caricato.</p>
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
        function printQrCode() {
            // Implementare generazione e stampa QR code
            alert('Funzionalità in sviluppo');
        }
    </script>
</body>
</html>
