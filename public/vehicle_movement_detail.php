<?php
/**
 * Public Vehicle Movement Management - Vehicle Detail
 * 
 * Page for managing a specific vehicle (status, departure, return, maintenance, documents)
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
$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['vehicle_movement_member']);
    header('Location: vehicle_movement_login.php');
    exit;
}

if ($vehicleId <= 0) {
    header('Location: vehicle_movement.php');
    exit;
}

$controller = new VehicleMovementController($db, $config);
$vehicleController = new VehicleController($db, $config);

// Get vehicle details
$vehicle = $vehicleController->get($vehicleId);
if (!$vehicle) {
    header('Location: vehicle_movement.php?error=not_found');
    exit;
}

// Get active movement if exists
$activeMovement = $controller->getActiveMovement($vehicleId);
$inMission = !empty($activeMovement);

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'change_status') {
            $newStatus = $_POST['status'] ?? '';
            $controller->updateVehicleStatus($vehicleId, $newStatus, $member['id']);
            $success = 'Stato aggiornato con successo';
            // Reload vehicle data
            $vehicle = $vehicleController->get($vehicleId);
            
        } elseif ($action === 'add_maintenance') {
            // Handle maintenance addition (reuse existing VehicleController logic)
            $maintenanceData = [
                'maintenance_type' => $_POST['maintenance_type'],
                'date' => $_POST['date'],
                'description' => $_POST['description'] ?? '',
                'cost' => !empty($_POST['cost']) ? floatval($_POST['cost']) : null,
                'performed_by' => $_POST['performed_by'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
            
            $vehicleController->addMaintenance($vehicleId, $maintenanceData, $member['id']);
            $success = 'Manutenzione aggiunta con successo';
            // Reload vehicle data
            $vehicle = $vehicleController->get($vehicleId);
        }
        
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Gestione Mezzo';
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
        .vehicle-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-action {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        .mission-info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 1rem;
            padding: 8px 15px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <a href="vehicle_movement.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Torna all'elenco
            </a>
            <div class="d-flex align-items-center text-white">
                <span class="me-3">
                    <i class="bi bi-person-circle"></i>
                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                </span>
                <a href="?logout=1" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Esci
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Vehicle Header -->
        <div class="vehicle-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>
                        <i class="bi bi-truck"></i>
                        <?php echo htmlspecialchars($vehicle['license_plate'] ?: $vehicle['serial_number']); ?>
                    </h2>
                    <p class="mb-2">
                        <strong>Tipo:</strong> <?php echo ucfirst($vehicle['vehicle_type']); ?> |
                        <strong>Marca/Modello:</strong> <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>
                    </p>
                    <?php if ($vehicle['license_type']): ?>
                        <p class="mb-0 text-muted">
                            <i class="bi bi-card-heading"></i>
                            <strong>Patente richiesta:</strong> <?php echo htmlspecialchars($vehicle['license_type']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end">
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
                    <?php if ($inMission): ?>
                        <br>
                        <span class="badge bg-info status-badge mt-2">
                            <i class="bi bi-geo-alt-fill"></i> IN MISSIONE
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($inMission): ?>
            <!-- Active Mission Info -->
            <div class="mission-info">
                <h5><i class="bi bi-info-circle-fill"></i> Missione Attiva</h5>
                <p class="mb-1">
                    <strong>Data/Ora Partenza:</strong> 
                    <?php echo date('d/m/Y H:i', strtotime($activeMovement['departure_datetime'])); ?>
                </p>
                <?php if ($activeMovement['departure_drivers']): ?>
                    <p class="mb-1">
                        <strong>Autisti:</strong> <?php echo htmlspecialchars($activeMovement['departure_drivers']); ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($activeMovement['trailer_name'])): ?>
                    <p class="mb-1">
                        <strong>Rimorchio:</strong> 
                        <span class="badge bg-secondary">
                            <i class="bi bi-link-45deg"></i> 
                            <?php echo htmlspecialchars($activeMovement['trailer_name']); ?>
                        </span>
                    </p>
                <?php endif; ?>
                <?php if ($activeMovement['destination']): ?>
                    <p class="mb-0">
                        <strong>Destinazione:</strong> <?php echo htmlspecialchars($activeMovement['destination']); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Actions Column -->
            <div class="col-lg-8">
                <!-- Mission Actions -->
                <div class="action-card">
                    <h4 class="mb-4"><i class="bi bi-flag-fill"></i> Gestione Missioni</h4>
                    
                    <?php if (!$inMission): ?>
                        <?php if ($vehicle['status'] === 'fuori_servizio'): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Il veicolo è fuori servizio e non può essere utilizzato per missioni.
                            </div>
                        <?php else: ?>
                            <a href="vehicle_movement_departure.php?vehicle_id=<?php echo $vehicleId; ?>" 
                               class="btn btn-primary btn-action">
                                <i class="bi bi-box-arrow-right"></i> Registra Uscita Veicolo
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="vehicle_movement_return.php?movement_id=<?php echo $activeMovement['id']; ?>" 
                           class="btn btn-success btn-action">
                            <i class="bi bi-box-arrow-in-left"></i> Registra Rientro Veicolo
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Recent Maintenance -->
                <?php if (!empty($vehicle['maintenances']) && count($vehicle['maintenances']) > 0): ?>
                    <div class="action-card">
                        <h4 class="mb-3"><i class="bi bi-wrench"></i> Manutenzioni Recenti</h4>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Descrizione</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($vehicle['maintenances'], 0, 5) as $maintenance): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($maintenance['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($maintenance['maintenance_type']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($maintenance['description'] ?? '', 0, 50)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Side Actions Column -->
            <div class="col-lg-4">
                <!-- Change Status -->
                <?php if (!$inMission): ?>
                    <div class="action-card">
                        <h5 class="mb-3"><i class="bi bi-sliders"></i> Cambia Stato</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_status">
                            <select name="status" class="form-select mb-2" required>
                                <option value="">Seleziona stato</option>
                                <option value="operativo" <?php echo $vehicle['status'] === 'operativo' ? 'selected' : ''; ?>>
                                    Operativo
                                </option>
                                <option value="in_manutenzione" <?php echo $vehicle['status'] === 'in_manutenzione' ? 'selected' : ''; ?>>
                                    In Manutenzione
                                </option>
                                <option value="fuori_servizio" <?php echo $vehicle['status'] === 'fuori_servizio' ? 'selected' : ''; ?>>
                                    Fuori Servizio
                                </option>
                            </select>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle"></i> Aggiorna Stato
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Add Maintenance -->
                <div class="action-card">
                    <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Aggiungi Manutenzione</h5>
                    <button type="button" class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#maintenanceModal">
                        <i class="bi bi-wrench"></i> Nuova Manutenzione
                    </button>
                </div>

                <!-- Upload Document -->
                <div class="action-card">
                    <h5 class="mb-3"><i class="bi bi-file-earmark-arrow-up"></i> Carica Documento</h5>
                    <button type="button" class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#documentModal">
                        <i class="bi bi-upload"></i> Carica Documento
                    </button>
                </div>

                <!-- Documents List -->
                <?php if (!empty($vehicle['documents'])): ?>
                    <div class="action-card">
                        <h5 class="mb-3"><i class="bi bi-files"></i> Documenti</h5>
                        <div class="list-group">
                            <?php foreach ($vehicle['documents'] as $doc): ?>
                                <a href="document_download.php?id=<?php echo $doc['id']; ?>" 
                                   class="list-group-item list-group-item-action" 
                                   target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <?php echo htmlspecialchars($doc['document_type']); ?>
                                    <?php if ($doc['expiry_date']): ?>
                                        <br><small class="text-muted">
                                            Scad: <?php echo date('d/m/Y', strtotime($doc['expiry_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Maintenance Modal -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-wrench"></i> Aggiungi Manutenzione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_maintenance">
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo Manutenzione *</label>
                            <select name="maintenance_type" class="form-select" required>
                                <option value="">Seleziona tipo</option>
                                <option value="ordinaria">Manutenzione Ordinaria</option>
                                <option value="straordinaria">Manutenzione Straordinaria</option>
                                <option value="revisione">Revisione</option>
                                <option value="guasto">Guasto</option>
                                <option value="riparazione">Riparazione</option>
                                <option value="anomalie">Anomalie</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data *</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrizione</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Costo (€)</label>
                            <input type="number" name="cost" class="form-control" step="0.01" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Eseguita da</label>
                            <input type="text" name="performed_by" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Salva
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Document Upload Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-arrow-up"></i> Carica Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="vehicle_document_upload.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="vehicle_id" value="<?php echo $vehicleId; ?>">
                        <input type="hidden" name="return_url" value="vehicle_movement_detail.php?id=<?php echo $vehicleId; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo Documento *</label>
                            <input type="text" name="document_type" class="form-control" required 
                                   placeholder="es: Assicurazione, Libretto, ecc.">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">File *</label>
                            <input type="file" name="document_file" class="form-control" required 
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Formati ammessi: PDF, JPG, PNG (max 10MB)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data Scadenza</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Carica
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
