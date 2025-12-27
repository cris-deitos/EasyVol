<?php
/**
 * Public Vehicle Movement Management - Departure Form
 * 
 * Form for registering vehicle departure with drivers, checklist, and details
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
$vehicleId = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

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

// Check vehicle can depart
if ($vehicle['status'] === 'fuori_servizio') {
    header('Location: vehicle_movement_detail.php?id=' . $vehicleId . '&error=fuori_servizio');
    exit;
}

if ($controller->isVehicleInMission($vehicleId)) {
    header('Location: vehicle_movement_detail.php?id=' . $vehicleId . '&error=already_in_mission');
    exit;
}

// Get available trailers
$availableTrailers = $controller->getAvailableTrailers();

// Get vehicle checklists - will be updated based on trailer selection via JavaScript
$checklists = $controller->getVehicleChecklists($vehicleId, 'departure');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        $departureData = [
            'vehicle_id' => $vehicleId,
            'trailer_id' => !empty($_POST['trailer_id']) ? intval($_POST['trailer_id']) : null,
            'departure_datetime' => $_POST['departure_datetime'],
            'drivers' => array_map('intval', $_POST['drivers']),
            'departure_km' => !empty($_POST['departure_km']) ? floatval($_POST['departure_km']) : null,
            'departure_fuel_level' => $_POST['departure_fuel_level'] ?? null,
            'service_type' => $_POST['service_type'] ?? null,
            'destination' => $_POST['destination'] ?? null,
            'authorized_by' => $_POST['authorized_by'] ?? null,
            'departure_notes' => $_POST['departure_notes'] ?? null,
            'departure_anomaly_flag' => isset($_POST['departure_anomaly_flag']) ? 1 : 0,
            'checklist' => $checklistData
        ];
        
        $result = $controller->createDeparture($departureData, $member['id']);
        
        if ($result['success']) {
            header('Location: vehicle_movement_detail.php?id=' . $vehicleId . '&success=departure');
            exit;
        }
        
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Registra Uscita Veicolo';
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
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section-header {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 20px 0 15px 0;
            border-left: 4px solid #667eea;
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
        .checklist-item {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
        }
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <a href="vehicle_movement_detail.php?id=<?php echo $vehicleId; ?>" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> Torna al veicolo
            </a>
            <div class="d-flex align-items-center text-white">
                <span>
                    <i class="bi bi-person-circle"></i>
                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h2 class="mb-4">
                <i class="bi bi-box-arrow-right"></i> Registra Uscita Veicolo
            </h2>
            
            <div class="alert alert-info">
                <strong>Veicolo:</strong> <?php echo htmlspecialchars($vehicle['license_plate'] ?: $vehicle['serial_number']); ?> -
                <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>
                <?php if ($vehicle['license_type']): ?>
                    <br><strong>Patente richiesta:</strong> <?php echo htmlspecialchars($vehicle['license_type']); ?>
                <?php endif; ?>
            </div>

            <form method="POST" id="departureForm">
                <!-- Required Fields Section -->
                <div class="section-header">
                    <h5 class="mb-0"><i class="bi bi-1-circle-fill"></i> Dati Obbligatori</h5>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Data e Ora Uscita *</label>
                        <input type="datetime-local" 
                               name="departure_datetime" 
                               class="form-control" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>" 
                               required>
                    </div>
                </div>

                <!-- Drivers Selection -->
                <div class="mb-3">
                    <label class="form-label">Autisti *</label>
                    <div class="position-relative">
                        <input type="text" 
                               id="driverSearch" 
                               class="form-control" 
                               placeholder="Cerca per matricola o cognome..."
                               autocomplete="off">
                        <div id="searchResults" class="search-results" style="display: none;"></div>
                    </div>
                    <div id="selectedDrivers" class="mt-2"></div>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Gli autisti selezionati devono avere le patenti richieste dal veicolo
                    </small>
                </div>

                <!-- Optional Fields Section -->
                <div class="section-header">
                    <h5 class="mb-0"><i class="bi bi-2-circle-fill"></i> Dati Facoltativi</h5>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Km Partenza</label>
                        <input type="number" 
                               name="departure_km" 
                               class="form-control" 
                               step="0.01" 
                               min="0"
                               placeholder="Inserisci chilometraggio">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Stato Carburante</label>
                        <select name="departure_fuel_level" class="form-select">
                            <option value="">Non specificato</option>
                            <option value="empty">Vuoto</option>
                            <option value="1/4">1/4</option>
                            <option value="1/2">1/2</option>
                            <option value="3/4">3/4</option>
                            <option value="full">Pieno</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo di Servizio</label>
                        <input type="text" 
                               name="service_type" 
                               class="form-control" 
                               placeholder="es: Emergenza, Esercitazione, Trasporto">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Destinazione</label>
                        <input type="text" 
                               name="destination" 
                               class="form-control" 
                               placeholder="Inserisci destinazione">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Autorizzato da</label>
                    <input type="text" 
                           name="authorized_by" 
                           class="form-control" 
                           placeholder="Nome di chi ha autorizzato l'uscita">
                </div>

                <!-- Trailer Selection -->
                <?php if (!empty($availableTrailers)): ?>
                <div class="mb-3">
                    <label class="form-label">Rimorchio (opzionale)</label>
                    <select name="trailer_id" id="trailerSelect" class="form-select">
                        <option value="">Nessun rimorchio</option>
                        <?php foreach ($availableTrailers as $trailer): ?>
                            <option value="<?php echo $trailer['id']; ?>"
                                    data-license="<?php echo htmlspecialchars($trailer['license_type'] ?? ''); ?>">
                                <?php 
                                echo htmlspecialchars($trailer['name']);
                                if ($trailer['license_plate']) {
                                    echo ' - ' . htmlspecialchars($trailer['license_plate']);
                                } elseif ($trailer['serial_number']) {
                                    echo ' - ' . htmlspecialchars($trailer['serial_number']);
                                }
                                if ($trailer['license_type']) {
                                    echo ' (Patente: ' . htmlspecialchars($trailer['license_type']) . ')';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Se si aggancia un rimorchio, gli autisti devono possedere anche le patenti richieste dal rimorchio
                    </small>
                </div>
                <?php endif; ?>

                <!-- Checklist Section -->
                <?php if (!empty($checklists)): ?>
                    <div class="section-header">
                        <h5 class="mb-0"><i class="bi bi-3-circle-fill"></i> Check List Uscita</h5>
                    </div>

                    <?php foreach ($checklists as $item): ?>
                        <div class="checklist-item">
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
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Anomalies Section -->
                <div class="section-header">
                    <h5 class="mb-0"><i class="bi bi-4-circle-fill"></i> Anomalie o Danni</h5>
                </div>

                <div class="mb-3">
                    <label class="form-label">Note Anomalie o Danni</label>
                    <textarea name="departure_notes" 
                              class="form-control" 
                              rows="4"
                              placeholder="Descrivi eventuali anomalie, danni o problemi rilevati"></textarea>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" 
                           class="form-check-input" 
                           name="departure_anomaly_flag" 
                           id="anomalyFlag"
                           value="1">
                    <label class="form-check-label" for="anomalyFlag">
                        <strong>Invia Alert di Anomalia o Segnalazione</strong>
                        <br><small class="text-muted">
                            Se selezionato, verrà inviata un'email agli indirizzi configurati con i dettagli delle anomalie
                        </small>
                    </label>
                </div>

                <!-- Submit Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="vehicle_movement_detail.php?id=<?php echo $vehicleId; ?>" 
                       class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Annulla
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> Conferma Uscita
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Driver search and selection
        const selectedDrivers = new Set();
        const driverSearch = document.getElementById('driverSearch');
        const searchResults = document.getElementById('searchResults');
        const selectedDriversDiv = document.getElementById('selectedDrivers');
        
        let searchTimeout;
        
        driverSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch('vehicle_movement_api.php?action=search_drivers&q=' + encodeURIComponent(query))
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
            if (!driverSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Form validation
        document.getElementById('departureForm').addEventListener('submit', function(e) {
            if (selectedDrivers.size === 0) {
                e.preventDefault();
                alert('Selezionare almeno un autista');
                driverSearch.focus();
            }
        });
    </script>
</body>
</html>
