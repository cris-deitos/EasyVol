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
use EasyVol\Middleware\CsrfProtection;

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

$pageTitle = 'Dettaglio Mezzo';
if (!empty($vehicle['license_plate'])) {
    $pageTitle .= ': ' . $vehicle['license_plate'];
} elseif (!empty($vehicle['brand']) || !empty($vehicle['model'])) {
    $pageTitle .= ': ' . trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
} elseif (!empty($vehicle['serial_number'])) {
    $pageTitle .= ': ' . $vehicle['serial_number'];
}
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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="checklists-tab" data-bs-toggle="tab" data-bs-target="#checklists" type="button">
                            <i class="bi bi-list-check"></i> Check List
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
                                                <th width="40%">Tipo:</th>
                                                <td>
                                                    <?php 
                                                    $types = ['veicolo' => 'Veicolo', 'natante' => 'Natante', 'rimorchio' => 'Rimorchio'];
                                                    echo htmlspecialchars($types[$vehicle['vehicle_type']] ?? $vehicle['vehicle_type']);
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Targa/Matricola:</th>
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
                    
                    <!-- Tab Check List -->
                    <div class="tab-pane fade" id="checklists" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Check List Mezzo</h5>
                                    <?php if ($app->checkPermission('vehicles', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="showAddChecklistModal()">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Elemento
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Le check list vengono utilizzate durante l'uscita e il rientro dei mezzi per verificare lo stato del veicolo.
                                    Gli autisti compileranno questi elementi tramite l'interfaccia dedicata.
                                </div>
                                
                                <div id="checklistsContainer">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Caricamento...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add/Edit Checklist Item Modal -->
    <div class="modal fade" id="checklistModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="checklistModalTitle">Aggiungi Elemento Check List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="checklistForm">
                        <input type="hidden" id="checklist_id" name="id">
                        <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                        
                        <div class="mb-3">
                            <label for="item_name" class="form-label">Nome Elemento *</label>
                            <input type="text" class="form-control" id="item_name" name="item_name" required 
                                   placeholder="es: Verifica livello olio, Controllo pneumatici">
                        </div>
                        
                        <div class="mb-3">
                            <label for="item_type" class="form-label">Tipo *</label>
                            <select class="form-select" id="item_type" name="item_type" required>
                                <option value="boolean">Si/No (Checkbox)</option>
                                <option value="numeric">Numerico (Quantità)</option>
                                <option value="text">Testo Libero (Note)</option>
                            </select>
                            <small class="text-muted">
                                Scegli il tipo di risposta che l'autista dovrà fornire
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="check_timing" class="form-label">Quando Verificare *</label>
                            <select class="form-select" id="check_timing" name="check_timing" required>
                                <option value="departure">Solo in Uscita</option>
                                <option value="return">Solo al Rientro</option>
                                <option value="both">Uscita e Rientro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Ordine di Visualizzazione</label>
                            <input type="number" class="form-control" id="display_order" name="display_order" 
                                   value="0" min="0" step="1">
                            <small class="text-muted">
                                Numero più basso = mostrato per primo
                            </small>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="is_required" name="is_required">
                            <label class="form-check-label" for="is_required">
                                Campo Obbligatorio
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Annulla
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveChecklistItem()">
                        <i class="bi bi-save"></i> Salva
                    </button>
                </div>
            </div>
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
            // Validate tab parameter against whitelist to prevent XSS
            const allowedTabs = ['info', 'maintenance', 'documents'];
            if (tab && allowedTabs.includes(tab)) {
                const tabElement = document.getElementById(tab + '-tab');
                if (tabElement) {
                    const bsTab = new bootstrap.Tab(tabElement);
                    bsTab.show();
                }
            }
            
            // Imposta lo stato corrente del veicolo nel form di manutenzione
            const currentVehicleStatus = '<?php echo $vehicle['status'] ?? ''; ?>';
            const vehicleStatusSelect = document.getElementById('vehicle_status');
            if (vehicleStatusSelect && currentVehicleStatus) {
                // Imposta lo stato corrente come selezionato di default
                vehicleStatusSelect.value = currentVehicleStatus;
            }
        });
        
        // Checklist Management Functions
        const vehicleId = <?php echo $vehicleId; ?>;
        let checklistModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            checklistModal = new bootstrap.Modal(document.getElementById('checklistModal'));
            
            // Load checklists when tab is shown
            const checklistsTab = document.getElementById('checklists-tab');
            if (checklistsTab) {
                checklistsTab.addEventListener('shown.bs.tab', function () {
                    loadChecklists();
                });
            }
        });
        
        function loadChecklists() {
            const container = document.getElementById('checklistsContainer');
            
            fetch('vehicle_checklist_api.php?action=list&vehicle_id=' + vehicleId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderChecklists(data.checklists);
                    } else {
                        container.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div class="alert alert-danger">Errore nel caricamento delle check list</div>';
                });
        }
        
        function renderChecklists(checklists) {
            const container = document.getElementById('checklistsContainer');
            
            if (checklists.length === 0) {
                container.innerHTML = '<p class="text-muted">Nessuna check list configurata. Aggiungi elementi per iniziare.</p>';
                return;
            }
            
            // Group by timing
            const departure = checklists.filter(c => c.check_timing === 'departure' || c.check_timing === 'both');
            const returnList = checklists.filter(c => c.check_timing === 'return' || c.check_timing === 'both');
            
            let html = '';
            
            if (departure.length > 0) {
                html += '<h6 class="mt-3 mb-3"><i class="bi bi-box-arrow-right text-primary"></i> Check List Uscita</h6>';
                html += renderChecklistTable(departure);
            }
            
            if (returnList.length > 0) {
                html += '<h6 class="mt-4 mb-3"><i class="bi bi-box-arrow-in-left text-success"></i> Check List Rientro</h6>';
                html += renderChecklistTable(returnList);
            }
            
            container.innerHTML = html;
        }
        
        function renderChecklistTable(items) {
            const canEdit = <?php echo $app->checkPermission('vehicles', 'edit') ? 'true' : 'false'; ?>;
            
            let html = '<div class="table-responsive"><table class="table table-hover table-sm">';
            html += '<thead><tr>';
            html += '<th width="40%">Elemento</th>';
            html += '<th width="15%">Tipo</th>';
            html += '<th width="15%">Quando</th>';
            html += '<th width="10%">Obbligatorio</th>';
            html += '<th width="10%">Ordine</th>';
            if (canEdit) {
                html += '<th width="10%">Azioni</th>';
            }
            html += '</tr></thead><tbody>';
            
            items.forEach(item => {
                html += '<tr>';
                html += '<td><strong>' + escapeHtml(item.item_name) + '</strong></td>';
                html += '<td>' + getTypeLabel(item.item_type) + '</td>';
                html += '<td>' + getTimingLabel(item.check_timing) + '</td>';
                html += '<td>' + (item.is_required == 1 ? '<span class="badge bg-danger">Sì</span>' : '<span class="badge bg-secondary">No</span>') + '</td>';
                html += '<td>' + item.display_order + '</td>';
                
                if (canEdit) {
                    html += '<td>';
                    html += '<button class="btn btn-sm btn-warning me-1" onclick="editChecklistItem(' + item.id + ')" title="Modifica">';
                    html += '<i class="bi bi-pencil"></i>';
                    html += '</button>';
                    html += '<button class="btn btn-sm btn-danger" onclick="deleteChecklistItem(' + item.id + ')" title="Elimina">';
                    html += '<i class="bi bi-trash"></i>';
                    html += '</button>';
                    html += '</td>';
                }
                
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            return html;
        }
        
        function getTypeLabel(type) {
            const types = {
                'boolean': '<i class="bi bi-check-square"></i> Si/No',
                'numeric': '<i class="bi bi-123"></i> Numerico',
                'text': '<i class="bi bi-text-paragraph"></i> Testo'
            };
            return types[type] || type;
        }
        
        function getTimingLabel(timing) {
            const timings = {
                'departure': '<span class="badge bg-primary">Uscita</span>',
                'return': '<span class="badge bg-success">Rientro</span>',
                'both': '<span class="badge bg-info">Entrambi</span>'
            };
            return timings[timing] || timing;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showAddChecklistModal() {
            document.getElementById('checklistModalTitle').textContent = 'Aggiungi Elemento Check List';
            document.getElementById('checklistForm').reset();
            document.getElementById('checklist_id').value = '';
            checklistModal.show();
        }
        
        function editChecklistItem(id) {
            fetch('vehicle_checklist_api.php?action=list&vehicle_id=' + vehicleId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = data.checklists.find(c => c.id == id);
                        if (item) {
                            document.getElementById('checklistModalTitle').textContent = 'Modifica Elemento Check List';
                            document.getElementById('checklist_id').value = item.id;
                            document.getElementById('item_name').value = item.item_name;
                            document.getElementById('item_type').value = item.item_type;
                            document.getElementById('check_timing').value = item.check_timing;
                            document.getElementById('display_order').value = item.display_order;
                            document.getElementById('is_required').checked = item.is_required == 1;
                            checklistModal.show();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Errore nel caricamento dei dati');
                });
        }
        
        function saveChecklistItem() {
            const form = document.getElementById('checklistForm');
            const formData = new FormData(form);
            
            const id = document.getElementById('checklist_id').value;
            const action = id ? 'update' : 'create';
            formData.append('action', action);
            
            fetch('vehicle_checklist_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    checklistModal.hide();
                    loadChecklists();
                    
                    // Show success message
                    const container = document.getElementById('checklistsContainer');
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    container.insertBefore(alert, container.firstChild);
                    
                    setTimeout(() => alert.remove(), 3000);
                } else {
                    alert('Errore: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore nel salvataggio');
            });
        }
        
        function deleteChecklistItem(id) {
            if (!confirm('Sei sicuro di voler eliminare questo elemento della check list?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            fetch('vehicle_checklist_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadChecklists();
                    
                    // Show success message
                    const container = document.getElementById('checklistsContainer');
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    container.insertBefore(alert, container.firstChild);
                    
                    setTimeout(() => alert.remove(), 3000);
                } else {
                    alert('Errore: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore nell\'eliminazione');
            });
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
                    <?php echo CsrfProtection::getHiddenField(); ?>
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

    <!-- Modal Carica Documento -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="vehicle_document_upload.php" method="POST" enctype="multipart/form-data">
                    <?php echo CsrfProtection::getHiddenField(); ?>
                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadDocumentModalLabel">
                            <i class="bi bi-upload"></i> Carica Documento
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Tipo Documento *</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Seleziona tipo...</option>
                                <option value="Carta di Circolazione">Carta di Circolazione</option>
                                <option value="Assicurazione">Assicurazione</option>
                                <option value="Revisione">Revisione</option>
                                <option value="Libretto di Navigazione">Libretto di Navigazione</option>
                                <option value="Certificato di Omologazione">Certificato di Omologazione</option>
                                <option value="Contratto di Locazione">Contratto di Locazione</option>
                                <option value="Atto di Proprietà">Atto di Proprietà</option>
                                <option value="Altro">Altro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="document_file" class="form-label">File *</label>
                            <input type="file" class="form-control" id="document_file" name="document_file" required>
                            <div class="form-text">
                                Formati supportati: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX (max 10MB)
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="expiry_date" class="form-label">Data Scadenza</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                            <div class="form-text">
                                Opzionale. Se il documento ha una scadenza, inseriscila qui per ricevere notifiche.
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Annulla
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Carica Documento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
