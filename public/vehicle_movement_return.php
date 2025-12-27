<?php
/**
 * Public Vehicle Movement Management - Return Form
 * 
 * Form for registering vehicle return with drivers, checklist, and details
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\VehicleMovementController;

$app = App::getInstance();
$db = $app->getDb();
$config = $app->getConfig();

// Check if member is authenticated
if (!isset($_SESSION['vehicle_movement_member'])) {
    header('Location: vehicle_movement_login.php');
    exit;
}

$member = $_SESSION['vehicle_movement_member'];
$movementId = isset($_GET['movement_id']) ? intval($_GET['movement_id']) : 0;

if ($movementId <= 0) {
    header('Location: vehicle_movement.php');
    exit;
}

$controller = new VehicleMovementController($db, $config);

// Get movement details
$movement = $controller->getMovement($movementId);
if (!$movement || $movement['status'] !== 'in_mission') {
    header('Location: vehicle_movement.php?error=invalid_movement');
    exit;
}

// Get vehicle checklists for return (including trailer checklists if present)
$checklists = $controller->getVehicleChecklists(
    $movement['vehicle_id'], 
    'return', 
    $movement['trailer_id'] ?? null
);

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
            'drivers' => !empty($_POST['drivers']) ? array_map('intval', $_POST['drivers']) : [],
            'return_km' => !empty($_POST['return_km']) ? floatval($_POST['return_km']) : null,
            'return_fuel_level' => $_POST['return_fuel_level'] ?? null,
            'return_notes' => $_POST['return_notes'] ?? null,
            'return_anomaly_flag' => isset($_POST['return_anomaly_flag']) ? 1 : 0,
            'traffic_violation_flag' => isset($_POST['traffic_violation_flag']) ? 1 : 0,
            'checklist' => $checklistData
        ];
        
        $result = $controller->createReturn($movementId, $returnData, $member['id']);
        
        if ($result['success']) {
            header('Location: vehicle_movement_detail.php?id=' . $movement['vehicle_id'] . '&success=return');
            exit;
        }
        
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Registra Rientro Veicolo';
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
        .departure-info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <a href="vehicle_movement_detail.php?id=<?php echo $movement['vehicle_id']; ?>" class="navbar-brand">
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
                <i class="bi bi-box-arrow-in-left"></i> Registra Rientro Veicolo
            </h2>
            
            <!-- Departure Info -->
            <div class="departure-info">
                <h5><i class="bi bi-info-circle-fill"></i> Informazioni Uscita</h5>
                <p class="mb-1">
                    <strong>Veicolo:</strong> <?php echo htmlspecialchars($movement['vehicle_name']); ?>
                    (<?php echo htmlspecialchars($movement['license_plate']); ?>)
                </p>
                <?php if (!empty($movement['trailer_name'])): ?>
                    <p class="mb-1">
                        <strong>Rimorchio:</strong> 
                        <span class="badge bg-secondary">
                            <i class="bi bi-link-45deg"></i> 
                            <?php echo htmlspecialchars($movement['trailer_name']); ?>
                        </span>
                    </p>
                <?php endif; ?>
                <p class="mb-1">
                    <strong>Data/Ora Partenza:</strong> 
                    <?php echo date('d/m/Y H:i', strtotime($movement['departure_datetime'])); ?>
                </p>
                <?php if (!empty($movement['departure_drivers'])): ?>
                    <p class="mb-1">
                        <strong>Autisti Partenza:</strong>
                        <?php 
                        $driverNames = array_map(function($d) {
                            return htmlspecialchars($d['first_name'] . ' ' . $d['last_name']);
                        }, $movement['departure_drivers']);
                        echo implode(', ', $driverNames);
                        ?>
                    </p>
                <?php endif; ?>
                <?php if ($movement['destination']): ?>
                    <p class="mb-1">
                        <strong>Destinazione:</strong> <?php echo htmlspecialchars($movement['destination']); ?>
                    </p>
                <?php endif; ?>
                <?php if ($movement['departure_km']): ?>
                    <p class="mb-0">
                        <strong>Km Partenza:</strong> <?php echo number_format($movement['departure_km'], 2); ?> km
                    </p>
                <?php endif; ?>
            </div>

            <form method="POST" id="returnForm">
                <!-- Required Fields Section -->
                <div class="section-header">
                    <h5 class="mb-0"><i class="bi bi-1-circle-fill"></i> Dati Obbligatori</h5>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Data e Ora Rientro *</label>
                        <input type="datetime-local" 
                               name="return_datetime" 
                               class="form-control" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>" 
                               min="<?php echo date('Y-m-d\TH:i', strtotime($movement['departure_datetime'])); ?>"
                               required>
                    </div>
                </div>

                <!-- Drivers Selection (Optional for return) -->
                <div class="mb-3">
                    <label class="form-label">Autisti al Rientro (opzionale)</label>
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
                        Se gli autisti al rientro sono diversi da quelli in partenza, selezionarli qui
                    </small>
                </div>

                <!-- Optional Fields Section -->
                <div class="section-header">
                    <h5 class="mb-0"><i class="bi bi-2-circle-fill"></i> Dati Facoltativi</h5>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Km Rientro</label>
                        <input type="number" 
                               name="return_km" 
                               class="form-control" 
                               step="0.01" 
                               min="<?php echo $movement['departure_km'] ?: 0; ?>"
                               placeholder="Inserisci chilometraggio">
                        <?php if ($movement['departure_km']): ?>
                            <small class="text-muted">
                                Km partenza: <?php echo number_format($movement['departure_km'], 2); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Stato Carburante</label>
                        <select name="return_fuel_level" class="form-select">
                            <option value="">Non specificato</option>
                            <option value="empty">Vuoto</option>
                            <option value="1/4">1/4</option>
                            <option value="1/2">1/2</option>
                            <option value="3/4">3/4</option>
                            <option value="full">Pieno</option>
                        </select>
                    </div>
                </div>

                <!-- Checklist Section -->
                <?php if (!empty($checklists)): ?>
                    <div class="section-header">
                        <h5 class="mb-0"><i class="bi bi-3-circle-fill"></i> Check List Rientro</h5>
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
                    <h5 class="mb-0"><i class="bi bi-4-circle-fill"></i> Anomalie e Segnalazioni</h5>
                </div>

                <div class="mb-3">
                    <label class="form-label">Note Anomalie o Danni</label>
                    <textarea name="return_notes" 
                              class="form-control" 
                              rows="4"
                              placeholder="Descrivi eventuali anomalie, danni o problemi rilevati al rientro"></textarea>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" 
                           class="form-check-input" 
                           name="traffic_violation_flag" 
                           id="trafficViolationFlag"
                           value="1">
                    <label class="form-check-label" for="trafficViolationFlag">
                        <strong>Ipotesi Sanzioni al Codice della Strada</strong>
                        <br><small class="text-muted">
                            Se selezionato, verrà inviata un'email all'associazione con i dettagli del viaggio
                        </small>
                    </label>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" 
                           class="form-check-input" 
                           name="return_anomaly_flag" 
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
                    <a href="vehicle_movement_detail.php?id=<?php echo $movement['vehicle_id']; ?>" 
                       class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Annulla
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle"></i> Conferma Rientro
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Driver search and selection (same as departure)
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
    </script>
</body>
</html>
