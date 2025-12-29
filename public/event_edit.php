<?php
/**
 * Gestione Eventi - Modifica/Crea
 * 
 * Pagina per creare o modificare un evento/intervento
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\EventController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $eventId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('events', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('events', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EventController($db, $config);

$event = [];
$errors = [];
$success = false;

// Se in modifica, carica dati esistenti
if ($isEdit) {
    $event = $controller->get($eventId);
    if (!$event) {
        header('Location: events.php?error=not_found');
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
            'event_type' => $_POST['event_type'] ?? 'attivita',
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'status' => $_POST['status'] ?? 'aperto',
            'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
            'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null,
            'full_address' => trim($_POST['full_address'] ?? ''),
            'municipality' => trim($_POST['municipality'] ?? '')
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->update($eventId, $data, $app->getUserId());
            } else {
                $result = $controller->create($data, $app->getUserId());
                $eventId = $result;
            }
            
            if ($result) {
                $success = true;
                header('Location: event_view.php?id=' . $eventId . '&success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Evento' : 'Nuovo Evento';
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
                        <a href="events.php" class="text-decoration-none text-muted">
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
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Dati Evento</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Titolo Evento <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="event_type" class="form-label">Tipo Evento <span class="text-danger">*</span></label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="emergenza" <?php echo ($event['event_type'] ?? '') === 'emergenza' ? 'selected' : ''; ?>>Emergenza</option>
                                        <option value="esercitazione" <?php echo ($event['event_type'] ?? '') === 'esercitazione' ? 'selected' : ''; ?>>Esercitazione</option>
                                        <option value="attivita" <?php echo ($event['event_type'] ?? 'attivita') === 'attivita' ? 'selected' : ''; ?>>Attività</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="description" class="form-label">Descrizione</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="location" class="form-label">Località</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
                                           placeholder="es. Via Roma 123, Milano">
                                    <small class="form-text text-muted">La georeferenziazione avviene automaticamente durante la digitazione</small>
                                </div>
                            </div>
                            
                            <!-- Geocoding results -->
                            <div id="geocoding-results" class="mb-3" style="display: none;">
                                <label class="form-label">Altri indirizzi trovati (opzionale):</label>
                                <div class="list-group" id="address-suggestions"></div>
                            </div>
                            
                            <!-- Hidden fields for geocoding data -->
                            <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($event['latitude'] ?? ''); ?>">
                            <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($event['longitude'] ?? ''); ?>">
                            <input type="hidden" id="full_address" name="full_address" value="<?php echo htmlspecialchars($event['full_address'] ?? ''); ?>">
                            <input type="hidden" id="municipality" name="municipality" value="<?php echo htmlspecialchars($event['municipality'] ?? ''); ?>">
                            
                            <!-- Selected address display -->
                            <div id="selected-address" class="alert alert-info" style="<?php echo !empty($event['full_address']) ? '' : 'display: none;'; ?>">
                                <strong><i class="bi bi-geo-alt"></i> Indirizzo georeferenziato:</strong><br>
                                <span id="selected-address-text"><?php echo htmlspecialchars($event['full_address'] ?? ''); ?></span>
                                <?php if (!empty($event['municipality'])): ?>
                                    <br><small>Comune: <?php echo htmlspecialchars($event['municipality']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-clock"></i> Date e Orari</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Data e Ora Inizio <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo !empty($event['start_date']) ? date('Y-m-d\TH:i', strtotime($event['start_date'])) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">Data e Ora Fine</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo !empty($event['end_date']) ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ''; ?>">
                                    <small class="form-text text-muted">Lasciare vuoto se l'evento è ancora in corso</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-flag"></i> Stato</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="status" class="form-label">Stato Evento <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="aperto" <?php echo ($event['status'] ?? 'aperto') === 'aperto' ? 'selected' : ''; ?>>Aperto</option>
                                        <option value="in_corso" <?php echo ($event['status'] ?? '') === 'in_corso' ? 'selected' : ''; ?>>In Corso</option>
                                        <option value="concluso" <?php echo ($event['status'] ?? '') === 'concluso' ? 'selected' : ''; ?>>Concluso</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="events.php" class="btn btn-secondary">
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
    <script>
        const eventId = <?php echo json_encode($eventId); ?>;
        const initialStatus = <?php echo json_encode($event['status'] ?? 'aperto'); ?>;
        
        // Geocoding functionality
        let geocodingTimeout = null;
        let currentResults = [];
        const locationInput = document.getElementById('location');
        const resultsDiv = document.getElementById('geocoding-results');
        const suggestionsDiv = document.getElementById('address-suggestions');
        const selectedAddressDiv = document.getElementById('selected-address');
        const selectedAddressText = document.getElementById('selected-address-text');
        
        // Listen to status changes
        const statusSelect = document.getElementById('status');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                const newStatus = this.value;
                
                // Se l'utente sta cercando di chiudere l'evento
                if (newStatus === 'concluso' && initialStatus !== 'concluso' && eventId > 0) {
                    // Verifica se ci sono interventi attivi
                    checkAndWarnActiveInterventions(() => {
                        // Se ci sono interventi attivi, ripristina lo stato precedente
                        statusSelect.value = initialStatus;
                    });
                }
            });
        }
        
        // Helper function to build warning message
        function buildActiveInterventionsMessage(interventions) {
            let message = 'NON è possibile chiudere l\'evento perché ci sono ancora interventi in corso o sospesi:\n\n';
            
            interventions.forEach(intervention => {
                const statusLabel = intervention.status === 'in_corso' ? 'In Corso' : 'Sospeso';
                message += `• ${intervention.title} (${statusLabel})\n`;
            });
            
            message += '\nChiudere prima tutti gli interventi per poter chiudere l\'evento.';
            return message;
        }
        
        // Verifica se ci sono interventi attivi e mostra warning
        function checkAndWarnActiveInterventions(onActiveFound) {
            fetch(`event_ajax.php?action=check_active_interventions&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_active) {
                        const message = buildActiveInterventionsMessage(data.interventions);
                        alert(message);
                        
                        if (onActiveFound) {
                            onActiveFound();
                        }
                    }
                })
                .catch(error => {
                    console.error('Errore verifica interventi attivi:', error);
                    alert('Errore durante la verifica degli interventi attivi');
                    if (onActiveFound) {
                        onActiveFound();
                    }
                });
        }
        
        // Intercetta il submit del form per validare prima dell'invio
        const eventForm = document.querySelector('form');
        if (eventForm) {
            eventForm.addEventListener('submit', function(e) {
                const newStatus = statusSelect.value;
                
                // Se l'utente sta cercando di chiudere l'evento, verifica prima
                if (newStatus === 'concluso' && initialStatus !== 'concluso' && eventId > 0) {
                    e.preventDefault();
                    
                    fetch(`event_ajax.php?action=check_active_interventions&event_id=${eventId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.has_active) {
                                const message = buildActiveInterventionsMessage(data.interventions);
                                alert(message);
                                statusSelect.value = initialStatus;
                            } else {
                                // Nessun intervento attivo, procedi con il submit
                                eventForm.submit();
                            }
                        })
                        .catch(error => {
                            console.error('Errore verifica interventi attivi:', error);
                            alert('Errore durante la verifica degli interventi attivi');
                        });
                }
            });
        }
        
        // Listen to location input changes
        locationInput.addEventListener('input', function() {
            clearTimeout(geocodingTimeout);
            
            const query = this.value.trim();
            
            if (query.length < 3) {
                resultsDiv.style.display = 'none';
                clearGeocodingData();
                return;
            }
            
            // Debounce: wait 800ms after user stops typing, then auto-geocode
            geocodingTimeout = setTimeout(() => {
                searchAddress(query);
            }, 800);
        });
        
        // Auto-geocode on blur if field has content
        locationInput.addEventListener('blur', function() {
            // Single timeout with appropriate delay
            setTimeout(() => {
                const query = this.value.trim();
                // Store current results to avoid race condition
                const resultsSnapshot = [...currentResults];
                if (query.length >= 3 && resultsSnapshot.length > 0) {
                    // Auto-select best match if not already selected
                    const latField = document.getElementById('latitude');
                    if (!latField.value || latField.value === '') {
                        selectAddress(resultsSnapshot[0], true);
                    }
                }
                // Hide suggestions
                resultsDiv.style.display = 'none';
            }, 300);
        });
        
        // Search address using geocoding API
        function searchAddress(query) {
            fetch(`geocoding_api.php?action=search&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.results.length > 0) {
                        currentResults = data.results;
                        displaySuggestions(data.results);
                        // Auto-select the best match (first result)
                        selectAddress(data.results[0], true);
                    } else {
                        currentResults = [];
                        resultsDiv.style.display = 'none';
                        clearGeocodingData();
                    }
                })
                .catch(error => {
                    console.error('Errore geocoding:', error);
                    currentResults = [];
                    resultsDiv.style.display = 'none';
                    clearGeocodingData();
                });
        }
        
        // Display address suggestions
        function displaySuggestions(results) {
            suggestionsDiv.innerHTML = '';
            
            results.forEach((result, index) => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action' + (index === 0 ? ' active' : '');
                item.innerHTML = `
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${escapeHtml(result.address)}</h6>
                        <small><i class="bi bi-geo-alt"></i> ${index === 0 ? '(selezionato)' : ''}</small>
                    </div>
                    <small class="text-muted">${escapeHtml(result.display_name)}</small>
                `;
                
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectAddress(result, false);
                    resultsDiv.style.display = 'none';
                });
                
                suggestionsDiv.appendChild(item);
            });
            
            resultsDiv.style.display = 'block';
        }
        
        // Select an address from suggestions
        function selectAddress(result, isAutomatic) {
            // Update hidden fields
            document.getElementById('latitude').value = result.latitude;
            document.getElementById('longitude').value = result.longitude;
            document.getElementById('full_address').value = result.display_name;
            document.getElementById('municipality').value = result.municipality;
            
            // Only update visible location field if user manually clicked
            if (!isAutomatic) {
                locationInput.value = result.address;
            }
            
            // Show selected address
            selectedAddressText.innerHTML = escapeHtml(result.display_name);
            if (result.municipality) {
                selectedAddressText.innerHTML += '<br><small>Comune: ' + escapeHtml(result.municipality) + '</small>';
            }
            selectedAddressDiv.style.display = 'block';
        }
        
        // Clear geocoding data
        function clearGeocodingData() {
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
            document.getElementById('full_address').value = '';
            document.getElementById('municipality').value = '';
            selectedAddressDiv.style.display = 'none';
        }
        
        // Utility: escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
