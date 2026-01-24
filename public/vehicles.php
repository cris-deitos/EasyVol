<?php
/**
 * Gestione Mezzi - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\VehicleController;
use EasyVol\Controllers\PrintTemplateController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('vehicles', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new VehicleController($db, $config);

// Load print templates for vehicles
$printController = new PrintTemplateController($db, $config);
$printTemplates = $printController->getAll([
    'entity_type' => 'vehicles',
    'is_active' => 1
]);

$filters = [
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$vehicles = $controller->index($filters, $page, $perPage);

// Get total count for pagination
$totalResults = $controller->count($filters);
$totalPages = max(1, ceil($totalResults / $perPage));

// Conteggi per status
$statusCounts = [
    'operativo' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'operativo'")['count'] ?? 0,
    'in_manutenzione' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'in_manutenzione'")['count'] ?? 0,
    'fuori_servizio' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'fuori_servizio'")['count'] ?? 0,
];

$pageTitle = 'Gestione Mezzi';
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
                    <h1 class="h2"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->checkPermission('vehicles', 'export')): ?>
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Esporta
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="data_export.php?entity=vehicles&format=excel">
                                    <i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)
                                </a></li>
                                <li><a class="dropdown-item" href="data_export.php?entity=vehicles&format=csv">
                                    <i class="bi bi-file-earmark-text"></i> CSV
                                </a></li>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-printer"></i> Stampa
                            </button>
                            <ul class="dropdown-menu">
                                <?php if (!empty($printTemplates)): ?>
                                    <?php 
                                    $displayedTemplates = array_slice($printTemplates, 0, 3); 
                                    foreach ($displayedTemplates as $template): 
                                    ?>
                                        <li><a class="dropdown-item" href="#" onclick="printListById(<?php echo $template['id']; ?>); return false;">
                                            <i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars($template['name']); ?>
                                        </a></li>
                                    <?php endforeach; ?>
                                    <?php if (count($printTemplates) > 3): ?>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="#" onclick="showPrintListModal(); return false;">
                                    <i class="bi bi-gear"></i> Scegli Template...
                                </a></li>
                            </ul>
                        </div>
                        <?php if ($app->checkPermission('vehicles', 'create')): ?>
                            <a href="vehicle_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Mezzo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Mezzi Operativi</h5>
                                <h2><?php echo number_format($statusCounts['operativo']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">In Manutenzione</h5>
                                <h2><?php echo number_format($statusCounts['in_manutenzione']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Fuori Servizio</h5>
                                <h2><?php echo number_format($statusCounts['fuori_servizio']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Targa, marca, modello...">
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tutti</option>
                                    <option value="veicolo" <?php echo $filters['type'] === 'veicolo' ? 'selected' : ''; ?>>Veicolo</option>
                                    <option value="natante" <?php echo $filters['type'] === 'natante' ? 'selected' : ''; ?>>Natante</option>
                                    <option value="rimorchio" <?php echo $filters['type'] === 'rimorchio' ? 'selected' : ''; ?>>Rimorchio</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="operativo" <?php echo $filters['status'] === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                                    <option value="in_manutenzione" <?php echo $filters['status'] === 'in_manutenzione' ? 'selected' : ''; ?>>In Manutenzione</option>
                                    <option value="fuori_servizio" <?php echo $filters['status'] === 'fuori_servizio' ? 'selected' : ''; ?>>Fuori Servizio</option>
                                    <option value="dismesso" <?php echo $filters['status'] === 'dismesso' ? 'selected' : ''; ?>>Dismesso</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Mezzi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Mezzi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Targa/Matricola</th>
                                        <th>Marca/Modello</th>
                                        <th>Anno</th>
                                        <th>Scad. Assicurazione</th>
                                        <th>Scad. Revisione</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vehicles)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nessun mezzo trovato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-<?php 
                                                        echo $vehicle['vehicle_type'] === 'veicolo' ? 'truck' : 
                                                            ($vehicle['vehicle_type'] === 'natante' ? 'water' : 'box-seam'); 
                                                    ?>"></i>
                                                    <?php echo ucfirst($vehicle['vehicle_type']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? $vehicle['serial_number'] ?? '-'); ?></td>
                                                <td>
                                                    <?php 
                                                    $brandModel = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
                                                    echo htmlspecialchars($brandModel ?: '-'); 
                                                    ?>
                                                </td>
                                                <td><?php echo $vehicle['year'] ?? '-'; ?></td>
                                                <td>
                                                    <?php if ($vehicle['insurance_expiry']): ?>
                                                        <?php
                                                        $expiry = new DateTime($vehicle['insurance_expiry']);
                                                        $today = new DateTime();
                                                        $diff = $today->diff($expiry)->days;
                                                        $expired = $expiry < $today;
                                                        $class = $expired ? 'danger' : ($diff <= 30 ? 'warning' : 'success');
                                                        ?>
                                                        <span class="badge bg-<?php echo $class; ?>">
                                                            <?php echo date('d/m/Y', strtotime($vehicle['insurance_expiry'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($vehicle['inspection_expiry']): ?>
                                                        <?php
                                                        $expiry = new DateTime($vehicle['inspection_expiry']);
                                                        $today = new DateTime();
                                                        $diff = $today->diff($expiry)->days;
                                                        $expired = $expiry < $today;
                                                        $class = $expired ? 'danger' : ($diff <= 30 ? 'warning' : 'success');
                                                        ?>
                                                        <span class="badge bg-<?php echo $class; ?>">
                                                            <?php echo date('d/m/Y', strtotime($vehicle['inspection_expiry'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'operativo' => 'success',
                                                        'in_manutenzione' => 'warning',
                                                        'fuori_servizio' => 'danger',
                                                        'dismesso' => 'secondary'
                                                    ];
                                                    $color = $statusColors[$vehicle['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $vehicle['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="vehicle_view.php?id=<?php echo $vehicle['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('vehicles', 'edit')): ?>
                                                            <a href="vehicle_edit.php?id=<?php echo $vehicle['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php
                        // Include pagination component
                        $showInfo = true;
                        include __DIR__ . '/../src/Views/includes/pagination.php';
                        ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Print list functionality
        function printListById(templateId) {
            let filters = getCurrentFilters();
            
            const params = new URLSearchParams({
                template_id: templateId,
                entity: 'vehicles',
                ...filters
            });
            window.open('print_preview.php?' + params.toString(), '_blank');
        }
        
        function printList(type) {
            // Legacy function for backward compatibility
            let templateId = null;
            let filters = getCurrentFilters();
            
            switch(type) {
                case 'elenco_mezzi':
                    templateId = 8; // Elenco Mezzi
                    break;
            }
            
            if (templateId) {
                printListById(templateId);
            }
        }
        
        function getCurrentFilters() {
            const filters = {};
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('type')) filters.vehicle_type = urlParams.get('type');
            if (urlParams.has('status')) filters.status = urlParams.get('status');
            if (urlParams.has('search')) filters.search = urlParams.get('search');
            
            return filters;
        }
        
        function showPrintListModal() {
            const modal = new bootstrap.Modal(document.getElementById('printListModal'));
            modal.show();
        }
        
        function generateListFromModal() {
            const templateId = document.getElementById('listTemplateSelect').value;
            if (templateId) {
                const filters = getCurrentFilters();
                const params = new URLSearchParams({
                    template_id: templateId,
                    entity: 'vehicles',
                    ...filters
                });
                window.open('print_preview.php?' + params.toString(), '_blank');
                const modal = bootstrap.Modal.getInstance(document.getElementById('printListModal'));
                modal.hide();
            }
        }
    </script>

    <!-- Print List Template Selection Modal -->
    <div class="modal fade" id="printListModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleziona Template Lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select id="listTemplateSelect" class="form-select">
                            <?php if (empty($printTemplates)): ?>
                                <option value="">Nessun template disponibile</option>
                            <?php else: ?>
                                <?php foreach ($printTemplates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>">
                                        <?php echo htmlspecialchars($template['name']); ?>
                                        <?php if (isset($template['template_format']) && $template['template_format'] === 'xml'): ?>
                                            [XML]
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <small><i class="bi bi-info-circle"></i> Verranno stampati i record secondo i filtri attualmente applicati</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="generateListFromModal()">
                        <i class="bi bi-printer"></i> Genera
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
