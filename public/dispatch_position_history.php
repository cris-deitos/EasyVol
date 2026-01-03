<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\DispatchController;
use EasyVol\Controllers\OperationsCenterController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

$dispatchController = new DispatchController($app->getDb(), $app->getConfig());
$opsController = new OperationsCenterController($app->getDb(), $app->getConfig());

// Get filters
$filters = [];
if (!empty($_GET['radio_dmr_id'])) {
    $filters['radio_dmr_id'] = $_GET['radio_dmr_id'];
}
if (!empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$positions = $dispatchController->getPositionHistory($filters, $page, 100);

// Get all radios for filter dropdown
$radios = $opsController->indexRadios([], 1, 1000);

$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
$pageTitle = 'Storico Posizioni Radio';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo $isCoUser ? 'EasyCO' : 'EasyVol'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <?php if ($isCoUser): ?>
        <link rel="stylesheet" href="../assets/css/easyco.css">
    <?php endif; ?>
    <style>
        #historyMap { height: 500px; border-radius: 8px; }
    </style>
</head>
<body>
    <?php 
    if ($isCoUser) {
        include '../src/Views/includes/navbar_operations.php';
    } else {
        include '../src/Views/includes/navbar.php';
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            if ($isCoUser) {
                include '../src/Views/includes/sidebar_operations.php';
            } else {
                include '../src/Views/includes/sidebar.php';
            }
            ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dispatch.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna al Dispatch
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtri</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Radio</label>
                                <select class="form-select" name="radio_dmr_id">
                                    <option value="">Tutte</option>
                                    <?php foreach ($radios as $radio): ?>
                                        <?php if (!empty($radio['dmr_id'])): ?>
                                            <option value="<?php echo htmlspecialchars($radio['dmr_id']); ?>"
                                                    <?php echo (isset($_GET['radio_dmr_id']) && $_GET['radio_dmr_id'] === $radio['dmr_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($radio['name'] . ' (' . $radio['dmr_id'] . ')'); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Inizio</label>
                                <input type="datetime-local" class="form-control" name="start_date" 
                                       value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Fine</label>
                                <input type="datetime-local" class="form-control" name="end_date" 
                                       value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                    <a href="dispatch_position_history.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Map -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Mappa Posizioni</h5>
                    </div>
                    <div class="card-body">
                        <div id="historyMap"></div>
                    </div>
                </div>

                <!-- Position List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lista Posizioni (<?php echo count($positions); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data/Ora</th>
                                        <th>Radio</th>
                                        <th>DMR ID</th>
                                        <th>Latitudine</th>
                                        <th>Longitudine</th>
                                        <th>Velocità</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($positions)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Nessuna posizione trovata</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($positions as $pos): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pos['timestamp']); ?></td>
                                                <td><?php echo htmlspecialchars($pos['radio_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($pos['radio_dmr_id']); ?></td>
                                                <td><?php echo htmlspecialchars($pos['latitude']); ?></td>
                                                <td><?php echo htmlspecialchars($pos['longitude']); ?></td>
                                                <td><?php echo $pos['speed'] ? htmlspecialchars($pos['speed']) . ' km/h' : 'N/A'; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick="showOnMap(<?php echo $pos['latitude']; ?>, <?php echo $pos['longitude']; ?>)">
                                                        <i class="bi bi-geo-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('historyMap').setView([45.4642, 9.1900], 10);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        const positions = <?php echo json_encode($positions); ?>;
        const markers = [];
        const polylinePoints = [];
        
        // Add markers and collect points for polyline
        positions.forEach((pos, index) => {
            const lat = parseFloat(pos.latitude);
            const lon = parseFloat(pos.longitude);
            
            const marker = L.marker([lat, lon]).addTo(map);
            marker.bindPopup(`
                <strong>${pos.radio_name || 'N/A'}</strong><br>
                DMR ID: ${pos.radio_dmr_id}<br>
                Time: ${pos.timestamp}<br>
                ${pos.speed ? 'Speed: ' + pos.speed + ' km/h' : ''}
            `);
            
            markers.push(marker);
            polylinePoints.push([lat, lon]);
        });
        
        // Draw path if multiple positions
        if (polylinePoints.length > 1) {
            const polyline = L.polyline(polylinePoints, {
                color: 'blue',
                weight: 3,
                opacity: 0.5
            }).addTo(map);
            
            map.fitBounds(polyline.getBounds());
        } else if (polylinePoints.length === 1) {
            map.setView(polylinePoints[0], 15);
        }
        
        function showOnMap(lat, lon) {
            map.setView([lat, lon], 15);
            
            // Find and open popup for this position
            markers.forEach(marker => {
                const markerLatLng = marker.getLatLng();
                if (markerLatLng.lat === lat && markerLatLng.lng === lon) {
                    marker.openPopup();
                }
            });
        }
    </script>
</body>
</html>
