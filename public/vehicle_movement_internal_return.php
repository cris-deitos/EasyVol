<?php
/**
 * Internal Vehicle Movement - Return Form (Administrative)
 * 
 * Form for registering vehicle return from admin panel
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\VehicleMovementController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('vehicles', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();

$movementId = isset($_GET['movement_id']) ? intval($_GET['movement_id']) : 0;

if ($movementId <= 0) {
    header('Location: vehicle_movements.php?error=invalid_movement');
    exit;
}

$controller = new VehicleMovementController($db, $config);

// Get movement details
$movement = $controller->getMovement($movementId);

if (!$movement) {
    header('Location: vehicle_movements.php?error=not_found');
    exit;
}

// Check if already returned
if ($movement['status'] !== 'in_mission') {
    header('Location: vehicle_movements.php?error=already_returned');
    exit;
}

// Get active members for drivers
$membersSql = "SELECT id, first_name, last_name, registration_number 
               FROM members 
               WHERE status = 'attivo' 
               ORDER BY last_name, first_name";
$members = $db->fetchAll($membersSql);

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        try {
            // Validate drivers
            if (empty($_POST['drivers']) || !is_array($_POST['drivers'])) {
                throw new \Exception('Selezionare almeno un autista');
            }
            
            $returnData = [
                'return_datetime' => $_POST['return_datetime'],
                'drivers' => array_map('intval', $_POST['drivers']),
                'return_km' => !empty($_POST['return_km']) ? floatval($_POST['return_km']) : null,
                'return_fuel_level' => (!empty($_POST['return_fuel_level']) && trim($_POST['return_fuel_level']) !== '') ? $_POST['return_fuel_level'] : null,
                'return_notes' => $_POST['return_notes'] ?? null,
                'return_anomaly_flag' => isset($_POST['return_anomaly_flag']) ? 1 : 0,
                'traffic_violation_flag' => isset($_POST['traffic_violation_flag']) ? 1 : 0,
                'checklist' => [] // Empty for now, can be enhanced later
            ];
            
            $result = $controller->createReturn($movementId, $returnData, $app->getUserId());
            
            if ($result['success']) {
                header('Location: vehicle_movements.php?success=return');
                exit;
            } else {
                $errors[] = $result['message'] ?? 'Errore durante la registrazione';
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Registra Rientro Veicolo';
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
                        <a href="vehicle_movements.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <h5>Dettagli Missione</h5>
                    <strong>Veicolo:</strong> 
                    <?php echo htmlspecialchars($movement['license_plate'] ?: $movement['serial_number']); ?> -
                    <?php echo htmlspecialchars($movement['brand'] . ' ' . $movement['model']); ?>
                    <br>
                    <strong>Partenza:</strong> 
                    <?php echo date('d/m/Y H:i', strtotime($movement['departure_datetime'])); ?>
                    <?php if ($movement['departure_km']): ?>
                        - Km: <?php echo number_format($movement['departure_km'], 2); ?>
                    <?php endif; ?>
                    <?php if (!empty($movement['departure_drivers'])): ?>
                        <br>
                        <strong>Autisti partenza:</strong> <?php echo htmlspecialchars($movement['departure_drivers']); ?>
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-box-arrow-in-left"></i> Dati Rientro</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="return_datetime" class="form-label">Data e Ora Rientro <span class="text-danger">*</span></label>
                                    <input type="datetime-local" 
                                           class="form-control" 
                                           id="return_datetime" 
                                           name="return_datetime" 
                                           value="<?php echo date('Y-m-d\TH:i'); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Autisti al Rientro <span class="text-danger">*</span></label>
                                    <select class="form-select" name="drivers[]" multiple size="8" required>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?php echo $member['id']; ?>">
                                                <?php echo htmlspecialchars($member['last_name'] . ' ' . $member['first_name'] . ' (' . $member['registration_number'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Tenere premuto Ctrl/Cmd per selezionare più autisti</small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <?php if ($movement['vehicle_type'] !== 'natante'): ?>
                                <div class="col-md-6">
                                    <label for="return_km" class="form-label">Km Rientro</label>
                                    <input type="number" 
                                           step="0.01" 
                                           class="form-control" 
                                           id="return_km" 
                                           name="return_km"
                                           <?php if ($movement['departure_km']): ?>
                                               min="<?php echo $movement['departure_km']; ?>"
                                           <?php endif; ?>>
                                    <?php if ($movement['departure_km']): ?>
                                        <small class="form-text text-muted">
                                            Km minimo: <?php echo number_format($movement['departure_km'], 2); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="col-md-6">
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        I natanti non richiedono la registrazione dei chilometri.
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6">
                                    <label for="return_fuel_level" class="form-label">Livello Carburante al Rientro</label>
                                    <select class="form-select" id="return_fuel_level" name="return_fuel_level">
                                        <option value="">Non specificato</option>
                                        <option value="pieno">Pieno</option>
                                        <option value="3/4">3/4</option>
                                        <option value="1/2">1/2</option>
                                        <option value="1/4">1/4</option>
                                        <option value="riserva">Riserva</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="return_notes" class="form-label">Note Rientro</label>
                                <textarea class="form-control" id="return_notes" name="return_notes" rows="3"></textarea>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="return_anomaly_flag" name="return_anomaly_flag">
                                <label class="form-check-label text-warning" for="return_anomaly_flag">
                                    <i class="bi bi-exclamation-triangle"></i> Segnala anomalia al rientro
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="traffic_violation_flag" name="traffic_violation_flag">
                                <label class="form-check-label text-danger" for="traffic_violation_flag">
                                    <i class="bi bi-exclamation-octagon"></i> Segnala ipotesi sanzioni/infrazioni
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="vehicle_movements.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annulla
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Registra Rientro
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
