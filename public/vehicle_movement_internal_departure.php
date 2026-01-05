<?php
/**
 * Internal Vehicle Movement - Departure Form (Administrative)
 * 
 * Form for registering vehicle departure from admin panel
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\VehicleMovementController;
use EasyVol\Controllers\VehicleController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('vehicles', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();

$vehicleId = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

$controller = new VehicleMovementController($db, $config);
$vehicleController = new VehicleController($db, $config);

// Get vehicle details if specified
$vehicle = null;
if ($vehicleId > 0) {
    $vehicle = $vehicleController->get($vehicleId);
    if (!$vehicle) {
        header('Location: vehicles.php?error=not_found');
        exit;
    }
    
    // Check vehicle can depart
    if ($vehicle['status'] === 'fuori_servizio') {
        header('Location: vehicle_view.php?id=' . $vehicleId . '&error=fuori_servizio');
        exit;
    }
    
    if ($controller->isVehicleInMission($vehicleId)) {
        header('Location: vehicle_view.php?id=' . $vehicleId . '&error=already_in_mission');
        exit;
    }
}

// Get available vehicles
$vehiclesSql = "SELECT id, license_plate, serial_number, brand, model, license_type, vehicle_type 
                FROM vehicles 
                WHERE status != 'fuori_servizio' 
                ORDER BY license_plate, serial_number";
$vehicles = $db->fetchAll($vehiclesSql);

// Get available trailers
$availableTrailers = $controller->getAvailableTrailers();

// Get vehicle checklists if a vehicle is pre-selected
$checklists = [];
if ($vehicleId > 0) {
    $checklists = $controller->getVehicleChecklists($vehicleId, 'departure');
}

// Note: Members/drivers are now loaded dynamically via AJAX search

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        try {
            // Validate required fields
            $selectedVehicleId = intval($_POST['vehicle_id'] ?? 0);
            if ($selectedVehicleId <= 0) {
                throw new \Exception('Selezionare un veicolo');
            }
            
            // Validate drivers
            if (empty($_POST['drivers']) || !is_array($_POST['drivers'])) {
                throw new \Exception('Selezionare almeno un autista');
            }
            
            // Prepare checklist data
            $checklistData = [];
            if (!empty($_POST['checklist'])) {
                // Get checklists for the selected vehicle
                $vehicleChecklists = $controller->getVehicleChecklists($selectedVehicleId, 'departure');
                
                foreach ($_POST['checklist'] as $itemId => $value) {
                    $checklistItem = array_filter($vehicleChecklists, function($c) use ($itemId) {
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
            
            $departureData = [
                'vehicle_id' => $selectedVehicleId,
                'trailer_id' => !empty($_POST['trailer_id']) ? intval($_POST['trailer_id']) : null,
                'departure_datetime' => $_POST['departure_datetime'],
                'drivers' => array_map('intval', $_POST['drivers']),
                'departure_km' => !empty($_POST['departure_km']) ? floatval($_POST['departure_km']) : null,
                'departure_fuel_level' => (!empty($_POST['departure_fuel_level']) && trim($_POST['departure_fuel_level']) !== '') ? $_POST['departure_fuel_level'] : null,
                'service_type' => $_POST['service_type'] ?? null,
                'destination' => $_POST['destination'] ?? null,
                'authorized_by' => $_POST['authorized_by'] ?? null,
                'departure_notes' => $_POST['departure_notes'] ?? null,
                'departure_anomaly_flag' => isset($_POST['departure_anomaly_flag']) ? 1 : 0,
                'checklist' => $checklistData
            ];
            
            $result = $controller->createDeparture($departureData, $app->getUserId());
            
            if ($result['success']) {
                header('Location: vehicle_movements.php?success=departure');
                exit;
            } else {
                $errors[] = $result['message'] ?? 'Errore durante la registrazione';
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Registra Uscita Veicolo';
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
                
                <?php if ($vehicle): ?>
                    <div class="alert alert-info">
                        <strong>Veicolo selezionato:</strong> 
                        <?php echo htmlspecialchars($vehicle['license_plate'] ?: $vehicle['serial_number']); ?> -
                        <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>
                        <?php if ($vehicle['license_type']): ?>
                            <br><strong>Patente richiesta:</strong> <?php echo htmlspecialchars($vehicle['license_type']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-box-arrow-right"></i> Dati Uscita</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$vehicle): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="vehicle_id" class="form-label">Veicolo <span class="text-danger">*</span></label>
                                    <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Seleziona veicolo...</option>
                                        <?php foreach ($vehicles as $v): ?>
                                            <option value="<?php echo $v['id']; ?>" data-vehicle-type="<?php echo htmlspecialchars($v['vehicle_type']); ?>">
                                                <?php echo htmlspecialchars(($v['license_plate'] ?: $v['serial_number']) . ' - ' . $v['brand'] . ' ' . $v['model']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="departure_datetime" class="form-label">Data e Ora Uscita <span class="text-danger">*</span></label>
                                    <input type="datetime-local" 
                                           class="form-control" 
                                           id="departure_datetime" 
                                           name="departure_datetime" 
                                           value="<?php echo date('Y-m-d\TH:i'); ?>" 
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="trailer_id" class="form-label">Rimorchio</label>
                                    <select class="form-select" id="trailer_id" name="trailer_id">
                                        <option value="">Nessun rimorchio</option>
                                        <?php foreach ($availableTrailers as $trailer): ?>
                                            <option value="<?php echo $trailer['id']; ?>">
                                                <?php echo htmlspecialchars($trailer['name'] . ' - ' . $trailer['license_plate']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Autisti <span class="text-danger">*</span></label>
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
                                <div class="col-md-4" id="departure_km_field">
                                    <label for="departure_km" class="form-label">Km Partenza</label>
                                    <input type="number" step="0.01" class="form-control" id="departure_km" name="departure_km">
                                    <small class="form-text text-muted" id="natante_info" style="display: none;">
                                        <i class="bi bi-info-circle"></i> I natanti non richiedono la registrazione dei chilometri.
                                    </small>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="departure_fuel_level" class="form-label">Livello Carburante</label>
                                    <select class="form-select" id="departure_fuel_level" name="departure_fuel_level">
                                        <option value="">Non specificato</option>
                                        <option value="pieno">Pieno</option>
                                        <option value="3/4">3/4</option>
                                        <option value="1/2">1/2</option>
                                        <option value="1/4">1/4</option>
                                        <option value="riserva">Riserva</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="service_type" class="form-label">Tipo Servizio</label>
                                    <select class="form-select" id="service_type" name="service_type">
                                        <option value="">Seleziona...</option>
                                        <option value="emergenza">Emergenza</option>
                                        <option value="trasporto">Trasporto</option>
                                        <option value="formazione">Formazione</option>
                                        <option value="manutenzione">Manutenzione</option>
                                        <option value="altro">Altro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="destination" class="form-label">Destinazione</label>
                                    <input type="text" class="form-control" id="destination" name="destination">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="authorized_by" class="form-label">Autorizzato da</label>
                                    <input type="text" class="form-control" id="authorized_by" name="authorized_by">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="departure_notes" class="form-label">Note Partenza</label>
                                <textarea class="form-control" id="departure_notes" name="departure_notes" rows="3"></textarea>
                            </div>
                            
                            <!-- Checklist Section (loaded dynamically) -->
                            <div id="checklistSection" style="display: none;">
                                <hr>
                                <h5><i class="bi bi-list-check"></i> Check List Uscita</h5>
                                <div id="checklistContainer"></div>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="departure_anomaly_flag" name="departure_anomaly_flag">
                                <label class="form-check-label text-warning" for="departure_anomaly_flag">
                                    <i class="bi bi-exclamation-triangle"></i> Segnala anomalia in partenza
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="vehicle_movements.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annulla
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Registra Uscita
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle vehicle type change to hide/show km field for natanti
        const vehicleSelect = document.getElementById('vehicle_id');
        if (vehicleSelect) {
            vehicleSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const vehicleType = selectedOption.getAttribute('data-vehicle-type');
                const kmInput = document.getElementById('departure_km');
                const kmLabel = document.querySelector('label[for="departure_km"]');
                const natanteInfo = document.getElementById('natante_info');
                
                if (kmInput && kmLabel && natanteInfo) {
                    if (vehicleType === 'natante') {
                        kmInput.style.display = 'none';
                        kmLabel.style.display = 'none';
                        natanteInfo.style.display = 'block';
                        kmInput.value = ''; // Clear value
                        kmInput.removeAttribute('required');
                    } else {
                        kmInput.style.display = 'block';
                        kmLabel.style.display = 'block';
                        natanteInfo.style.display = 'none';
                    }
                }
            });
        }
        
        // If vehicle is pre-selected, apply natante styling
        <?php if ($vehicle && $vehicle['vehicle_type'] === 'natante'): ?>
        const kmInput = document.getElementById('departure_km');
        const kmLabel = document.querySelector('label[for="departure_km"]');
        const natanteInfo = document.getElementById('natante_info');
        if (kmInput && kmLabel && natanteInfo) {
            kmInput.style.display = 'none';
            kmLabel.style.display = 'none';
            natanteInfo.style.display = 'block';
            kmInput.value = '';
            kmInput.removeAttribute('required');
        }
        <?php endif; ?>
        
        // Load checklists when vehicle is selected
        if (vehicleSelect) {
            vehicleSelect.addEventListener('change', function() {
                const vehicleId = this.value;
                if (vehicleId) {
                    const trailerId = document.getElementById('trailer_id') ? document.getElementById('trailer_id').value : '';
                    loadChecklists(vehicleId, trailerId);
                } else {
                    document.getElementById('checklistSection').style.display = 'none';
                }
            });
            
            // Load checklists if vehicle is pre-selected
            <?php if ($vehicleId > 0): ?>
            loadChecklists(<?php echo $vehicleId; ?>);
            <?php endif; ?>
        }
        
        // Load checklists when trailer is selected
        const trailerSelect = document.getElementById('trailer_id');
        if (trailerSelect) {
            trailerSelect.addEventListener('change', function() {
                const vehicleSelect = document.getElementById('vehicle_id');
                const vehicleId = vehicleSelect ? vehicleSelect.value : <?php echo $vehicleId ?: 0; ?>;
                if (vehicleId) {
                    loadChecklists(vehicleId, this.value);
                }
            });
        }
        
        function loadChecklists(vehicleId, trailerId = '') {
            let url = 'vehicle_checklist_api.php?action=get_checklists&vehicle_id=' + vehicleId + '&timing=departure';
            if (trailerId) {
                url += '&trailer_id=' + trailerId;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.checklists.length > 0) {
                        // Server already filters by timing, no need to filter again
                        renderChecklists(data.checklists);
                        document.getElementById('checklistSection').style.display = 'block';
                    } else {
                        document.getElementById('checklistSection').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading checklists:', error);
                });
        }
        
        function renderChecklists(checklists) {
            const container = document.getElementById('checklistContainer');
            let html = '';
            
            checklists.forEach(item => {
                html += '<div class="card mb-3">';
                html += '<div class="card-body">';
                html += '<label class="form-label fw-bold">';
                html += escapeHtml(item.item_name);
                if (item.is_required == 1) {
                    html += ' <span class="text-danger">*</span>';
                }
                html += '</label>';
                
                if (item.item_type === 'boolean') {
                    html += '<div class="form-check form-switch">';
                    html += '<input type="checkbox" class="form-check-input" name="checklist[' + item.id + ']" value="1" id="checklist_' + item.id + '"';
                    if (item.is_required == 1) html += ' required';
                    html += '>';
                    html += '<label class="form-check-label" for="checklist_' + item.id + '">Verificato</label>';
                    html += '</div>';
                } else if (item.item_type === 'numeric') {
                    html += '<input type="number" name="checklist[' + item.id + ']" class="form-control" step="0.01" placeholder="Inserisci quantità"';
                    if (item.is_required == 1) html += ' required';
                    html += '>';
                } else {
                    html += '<textarea name="checklist[' + item.id + ']" class="form-control" rows="2" placeholder="Inserisci note"';
                    if (item.is_required == 1) html += ' required';
                    html += '></textarea>';
                }
                
                html += '</div></div>';
            });
            
            container.innerHTML = html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
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
