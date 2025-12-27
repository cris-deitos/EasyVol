<?php
/**
 * Report e Statistiche
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ReportController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('reports', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ReportController($db, $config);

// Ottieni statistiche dashboard
$dashboardStats = $controller->getDashboardStats();

// Report soci
$membersByStatus = $controller->membersByStatus();
$membersByQualification = $controller->membersByQualification();

// Report eventi (ultimi 12 mesi)
$startDate = date('Y-m-d', strtotime('-12 months'));
$eventsByType = $controller->eventsByType($startDate);

// Report mezzi
$vehiclesByType = $controller->vehiclesByType();
$vehicleExpirations = $controller->vehicleExpirations(60); // prossimi 60 giorni

// Report magazzino
$warehouseStock = $controller->warehouseStock();
$lowStockItems = $controller->lowStockItems();

// Report documenti
$documentsByCategory = $controller->documentsByCategory();

$pageTitle = 'Report e Statistiche';
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
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Stampa
                        </button>
                    </div>
                </div>
                
                <!-- KPI Dashboard -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Soci Attivi</h6>
                                <h2><?php echo number_format($dashboardStats['members']['active'] ?? 0); ?></h2>
                                <small>su <?php echo number_format($dashboardStats['members']['total'] ?? 0); ?> totali</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Eventi Aperti</h6>
                                <h2><?php echo number_format($dashboardStats['events']['open'] ?? 0); ?></h2>
                                <small>su <?php echo number_format($dashboardStats['events']['total'] ?? 0); ?> totali</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Mezzi Operativi</h6>
                                <h2><?php echo number_format($dashboardStats['vehicles']['operational'] ?? 0); ?></h2>
                                <small>su <?php echo number_format($dashboardStats['vehicles']['total'] ?? 0); ?> totali</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Corsi Attivi</h6>
                                <h2><?php echo number_format($dashboardStats['training']['active'] ?? 0); ?></h2>
                                <small>su <?php echo number_format($dashboardStats['training']['total'] ?? 0); ?> totali</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs per Report -->
                <ul class="nav nav-tabs mb-3" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button">
                            <i class="bi bi-people"></i> Soci
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button">
                            <i class="bi bi-calendar-event"></i> Eventi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles" type="button">
                            <i class="bi bi-truck"></i> Mezzi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="warehouse-tab" data-bs-toggle="tab" data-bs-target="#warehouse" type="button">
                            <i class="bi bi-box"></i> Magazzino
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">
                            <i class="bi bi-file-earmark"></i> Documenti
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="reportTabsContent">
                    <!-- Tab Soci -->
                    <div class="tab-pane fade show active" id="members" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Soci per Stato</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Stato</th>
                                                    <th class="text-end">Numero</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($membersByStatus as $item): ?>
                                                    <tr>
                                                        <td><?php echo ucfirst(htmlspecialchars($item['status'])); ?></td>
                                                        <td class="text-end"><?php echo number_format($item['count']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Soci per Qualifica</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Qualifica</th>
                                                    <th class="text-end">Numero</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($membersByQualification as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['qualification'] ?? 'Non specificato'); ?></td>
                                                        <td class="text-end"><?php echo number_format($item['count']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Eventi -->
                    <div class="tab-pane fade" id="events" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Eventi per Tipo (ultimi 12 mesi)</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tipo Evento</th>
                                            <th class="text-end">Numero Eventi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($eventsByType)): ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">Nessun evento nel periodo</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($eventsByType as $item): ?>
                                                <tr>
                                                    <td><?php echo ucfirst(htmlspecialchars($item['event_type'])); ?></td>
                                                    <td class="text-end"><?php echo number_format($item['count']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Mezzi -->
                    <div class="tab-pane fade" id="vehicles" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Mezzi per Tipo</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th class="text-end">Numero</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($vehiclesByType)): ?>
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted">Nessun mezzo</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($vehiclesByType as $item): ?>
                                                        <tr>
                                                            <td><?php echo ucfirst(htmlspecialchars($item['vehicle_type'])); ?></td>
                                                            <td class="text-end"><?php echo number_format($item['count']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            Scadenze Prossime (60 giorni)
                                            <?php if (!empty($vehicleExpirations)): ?>
                                                <span class="badge bg-danger"><?php echo count($vehicleExpirations); ?></span>
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($vehicleExpirations)): ?>
                                            <p class="text-muted">Nessuna scadenza nei prossimi 60 giorni</p>
                                        <?php else: ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($vehicleExpirations as $vehicle): ?>
                                                    <div class="list-group-item px-0">
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
                                                        <small class="text-muted"><?php echo htmlspecialchars($vehicle['vehicle_type'] ?? ''); ?></small>
                                                        <br>
                                                        <?php if ($vehicle['insurance_days'] !== null && $vehicle['insurance_days'] <= 60): ?>
                                                            <small class="text-danger">
                                                                <i class="bi bi-exclamation-triangle"></i> 
                                                                Assicurazione: <?php echo $vehicle['insurance_days']; ?> giorni
                                                            </small><br>
                                                        <?php endif; ?>
                                                        <?php if ($vehicle['inspection_days'] !== null && $vehicle['inspection_days'] <= 60): ?>
                                                            <small class="text-warning">
                                                                <i class="bi bi-exclamation-circle"></i> 
                                                                Revisione: <?php echo $vehicle['inspection_days']; ?> giorni
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Magazzino -->
                    <div class="tab-pane fade" id="warehouse" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Giacenze per Categoria</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Categoria</th>
                                                    <th class="text-end">Articoli</th>
                                                    <th class="text-end">Scorta Bassa</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($warehouseStock)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">Nessun articolo</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($warehouseStock as $item): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                            <td class="text-end"><?php echo number_format($item['items']); ?></td>
                                                            <td class="text-end">
                                                                <?php if ($item['low_stock_items'] > 0): ?>
                                                                    <span class="badge bg-danger"><?php echo $item['low_stock_items']; ?></span>
                                                                <?php else: ?>
                                                                    -
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            Articoli Sotto Scorta
                                            <?php if (!empty($lowStockItems)): ?>
                                                <span class="badge bg-danger"><?php echo count($lowStockItems); ?></span>
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($lowStockItems)): ?>
                                            <p class="text-muted">Nessun articolo sotto scorta minima</p>
                                        <?php else: ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach (array_slice($lowStockItems, 0, 10) as $item): ?>
                                                    <div class="list-group-item px-0">
                                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            Disponibile: <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?>
                                                            | Minimo: <?php echo $item['minimum_quantity']; ?>
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Documenti -->
                    <div class="tab-pane fade" id="documents" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Documenti per Categoria</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Categoria</th>
                                            <th class="text-end">Numero Documenti</th>
                                            <th class="text-end">Spazio Occupato</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($documentsByCategory)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Nessun documento</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($documentsByCategory as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                    <td class="text-end"><?php echo number_format($item['count']); ?></td>
                                                    <td class="text-end">
                                                        <?php 
                                                        if ($item['total_size']) {
                                                            echo round($item['total_size'] / 1024 / 1024, 2) . ' MB';
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
