<?php
/**
 * Gestione Mezzi - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un mezzo
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
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

// Log page access
AutoLogger::logPageAccess();

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
                            <div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-printer"></i> Stampa
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="printTemplate('technical', <?php echo $vehicle['id']; ?>); return false;">
                                        <i class="bi bi-file-earmark-text"></i> Scheda Tecnica
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="printQrCode(); return false;">
                                        <i class="bi bi-qr-code"></i> QR Code
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
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
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
                                                    <th>Data Scadenza</th>
                                                    <th>Data Caricamento</th>
                                                    <th>Azioni</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($vehicle['documents'] as $doc): ?>
                                                    <?php
                                                    $isExpiringSoon = false;
                                                    $isExpired = false;
                                                    if (!empty($doc['expiry_date'])) {
                                                        $expiryDate = new DateTime($doc['expiry_date']);
                                                        $today = new DateTime();
                                                        $diff = $today->diff($expiryDate);
                                                        $isExpired = $expiryDate < $today;
                                                        $isExpiringSoon = !$isExpired && $diff->days <= 30;
                                                    }
                                                    $rowClass = $isExpired ? 'table-danger' : ($isExpiringSoon ? 'table-warning' : '');
                                                    ?>
                                                    <tr class="<?php echo $rowClass; ?>">
                                                        <td><i class="bi bi-file-pdf"></i> <?php echo htmlspecialchars($doc['file_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                                        <td>
                                                            <?php if (!empty($doc['expiry_date'])): ?>
                                                                <?php echo date('d/m/Y', strtotime($doc['expiry_date'])); ?>
                                                                <?php if ($isExpired): ?>
                                                                    <br><small class="text-danger">
                                                                        <i class="bi bi-exclamation-triangle"></i> Scaduto
                                                                    </small>
                                                                <?php elseif ($isExpiringSoon): ?>
                                                                    <br><small class="text-warning">
                                                                        <i class="bi bi-clock"></i> Scade tra <?php echo $diff->days; ?> giorni
                                                                    </small>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($doc['uploaded_at']))); ?></td>
                                                        <td>
                                                            <a href="download.php?type=vehicle_attachment&id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-info" target="_blank">
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
        
        // Print functionality
        function printTemplate(type, recordId) {
            let templateId = null;
            
            // Map template types to default template IDs for vehicles
            switch(type) {
                case 'technical':
                    templateId = 7; // Scheda Tecnica Mezzo
                    break;
            }
            
            if (templateId) {
                const url = 'print_preview.php?template_id=' + templateId + '&record_id=' + recordId + '&entity=vehicles';
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
                const url = 'print_preview.php?template_id=' + templateId + '&record_id=<?php echo $vehicle['id']; ?>&entity=vehicles';
                window.open(url, '_blank');
                const modal = bootstrap.Modal.getInstance(document.getElementById('printModal'));
                modal.hide();
            }
        }
        
        // Handle tab parameter from URL (e.g., after saving maintenance)
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.querySelector('#' + tab + '-tab');
                if (tabElement) {
                    const bsTab = new bootstrap.Tab(tabElement);
                    bsTab.show();
                }
            }
        });
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
                            <option value="7">Scheda Tecnica Mezzo</option>
                            <option value="8">Elenco Mezzi</option>
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

    <!-- Modal Aggiungi Manutenzione -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-labelledby="addMaintenanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="vehicle_maintenance_save.php" method="POST">
                    <?php use EasyVol\Middleware\CsrfProtection; echo CsrfProtection::getHiddenField(); ?>
                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="addMaintenanceModalLabel">
                            <i class="bi bi-wrench"></i> Aggiungi Manutenzione
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="maintenance_type" class="form-label">Tipo Manutenzione *</label>
                                <select class="form-select" id="maintenance_type" name="maintenance_type" required>
                                    <option value="">Seleziona tipo...</option>
                                    <option value="revisione">Revisione</option>
                                    <option value="manutenzione_ordinaria">Manutenzione Ordinaria</option>
                                    <option value="manutenzione_straordinaria">Manutenzione Straordinaria</option>
                                    <option value="anomalie">Anomalie</option>
                                    <option value="guasti">Guasti</option>
                                    <option value="riparazioni">Riparazioni</option>
                                    <option value="sostituzioni">Sostituzioni</option>
                                </select>
                                <small class="text-muted">Per le revisioni, la scadenza sarà calcolata automaticamente</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label">Data *</label>
                                <input type="date" class="form-control" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Descrivi la manutenzione effettuata..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cost" class="form-label">Costo (€)</label>
                                <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0" placeholder="0.00">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="performed_by" class="form-label">Eseguita da</label>
                                <input type="text" class="form-control" id="performed_by" name="performed_by" placeholder="Nome officina o persona">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="vehicle_status" class="form-label">Aggiorna Stato Veicolo</label>
                            <select class="form-select" id="vehicle_status" name="vehicle_status">
                                <option value="">Non modificare</option>
                                <option value="operativo">Operativo</option>
                                <option value="in_manutenzione">In Manutenzione</option>
                                <option value="fuori_servizio">Fuori Servizio</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Note</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Note aggiuntive..."></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Annulla
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salva Manutenzione
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
