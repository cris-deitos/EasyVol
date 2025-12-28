<?php
/**
 * Internal Vehicle Movements Management
 * 
 * Page for viewing and managing vehicle movement history (internal use)
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\VehicleMovementController;

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
$controller = new VehicleMovementController($db, $config);

$filters = [
    'vehicle_id' => $_GET['vehicle_id'] ?? '',
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$movements = $controller->getMovementHistory($filters, $page, $perPage);

// Get vehicles list for filter
$vehiclesSql = "SELECT id, license_plate, serial_number, brand, model 
                FROM vehicles 
                WHERE status != 'dismesso' 
                ORDER BY license_plate, serial_number";
$vehicles = $db->fetchAll($vehiclesSql);

$pageTitle = 'Gestione Movimentazione Mezzi';
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
                        <i class="bi bi-shuffle"></i> <?php echo $pageTitle; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="vehicles.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Torna ai Mezzi
                        </a>
                        <?php if ($app->checkPermission('vehicles', 'create')): ?>
                            <a href="vehicle_movement_internal_departure.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Uscita
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php
                        $successMsg = match($_GET['success']) {
                            'departure' => 'Uscita veicolo registrata con successo',
                            'return' => 'Rientro veicolo registrato con successo',
                            'completed' => 'Viaggio completato senza rientro',
                            default => 'Operazione completata con successo'
                        };
                        echo $successMsg;
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Veicolo</label>
                                <select name="vehicle_id" class="form-select form-select-sm">
                                    <option value="">Tutti i veicoli</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?php echo $v['id']; ?>" 
                                                <?php echo $filters['vehicle_id'] == $v['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(($v['license_plate'] ?: $v['serial_number']) . ' - ' . $v['brand'] . ' ' . $v['model']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Stato</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">Tutti gli stati</option>
                                    <option value="in_mission" <?php echo $filters['status'] === 'in_mission' ? 'selected' : ''; ?>>
                                        In Missione
                                    </option>
                                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>
                                        Completato
                                    </option>
                                    <option value="completed_no_return" <?php echo $filters['status'] === 'completed_no_return' ? 'selected' : ''; ?>>
                                        Completato senza rientro
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Data Da</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Data A</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-search"></i> Filtra
                                </button>
                                <?php if (array_filter($filters)): ?>
                                    <a href="vehicle_movements.php" class="btn btn-secondary btn-sm">
                                        <i class="bi bi-x-circle"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Movements Table -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($movements)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Nessun movimento trovato.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Veicolo</th>
                                            <th>Partenza</th>
                                            <th>Rientro</th>
                                            <th>Durata</th>
                                            <th>Km</th>
                                            <th>Autisti</th>
                                            <th>Stato</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($movements as $movement): ?>
                                             <tr>
                                                <td><?php echo $movement['id']; ?></td>
                                                <td>
                                                    <i class="bi bi-<?php 
                                                        echo $movement['vehicle_type'] === 'veicolo' ? 'truck' : 
                                                            ($movement['vehicle_type'] === 'natante' ? 'water' : 'box-seam'); 
                                                    ?>"></i>
                                                    <strong><?php echo htmlspecialchars($movement['license_plate']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($movement['brand'] . ' ' . $movement['model']); ?>
                                                    </small>
                                                    <?php if (!empty($movement['trailer_name'])): ?>
                                                        <br>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-box-seam"></i> Rimorchio: 
                                                            <?php echo htmlspecialchars($movement['trailer_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($movement['departure_datetime'])); ?>
                                                    <?php if ($movement['departure_km']): ?>
                                                        <br><small class="text-muted">
                                                            Km: <?php echo number_format($movement['departure_km'], 2); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($movement['return_datetime']): ?>
                                                        <?php echo date('d/m/Y H:i', strtotime($movement['return_datetime'])); ?>
                                                        <?php if ($movement['return_km']): ?>
                                                            <br><small class="text-muted">
                                                                Km: <?php echo number_format($movement['return_km'], 2); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($movement['trip_duration_minutes']): ?>
                                                        <?php
                                                        $hours = floor($movement['trip_duration_minutes'] / 60);
                                                        $minutes = $movement['trip_duration_minutes'] % 60;
                                                        echo "{$hours}h {$minutes}m";
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($movement['trip_km']): ?>
                                                        <?php echo number_format($movement['trip_km'], 2); ?> km
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Partenza:</strong><br>
                                                        <?php echo htmlspecialchars($movement['departure_drivers'] ?: 'N/A'); ?>
                                                        <?php if ($movement['return_drivers']): ?>
                                                            <br><strong>Rientro:</strong><br>
                                                            <?php echo htmlspecialchars($movement['return_drivers']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = match($movement['status']) {
                                                        'in_mission' => 'info',
                                                        'completed' => 'success',
                                                        'completed_no_return' => 'secondary',
                                                        default => 'secondary'
                                                    };
                                                    $statusText = match($movement['status']) {
                                                        'in_mission' => 'In Missione',
                                                        'completed' => 'Completato',
                                                        'completed_no_return' => 'Completato (no rientro)',
                                                        default => ucfirst($movement['status'])
                                                    };
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                    
                                                    <?php if ($movement['departure_anomaly_flag']): ?>
                                                        <br><span class="badge bg-warning mt-1" title="Anomalia segnalata in partenza">
                                                            <i class="bi bi-exclamation-triangle"></i> Anomalia P
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($movement['return_anomaly_flag']): ?>
                                                        <br><span class="badge bg-warning mt-1" title="Anomalia segnalata al rientro">
                                                            <i class="bi bi-exclamation-triangle"></i> Anomalia R
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($movement['traffic_violation_flag']): ?>
                                                        <br><span class="badge bg-danger mt-1" title="Ipotesi sanzioni">
                                                            <i class="bi bi-exclamation-octagon"></i> Sanzioni
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="vehicle_movement_view.php?id=<?php echo $movement['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary"
                                                       title="Visualizza dettagli">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($movement['status'] === 'in_mission' && $app->checkPermission('vehicles', 'edit')): ?>
                                                        <a href="vehicle_movement_internal_return.php?movement_id=<?php echo $movement['id']; ?>" 
                                                           class="btn btn-sm btn-success"
                                                           title="Registra rientro">
                                                            <i class="bi bi-box-arrow-in-left"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-secondary" 
                                                                onclick="completeWithoutReturn(<?php echo $movement['id']; ?>)"
                                                                title="Completa senza rientro">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function completeWithoutReturn(movementId) {
            if (!confirm('Sei sicuro di voler completare questo viaggio senza registrare i dati di rientro?')) {
                return;
            }
            
            fetch('vehicle_movement_internal_api.php?action=complete_without_return&movement_id=' + encodeURIComponent(movementId), {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Errore: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore durante l\'operazione');
            });
        }
    </script>
</body>
</html>
