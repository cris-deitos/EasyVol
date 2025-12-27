<?php
/**
 * EasyCO - Dettaglio Mezzo (Read-Only)
 * 
 * Pagina di visualizzazione limitata per la Centrale Operativa
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login_co.php');
    exit;
}

// Verifica che sia utente CO
$user = $app->getCurrentUser();
if (!isset($user['is_operations_center_user']) || !$user['is_operations_center_user']) {
    die('Accesso negato - Solo per utenti EasyCO');
}

$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($vehicleId <= 0) {
    header('Location: operations_vehicles.php');
    exit;
}

$db = $app->getDb();

// Query per ottenere dati limitati del mezzo
$sql = "SELECT 
    v.id,
    v.name,
    v.license_plate,
    v.vehicle_type,
    v.status,
    v.brand,
    v.model,
    v.year,
    v.fuel_type,
    v.seats,
    v.chassis_number,
    v.engine_number,
    v.engine_power,
    v.weight,
    v.notes
FROM vehicles v
WHERE v.id = ?";

$vehicle = $db->fetchOne($sql, [$vehicleId]);

if (!$vehicle) {
    header('Location: operations_vehicles.php?error=not_found');
    exit;
}

// Log page access
AutoLogger::logPageAccess();

// Build page title from vehicle identifiers
$pageTitle = 'Mezzo';
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
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/easyco.css">
</head>
<body>
    <?php include '../src/Views/includes/navbar_operations.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar_operations.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <a href="operations_vehicles.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Visualizzazione limitata per la Centrale Operativa. 
                    Non è possibile modificare i dati da questa interfaccia.
                </div>
                
                <!-- Dati Principali -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-truck"></i> Dati Principali</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Targa/Matricola:</label>
                                <p>
                                    <?php if (!empty($vehicle['license_plate'])): ?>
                                        <span class="badge bg-secondary fs-6">
                                            <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                                        </span>
                                    <?php else: ?>
                                        N/D
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Tipo Veicolo:</label>
                                <p><?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Stato Operativo:</label>
                                <p>
                                    <?php
                                    $statusBadge = match($vehicle['status'] ?? '') {
                                        'operativo' => 'success',
                                        'manutenzione' => 'warning',
                                        'fuori_servizio' => 'danger',
                                        default => 'secondary'
                                    };
                                    $statusText = match($vehicle['status'] ?? '') {
                                        'operativo' => 'Operativo',
                                        'manutenzione' => 'In Manutenzione',
                                        'fuori_servizio' => 'Fuori Servizio',
                                        default => 'N/D'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $statusBadge; ?> fs-6">
                                        <?php echo $statusText; ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dati Tecnici -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Dati Tecnici</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Marca:</label>
                                <p><?php echo htmlspecialchars($vehicle['brand'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Modello:</label>
                                <p><?php echo htmlspecialchars($vehicle['model'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Anno:</label>
                                <p><?php echo htmlspecialchars($vehicle['year'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Alimentazione:</label>
                                <p>
                                    <?php 
                                    $fuelType = $vehicle['fuel_type'] ?? '';
                                    $fuelText = match($fuelType) {
                                        'benzina' => 'Benzina',
                                        'diesel' => 'Diesel',
                                        'gpl' => 'GPL',
                                        'metano' => 'Metano',
                                        'elettrica' => 'Elettrica',
                                        'ibrida' => 'Ibrida',
                                        default => 'N/D'
                                    };
                                    echo htmlspecialchars($fuelText);
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Posti:</label>
                                <p>
                                    <?php 
                                    echo !empty($vehicle['seats']) 
                                        ? htmlspecialchars($vehicle['seats']) . ' posti' 
                                        : 'N/D'; 
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Peso:</label>
                                <p>
                                    <?php 
                                    echo !empty($vehicle['weight']) 
                                        ? htmlspecialchars($vehicle['weight']) . ' kg' 
                                        : 'N/D'; 
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Numero Telaio:</label>
                                <p><?php echo htmlspecialchars($vehicle['chassis_number'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Numero Motore:</label>
                                <p><?php echo htmlspecialchars($vehicle['engine_number'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Potenza Motore:</label>
                                <p>
                                    <?php 
                                    echo !empty($vehicle['engine_power']) 
                                        ? htmlspecialchars($vehicle['engine_power']) . ' kW' 
                                        : 'N/D'; 
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Note -->
                <?php if (!empty($vehicle['notes'])): ?>
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-file-text"></i> Note</h5>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($vehicle['notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <a href="operations_vehicles.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla Lista
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
