<?php
/**
 * Vehicle Movement View
 * 
 * View details of a specific vehicle movement
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\VehicleMovementController;
use EasyVol\Controllers\VehicleController;

$app = App::getInstance();

// Check authentication
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permissions
if (!$app->checkPermission('vehicles', 'view')) {
    die('Accesso negato');
}

$movementId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($movementId <= 0) {
    header('Location: vehicle_movements.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new VehicleMovementController($db, $config);
$vehicleController = new VehicleController($db, $config);

// Get movement details
$sql = "SELECT vm.*, 
        v.license_plate, v.serial_number, v.brand, v.model, v.vehicle_type,
        t.name as trailer_name, t.license_plate as trailer_plate
        FROM vehicle_movements vm
        LEFT JOIN vehicles v ON vm.vehicle_id = v.id
        LEFT JOIN vehicles t ON vm.trailer_id = t.id
        WHERE vm.id = ?";
$movement = $db->fetchOne($sql, [$movementId]);

if (!$movement) {
    header('Location: vehicle_movements.php?error=not_found');
    exit;
}

// Get departure drivers
$departureSql = "SELECT m.first_name, m.last_name, m.registration_number
                 FROM vehicle_movement_drivers vmd
                 LEFT JOIN members m ON vmd.member_id = m.id
                 WHERE vmd.movement_id = ? AND vmd.driver_type = 'departure'
                 ORDER BY m.last_name, m.first_name";
$departureDrivers = $db->fetchAll($departureSql, [$movementId]);

// Get return drivers
$returnSql = "SELECT m.first_name, m.last_name, m.registration_number
              FROM vehicle_movement_drivers vmd
              LEFT JOIN members m ON vmd.member_id = m.id
              WHERE vmd.movement_id = ? AND vmd.driver_type = 'return'
              ORDER BY m.last_name, m.first_name";
$returnDrivers = $db->fetchAll($returnSql, [$movementId]);

$pageTitle = 'Dettaglio Movimento Mezzo';
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
                    <h1 class="h2"><i class="bi bi-eye"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="vehicle_movements.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna alla Lista
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Vehicle Info -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-truck"></i> Informazioni Veicolo</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <th width="40%">Targa/Matricola:</th>
                                        <td>
                                            <strong><?php echo htmlspecialchars($movement['license_plate'] ?: $movement['serial_number']); ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Marca/Modello:</th>
                                        <td><?php echo htmlspecialchars($movement['brand'] . ' ' . $movement['model']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tipo:</th>
                                        <td><?php echo htmlspecialchars($movement['vehicle_type']); ?></td>
                                    </tr>
                                    <?php if ($movement['trailer_id']): ?>
                                        <tr>
                                            <th>Rimorchio:</th>
                                            <td>
                                                <?php echo htmlspecialchars($movement['trailer_name']); ?>
                                                <?php if ($movement['trailer_plate']): ?>
                                                    (<?php echo htmlspecialchars($movement['trailer_plate']); ?>)
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Movement Status -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Stato Movimento</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <th width="40%">Stato:</th>
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
                                                'completed_no_return' => 'Completato (senza rientro)',
                                                default => ucfirst($movement['status'])
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($movement['trip_duration_minutes']): ?>
                                        <tr>
                                            <th>Durata Viaggio:</th>
                                            <td>
                                                <?php
                                                $hours = floor($movement['trip_duration_minutes'] / 60);
                                                $minutes = $movement['trip_duration_minutes'] % 60;
                                                echo "{$hours}h {$minutes}m";
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($movement['trip_km']): ?>
                                        <tr>
                                            <th>Km Percorsi:</th>
                                            <td><strong><?php echo number_format($movement['trip_km'], 2); ?> km</strong></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Departure Information -->
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-arrow-right-circle"></i> Informazioni Partenza</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Data e Ora:</th>
                                        <td><?php echo date('d/m/Y H:i', strtotime($movement['departure_datetime'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Km Partenza:</th>
                                        <td><?php echo number_format($movement['departure_km'], 2); ?> km</td>
                                    </tr>
                                    <?php if ($movement['departure_fuel_level']): ?>
                                        <tr>
                                            <th>Carburante:</th>
                                            <td><?php echo htmlspecialchars($movement['departure_fuel_level']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Autisti:</th>
                                        <td>
                                            <?php if (!empty($departureDrivers)): ?>
                                                <ul class="mb-0 ps-3">
                                                    <?php foreach ($departureDrivers as $driver): ?>
                                                        <li>
                                                            <?php echo htmlspecialchars($driver['last_name'] . ' ' . $driver['first_name']); ?>
                                                            <?php if ($driver['registration_number']): ?>
                                                                <small class="text-muted">(<?php echo htmlspecialchars($driver['registration_number']); ?>)</small>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted">Nessun autista registrato</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <?php if ($movement['departure_anomaly_flag']): ?>
                                    <div class="alert alert-warning mb-3">
                                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Anomalie Segnalate in Partenza</h6>
                                        <?php if ($movement['departure_anomaly_notes']): ?>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($movement['departure_anomaly_notes'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($movement['departure_notes']): ?>
                                    <h6>Note Partenza:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($movement['departure_notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Return Information -->
                <?php if ($movement['return_datetime'] || $movement['status'] === 'completed_no_return'): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-arrow-left-circle"></i> Informazioni Rientro</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($movement['status'] === 'completed_no_return'): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Viaggio completato senza registrazione dati di rientro
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Data e Ora:</th>
                                                <td><?php echo date('d/m/Y H:i', strtotime($movement['return_datetime'])); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Km Rientro:</th>
                                                <td><?php echo number_format($movement['return_km'], 2); ?> km</td>
                                            </tr>
                                            <?php if ($movement['return_fuel_level']): ?>
                                                <tr>
                                                    <th>Carburante:</th>
                                                    <td><?php echo htmlspecialchars($movement['return_fuel_level']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Autisti:</th>
                                                <td>
                                                    <?php if (!empty($returnDrivers)): ?>
                                                        <ul class="mb-0 ps-3">
                                                            <?php foreach ($returnDrivers as $driver): ?>
                                                                <li>
                                                                    <?php echo htmlspecialchars($driver['last_name'] . ' ' . $driver['first_name']); ?>
                                                                    <?php if ($driver['registration_number']): ?>
                                                                        <small class="text-muted">(<?php echo htmlspecialchars($driver['registration_number']); ?>)</small>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="text-muted">Nessun autista registrato</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($movement['return_anomaly_flag']): ?>
                                            <div class="alert alert-warning mb-3">
                                                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Anomalie Segnalate al Rientro</h6>
                                                <?php if ($movement['return_anomaly_notes']): ?>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($movement['return_anomaly_notes'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($movement['traffic_violation_flag']): ?>
                                            <div class="alert alert-danger mb-3">
                                                <h6 class="alert-heading"><i class="bi bi-exclamation-octagon"></i> Ipotesi di Sanzioni</h6>
                                                <?php if ($movement['traffic_violation_notes']): ?>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($movement['traffic_violation_notes'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($movement['return_notes']): ?>
                                            <h6>Note Rientro:</h6>
                                            <p><?php echo nl2br(htmlspecialchars($movement['return_notes'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <?php if ($movement['status'] === 'in_mission' && $app->checkPermission('vehicles', 'edit')): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-gear"></i> Azioni</h5>
                        </div>
                        <div class="card-body">
                            <p>Il veicolo è ancora in missione. È possibile completare il movimento:</p>
                            <div class="btn-group">
                                <a href="vehicle_movement_internal_return.php?movement_id=<?php echo $movement['id']; ?>" 
                                   class="btn btn-success">
                                    <i class="bi bi-box-arrow-in-left"></i> Registra Rientro
                                </a>
                                <button type="button" 
                                        class="btn btn-secondary" 
                                        onclick="completeWithoutReturn(<?php echo $movement['id']; ?>)">
                                    <i class="bi bi-check-circle"></i> Completa Senza Rientro
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
