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

// Note: Members/drivers are now loaded dynamically via AJAX search

// Get vehicle checklists for return (including trailer checklists if present)
$checklists = $controller->getVehicleChecklists(
    $movement['vehicle_id'], 
    'return', 
    $movement['trailer_id'] ?? null
);

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
            
            // Prepare checklist data
            $checklistData = [];
            if (!empty($_POST['checklist'])) {
                foreach ($_POST['checklist'] as $itemId => $value) {
                    $checklistItem = array_filter($checklists, function($c) use ($itemId) {
                        return $c['id'] == $itemId;
                    });
                    
                    if (!empty($checklistItem)) {
                        $item = reset($checklistItem);
                        $checklistData[] = [
                            'checklist_item_id' => $itemId,
                            'item_name' => $item['item_name'],
                            'item_type' => $item['item_type'],
                            'value_boolean' => $item['item_type'] === 'boolean' ? intval($value) : null,
                            'value_numeric' => $item['item_type'] === 'numeric' ? floatval($value) : null,
                            'value_text' => $item['item_type'] === 'text' ? $value : null
                        ];
                    }
                }
            }
            
            $returnData = [
                'return_datetime' => $_POST['return_datetime'],
                'drivers' => array_map('intval', $_POST['drivers']),
                'return_km' => !empty($_POST['return_km']) ? floatval($_POST['return_km']) : null,
                'return_fuel_level' => (!empty($_POST['return_fuel_level']) && trim($_POST['return_fuel_level']) !== '') ? $_POST['return_fuel_level'] : null,
                'return_notes' => $_POST['return_notes'] ?? null,
                'return_anomaly_flag' => isset($_POST['return_anomaly_flag']) ? 1 : 0,
                'traffic_violation_flag' => isset($_POST['traffic_violation_flag']) ? 1 : 0,
                'checklist' => $checklistData
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
    <style>
        .search-results {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .search-result-item:hover {
            background-color: #f8f9fa;
        }
        .driver-item {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
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
                                    <div class="position-relative">
                                        <input type="text" 
                                               id="driverSearch" 
                                               class="form-control" 
                                               placeholder="Cerca per nome, cognome o matricola..."
                                               autocomplete="off">
                                        <div id="searchResults" class="search-results" style="display: none;"></div>
                                    </div>
                                    <div id="selectedDrivers" class="mt-2"></div>
                                    <small class="form-text text-muted">Inizia a digitare per cercare e selezionare gli autisti</small>
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
                            
                            <!-- Checklist Section -->
                            <?php if (!empty($checklists)): ?>
                                <hr>
                                <h5><i class="bi bi-list-check"></i> Check List Rientro</h5>
                                <?php foreach ($checklists as $item): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <label class="form-label fw-bold">
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                                <?php if ($item['is_required']): ?>
                                                    <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>
                                            
                                            <?php if ($item['item_type'] === 'boolean'): ?>
                                                <div class="form-check form-switch">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           name="checklist[<?php echo $item['id']; ?>]" 
                                                           value="1"
                                                           id="checklist_<?php echo $item['id']; ?>"
                                                           <?php echo $item['is_required'] ? 'required' : ''; ?>>
                                                    <label class="form-check-label" for="checklist_<?php echo $item['id']; ?>">
                                                        Verificato
                                                    </label>
                                                </div>
                                            <?php elseif ($item['item_type'] === 'numeric'): ?>
                                                <input type="number" 
                                                       name="checklist[<?php echo $item['id']; ?>]" 
                                                       class="form-control" 
                                                       step="0.01"
                                                       placeholder="Inserisci quantità"
                                                       <?php echo $item['is_required'] ? 'required' : ''; ?>>
                                            <?php else: ?>
                                                <textarea name="checklist[<?php echo $item['id']; ?>]" 
                                                          class="form-control" 
                                                          rows="2"
                                                          placeholder="Inserisci note"
                                                          <?php echo $item['is_required'] ? 'required' : ''; ?>></textarea>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
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
    <script>
        // Driver search and selection functionality
        const selectedDrivers = new Set();
        const driverSearch = document.getElementById('driverSearch');
        const searchResults = document.getElementById('searchResults');
        const selectedDriversDiv = document.getElementById('selectedDrivers');
        
        let searchTimeout;
        
        if (driverSearch) {
            driverSearch.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    fetch('vehicle_movement_internal_api.php?action=search_drivers&q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.drivers.length > 0) {
                                let html = '';
                                data.drivers.forEach(driver => {
                                    if (!selectedDrivers.has(driver.id)) {
                                        html += `<div class="search-result-item" data-id="${driver.id}" data-name="${driver.first_name} ${driver.last_name}" data-reg="${driver.registration_number}">
                                            <strong>${driver.first_name} ${driver.last_name}</strong> 
                                            (${driver.registration_number})
                                            <br><small class="text-muted">${driver.roles || 'Nessuna qualifica'}</small>
                                        </div>`;
                                    }
                                });
                                
                                if (html) {
                                    searchResults.innerHTML = html;
                                    searchResults.style.display = 'block';
                                    
                                    // Add click handlers
                                    searchResults.querySelectorAll('.search-result-item').forEach(item => {
                                        item.addEventListener('click', function() {
                                            addDriver(this.dataset.id, this.dataset.name, this.dataset.reg);
                                        });
                                    });
                                } else {
                                    searchResults.style.display = 'none';
                                }
                            } else {
                                searchResults.innerHTML = '<div class="search-result-item text-muted">Nessun autista trovato</div>';
                                searchResults.style.display = 'block';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            searchResults.style.display = 'none';
                        });
                }, 300);
            });
        }
        
        function addDriver(id, name, reg) {
            if (selectedDrivers.has(id)) {
                return;
            }
            
            selectedDrivers.add(id);
            
            const driverDiv = document.createElement('div');
            driverDiv.className = 'driver-item';
            driverDiv.innerHTML = `
                <span>
                    <i class="bi bi-person-check-fill text-success"></i>
                    <strong>${name}</strong> (${reg})
                </span>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeDriver(${id}, this)">
                    <i class="bi bi-x"></i>
                </button>
                <input type="hidden" name="drivers[]" value="${id}">
            `;
            
            selectedDriversDiv.appendChild(driverDiv);
            driverSearch.value = '';
            searchResults.style.display = 'none';
        }
        
        function removeDriver(id, button) {
            selectedDrivers.delete(id.toString());
            button.closest('.driver-item').remove();
        }
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (driverSearch && !driverSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (selectedDrivers.size === 0) {
                e.preventDefault();
                alert('Selezionare almeno un autista');
                if (driverSearch) {
                    driverSearch.focus();
                }
            }
        });
    </script>
</body>
</html>
