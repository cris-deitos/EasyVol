<?php
/**
 * Public Vehicle Movement Management
 * 
 * Main page for managing vehicle movements (departures and returns)
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\VehicleMovementController;
use EasyVol\Controllers\VehicleController;

$app = App::getInstance();
$db = $app->getDb();
$config = $app->getConfig();

// Check if member is authenticated
if (!isset($_SESSION['vehicle_movement_member'])) {
    header('Location: vehicle_movement_login.php');
    exit;
}

$member = $_SESSION['vehicle_movement_member'];
$controller = new VehicleMovementController($db, $config);
$vehicleController = new VehicleController($db, $config);

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['vehicle_movement_member']);
    header('Location: vehicle_movement_login.php');
    exit;
}

// Get vehicle list
$filters = [
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$vehicles = $controller->getVehicleList($filters);

$pageTitle = 'Movimentazione Mezzi';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .vehicle-card {
            transition: all 0.3s;
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .vehicle-card.operativo {
            border-left-color: #28a745;
        }
        .vehicle-card.in_manutenzione {
            border-left-color: #ffc107;
        }
        .vehicle-card.fuori_servizio {
            border-left-color: #dc3545;
        }
        .badge-in-mission {
            background-color: #17a2b8;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
        }
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-truck"></i> Movimentazione Mezzi
            </span>
            <div class="d-flex align-items-center text-white">
                <span class="me-3">
                    <i class="bi bi-person-circle"></i>
                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                    <small class="ms-1">(<?php echo htmlspecialchars($member['registration_number']); ?>)</small>
                </span>
                <a href="?logout=1" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Esci
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><i class="bi bi-filter"></i> Tipo Mezzo</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tutti i tipi</option>
                        <option value="veicolo" <?php echo $filters['type'] === 'veicolo' ? 'selected' : ''; ?>>Veicolo</option>
                        <option value="natante" <?php echo $filters['type'] === 'natante' ? 'selected' : ''; ?>>Natante</option>
                        <option value="rimorchio" <?php echo $filters['type'] === 'rimorchio' ? 'selected' : ''; ?>>Rimorchio</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="bi bi-filter"></i> Stato</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Tutti gli stati</option>
                        <option value="operativo" <?php echo $filters['status'] === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                        <option value="in_manutenzione" <?php echo $filters['status'] === 'in_manutenzione' ? 'selected' : ''; ?>>In Manutenzione</option>
                        <option value="fuori_servizio" <?php echo $filters['status'] === 'fuori_servizio' ? 'selected' : ''; ?>>Fuori Servizio</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <?php if (!empty($filters['type']) || !empty($filters['status'])): ?>
                        <a href="vehicle_movement.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Rimuovi Filtri
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Vehicles List -->
        <div class="row g-4">
            <?php if (empty($vehicles)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Nessun mezzo trovato.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($vehicles as $vehicle): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card vehicle-card <?php echo $vehicle['status']; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-<?php 
                                            echo $vehicle['vehicle_type'] === 'veicolo' ? 'truck' : 
                                                ($vehicle['vehicle_type'] === 'natante' ? 'water' : 'box-seam'); 
                                        ?>"></i>
                                        <?php 
                                        $identifier = $vehicle['license_plate'] ?: $vehicle['serial_number'];
                                        echo htmlspecialchars($identifier);
                                        ?>
                                    </h5>
                                    <?php if ($vehicle['in_mission']): ?>
                                        <span class="badge badge-in-mission">
                                            <i class="bi bi-geo-alt-fill"></i> IN MISSIONE
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="card-text">
                                    <strong>Tipo:</strong> <?php echo ucfirst($vehicle['vehicle_type']); ?><br>
                                    <strong>Marca/Modello:</strong> <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?><br>
                                    <strong>Stato:</strong> 
                                    <?php
                                    $statusClass = match($vehicle['status']) {
                                        'operativo' => 'success',
                                        'in_manutenzione' => 'warning',
                                        'fuori_servizio' => 'danger',
                                        default => 'secondary'
                                    };
                                    $statusText = match($vehicle['status']) {
                                        'operativo' => 'Operativo',
                                        'in_manutenzione' => 'In Manutenzione',
                                        'fuori_servizio' => 'Fuori Servizio',
                                        default => ucfirst($vehicle['status'])
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?> status-badge">
                                        <?php echo $statusText; ?>
                                    </span>
                                </p>
                                
                                <?php if ($vehicle['license_type']): ?>
                                    <p class="card-text text-muted small">
                                        <i class="bi bi-card-heading"></i>
                                        <strong>Patente richiesta:</strong> <?php echo htmlspecialchars($vehicle['license_type']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <a href="vehicle_movement_detail.php?id=<?php echo $vehicle['id']; ?>" 
                                       class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-gear-fill"></i> Gestisci
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
