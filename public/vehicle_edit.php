<?php
/**
 * Gestione Mezzi - Modifica/Crea
 * 
 * Pagina per creare o modificare un mezzo
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

$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $vehicleId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('vehicles', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('vehicles', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new VehicleController($db, $config);

$vehicle = [];
$errors = [];
$success = false;

// Se in modifica, carica dati esistenti
if ($isEdit) {
    $vehicle = $controller->get($vehicleId);
    if (!$vehicle) {
        header('Location: vehicles.php?error=not_found');
        exit;
    }
}

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'vehicle_type' => $_POST['vehicle_type'] ?? 'veicolo',
            'name' => trim($_POST['name'] ?? ''),
            'license_plate' => trim($_POST['license_plate'] ?? ''),
            'brand' => trim($_POST['brand'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'year' => $_POST['year'] ?? null,
            'serial_number' => trim($_POST['serial_number'] ?? ''),
            'status' => $_POST['status'] ?? 'operativo',
            'insurance_expiry' => $_POST['insurance_expiry'] ?? null,
            'inspection_expiry' => $_POST['inspection_expiry'] ?? null,
            'notes' => trim($_POST['notes'] ?? ''),
            'generate_qr' => isset($_POST['generate_qr']) ? 1 : 0
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->update($vehicleId, $data, $app->getUserId());
            } else {
                $result = $controller->create($data, $app->getUserId());
                $vehicleId = $result;
            }
            
            if ($result) {
                $success = true;
                header('Location: vehicle_view.php?id=' . $vehicleId . '&success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Mezzo' : 'Nuovo Mezzo';
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
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-truck"></i> Dati Mezzo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Nome Mezzo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($vehicle['name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="vehicle_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                                    <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                        <option value="veicolo" <?php echo ($vehicle['vehicle_type'] ?? '') === 'veicolo' ? 'selected' : ''; ?>>Veicolo</option>
                                        <option value="natante" <?php echo ($vehicle['vehicle_type'] ?? '') === 'natante' ? 'selected' : ''; ?>>Natante</option>
                                        <option value="rimorchio" <?php echo ($vehicle['vehicle_type'] ?? '') === 'rimorchio' ? 'selected' : ''; ?>>Rimorchio</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="license_plate" class="form-label">Targa</label>
                                    <input type="text" class="form-control" id="license_plate" name="license_plate" 
                                           value="<?php echo htmlspecialchars($vehicle['license_plate'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="serial_number" class="form-label">Numero Telaio</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                           value="<?php echo htmlspecialchars($vehicle['serial_number'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="brand" class="form-label">Marca</label>
                                    <input type="text" class="form-control" id="brand" name="brand" 
                                           value="<?php echo htmlspecialchars($vehicle['brand'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="model" class="form-label">Modello</label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo htmlspecialchars($vehicle['model'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="year" class="form-label">Anno</label>
                                    <input type="number" class="form-control" id="year" name="year" 
                                           min="1900" max="<?php echo date('Y') + 1; ?>"
                                           value="<?php echo htmlspecialchars($vehicle['year'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="status" class="form-label">Stato <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="operativo" <?php echo ($vehicle['status'] ?? 'operativo') === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                                        <option value="in_manutenzione" <?php echo ($vehicle['status'] ?? '') === 'in_manutenzione' ? 'selected' : ''; ?>>In Manutenzione</option>
                                        <option value="fuori_servizio" <?php echo ($vehicle['status'] ?? '') === 'fuori_servizio' ? 'selected' : ''; ?>>Fuori Servizio</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Scadenze</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="insurance_expiry" class="form-label">Scadenza Assicurazione</label>
                                    <input type="date" class="form-control" id="insurance_expiry" name="insurance_expiry" 
                                           value="<?php echo htmlspecialchars($vehicle['insurance_expiry'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="inspection_expiry" class="form-label">Scadenza Revisione</label>
                                    <input type="date" class="form-control" id="inspection_expiry" name="inspection_expiry" 
                                           value="<?php echo htmlspecialchars($vehicle['inspection_expiry'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($vehicle['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <?php if (!$isEdit): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="generate_qr" name="generate_qr" checked>
                                <label class="form-check-label" for="generate_qr">
                                    Genera QR Code automaticamente
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="vehicles.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annulla
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salva
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
