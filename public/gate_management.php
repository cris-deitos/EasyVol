<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\GateController;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('gate_management', 'view')) {
    die('Accesso negato');
}

$controller = new GateController($app->getDb(), $app->getConfig());
$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];

// Get system status and gates
$systemStatus = $controller->getSystemStatus();
$gates = $controller->getAllGates();
$association = $app->getAssociation();

$pageTitle = 'Gestione Varchi - Sistema Conta Persone';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo $isCoUser ? 'EasyCO' : 'EasyVol'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <?php if ($isCoUser): ?>
        <link rel="stylesheet" href="../assets/css/easyco.css">
    <?php endif; ?>
    <style>
        #map {
            height: 600px;
            border-radius: 8px;
        }
        .status-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-indicator.active {
            background-color: #28a745;
            animation: pulse 2s infinite;
        }
        .status-indicator.inactive {
            background-color: #dc3545;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .gate-marker-label {
            background: white;
            padding: 5px;
            border-radius: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-size: 12px;
        }
        .limit-exceeded {
            background-color: #fff3cd;
        }
    </style>
</head>
<body>
    <?php 
    if ($isCoUser) {
        include '../src/Views/includes/navbar_operations.php';
    } else {
        include '../src/Views/includes/navbar.php';
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            if ($isCoUser) {
                include '../src/Views/includes/sidebar_operations.php';
            } else {
                include '../src/Views/includes/sidebar.php';
            }
            ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-door-open"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dispatch.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna alla Centrale Operativa
                        </a>
                    </div>
                </div>

                <!-- System Status -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5>
                                    <span class="status-indicator <?php echo $systemStatus['is_active'] ? 'active' : 'inactive'; ?>"></span>
                                    Sistema Gestione Varchi: 
                                    <strong id="system-status-text">
                                        <?php echo $systemStatus['is_active'] ? 'ATTIVO' : 'DISATTIVO'; ?>
                                    </strong>
                                </h5>
                            </div>
                            <div class="col-md-6 text-end">
                                <?php if ($app->checkPermission('gate_management', 'edit')): ?>
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" id="systemToggle" 
                                               <?php echo $systemStatus['is_active'] ? 'checked' : ''; ?>
                                               style="width: 60px; height: 30px; cursor: pointer;">
                                        <label class="form-check-label ms-2" for="systemToggle">
                                            <span id="toggle-label"><?php echo $systemStatus['is_active'] ? 'Disattiva' : 'Attiva'; ?></span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="gateTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="gates-list-tab" data-bs-toggle="tab" 
                                data-bs-target="#gates-list" type="button" role="tab">
                            <i class="bi bi-list-ul"></i> Elenco Varchi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="gates-map-tab" data-bs-toggle="tab" 
                                data-bs-target="#gates-map" type="button" role="tab">
                            <i class="bi bi-map"></i> Mappa Varchi
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="gateTabsContent">
                    <!-- Gates List Tab -->
                    <div class="tab-pane fade show active" id="gates-list" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Gestione Varchi</h5>
                                <?php if ($app->checkPermission('gate_management', 'edit')): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gateModal" onclick="openGateModal()">
                                        <i class="bi bi-plus-lg"></i> Aggiungi Varco
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="gatesTable">
                                        <thead>
                                            <tr>
                                                <th>Nr Varco</th>
                                                <th>Nome</th>
                                                <th>Stato</th>
                                                <th>GPS</th>
                                                <th>Limite A</th>
                                                <th>Limite B</th>
                                                <th>Limite C</th>
                                                <th>Limite Manuale</th>
                                                <th>Limite in Uso</th>
                                                <th>Num. Persone</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody id="gates-tbody">
                                            <!-- Gates will be loaded here via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gates Map Tab -->
                    <div class="tab-pane fade" id="gates-map" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Mappa Posizioni Varchi</h5>
                                <a href="gate_map_fullscreen.php" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-box-arrow-up-right"></i> Apri a Schermo Intero
                                </a>
                            </div>
                            <div class="card-body">
                                <div id="map"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Gate Modal -->
    <div class="modal fade" id="gateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gateModalTitle">Aggiungi Varco</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="gateForm">
                        <input type="hidden" id="gate-id" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gate-number" class="form-label">Nr Varco *</label>
                                <input type="text" class="form-control" id="gate-number" name="gate_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gate-name" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="gate-name" name="name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="gate-status" class="form-label">Stato</label>
                                <select class="form-select" id="gate-status" name="status">
                                    <option value="aperto">Aperto</option>
                                    <option value="chiuso">Chiuso</option>
                                    <option value="non_gestito" selected>Non Gestito</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="gate-latitude" class="form-label">Latitudine</label>
                                <input type="number" step="0.00000001" class="form-control" id="gate-latitude" name="latitude">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="gate-longitude" class="form-label">Longitudine</label>
                                <input type="number" step="0.00000001" class="form-control" id="gate-longitude" name="longitude">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="gate-limit-a" class="form-label">Limite A</label>
                                <input type="number" min="0" class="form-control" id="gate-limit-a" name="limit_a" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="gate-limit-b" class="form-label">Limite B</label>
                                <input type="number" min="0" class="form-control" id="gate-limit-b" name="limit_b" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="gate-limit-c" class="form-label">Limite C</label>
                                <input type="number" min="0" class="form-control" id="gate-limit-c" name="limit_c" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="gate-limit-manual" class="form-label">Limite Manuale</label>
                                <input type="number" min="0" class="form-control" id="gate-limit-manual" name="limit_manual" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gate-limit-in-use" class="form-label">Limite in Uso</label>
                                <select class="form-select" id="gate-limit-in-use" name="limit_in_use">
                                    <option value="a">A</option>
                                    <option value="b">B</option>
                                    <option value="c">C</option>
                                    <option value="manual" selected>Manuale</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gate-people-count" class="form-label">Numero Persone</label>
                                <input type="number" min="0" class="form-control" id="gate-people-count" name="people_count" value="0">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="saveGate()">Salva</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Conferma Eliminazione</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">ATTENZIONE! Stai per eliminare il varco:</p>
                    <p id="delete-gate-info"></p>
                    <p>Questa operazione non può essere annullata. Sei sicuro di voler procedere?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" onclick="confirmDeleteGate()">
                        <i class="bi bi-trash"></i> Elimina Definitivamente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let markers = [];
        let gateToDelete = null;
        let refreshInterval;

        // Helper function to clear refresh interval
        function clearRefreshInterval() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }

        // Initialize map
        function initMap() {
            if (!map) {
                map = L.map('map').setView([45.4642, 9.1900], 13); // Default to Milan
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
            }
        }

        // Load gates data
        function loadGates() {
            fetch('api/gates.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateGatesTable(data.gates);
                        updateMapMarkers(data.gates);
                    }
                })
                .catch(error => console.error('Error loading gates:', error));
        }

        // Update gates table
        function updateGatesTable(gates) {
            const tbody = document.getElementById('gates-tbody');
            if (!gates || gates.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center">Nessun varco configurato</td></tr>';
                return;
            }

            tbody.innerHTML = gates.map(gate => {
                const currentLimit = getCurrentLimit(gate);
                const isExceeded = gate.people_count > currentLimit;
                const rowClass = isExceeded ? 'limit-exceeded' : '';
                
                return `
                    <tr class="${rowClass}">
                        <td>${escapeHtml(gate.gate_number)}</td>
                        <td>${escapeHtml(gate.name)}</td>
                        <td><span class="badge bg-${getStatusColor(gate.status)}">${getStatusLabel(gate.status)}</span></td>
                        <td>${gate.latitude && gate.longitude ? `${gate.latitude}, ${gate.longitude}` : '-'}</td>
                        <td>${gate.limit_a}</td>
                        <td>${gate.limit_b}</td>
                        <td>${gate.limit_c}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm" value="${gate.limit_manual}" 
                                   onchange="updateLimitManual(${gate.id}, this.value)" style="width: 80px;">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" onchange="updateLimitInUse(${gate.id}, this.value)" style="width: 100px;">
                                <option value="a" ${gate.limit_in_use === 'a' ? 'selected' : ''}>A (${gate.limit_a})</option>
                                <option value="b" ${gate.limit_in_use === 'b' ? 'selected' : ''}>B (${gate.limit_b})</option>
                                <option value="c" ${gate.limit_in_use === 'c' ? 'selected' : ''}>C (${gate.limit_c})</option>
                                <option value="manual" ${gate.limit_in_use === 'manual' ? 'selected' : ''}>Manuale (${gate.limit_manual})</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm" value="${gate.people_count}" 
                                   onchange="updatePeopleCount(${gate.id}, this.value)" style="width: 80px;">
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning me-1" onclick="editGate(${gate.id})" title="Modifica">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteGate(${gate.id}, '${escapeHtml(gate.gate_number)}', '${escapeHtml(gate.name)}')" title="Elimina">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Update map markers
        function updateMapMarkers(gates) {
            // Clear existing markers
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];

            if (!gates || gates.length === 0) return;

            let bounds = [];
            gates.forEach(gate => {
                if (gate.latitude && gate.longitude) {
                    const lat = parseFloat(gate.latitude);
                    const lng = parseFloat(gate.longitude);
                    bounds.push([lat, lng]);

                    const color = gate.status === 'aperto' ? 'green' : (gate.status === 'chiuso' ? 'red' : 'gray');
                    const currentLimit = getCurrentLimit(gate);
                    
                    const icon = L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background-color: ${color}; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`
                    });

                    const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
                    marker.bindTooltip(`
                        <div class="gate-marker-label">
                            <strong>Nr. ${gate.gate_number}: ${gate.name}</strong><br>
                            Limite: ${currentLimit} (${gate.limit_in_use.toUpperCase()})<br>
                            Persone: ${gate.people_count}
                        </div>
                    `, { permanent: false, direction: 'top' });
                    
                    markers.push(marker);
                }
            });

            // Fit map to bounds
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

        // Get current limit for a gate
        function getCurrentLimit(gate) {
            switch (gate.limit_in_use) {
                case 'a': return gate.limit_a;
                case 'b': return gate.limit_b;
                case 'c': return gate.limit_c;
                case 'manual': return gate.limit_manual;
                default: return gate.limit_manual;
            }
        }

        // Get status color
        function getStatusColor(status) {
            switch (status) {
                case 'aperto': return 'success';
                case 'chiuso': return 'danger';
                case 'non_gestito': return 'secondary';
                default: return 'secondary';
            }
        }

        // Get status label
        function getStatusLabel(status) {
            switch (status) {
                case 'aperto': return 'Aperto';
                case 'chiuso': return 'Chiuso';
                case 'non_gestito': return 'Non Gestito';
                default: return status;
            }
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Toggle system status
        document.getElementById('systemToggle')?.addEventListener('change', function() {
            const isActive = this.checked;
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_system', is_active: isActive })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('system-status-text').textContent = isActive ? 'ATTIVO' : 'DISATTIVO';
                    document.getElementById('toggle-label').textContent = isActive ? 'Disattiva' : 'Attiva';
                    const indicator = document.querySelector('.status-indicator');
                    indicator.classList.toggle('active', isActive);
                    indicator.classList.toggle('inactive', !isActive);
                } else {
                    alert('Errore nell\'aggiornamento dello stato del sistema');
                    this.checked = !isActive;
                }
            });
        });

        // Open gate modal
        function openGateModal(gateId = null) {
            document.getElementById('gateForm').reset();
            document.getElementById('gate-id').value = '';
            document.getElementById('gateModalTitle').textContent = 'Aggiungi Varco';
            
            if (gateId) {
                fetch(`api/gates.php?action=get&id=${gateId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.gate) {
                            const gate = data.gate;
                            document.getElementById('gate-id').value = gate.id;
                            document.getElementById('gate-number').value = gate.gate_number;
                            document.getElementById('gate-name').value = gate.name;
                            document.getElementById('gate-status').value = gate.status;
                            document.getElementById('gate-latitude').value = gate.latitude || '';
                            document.getElementById('gate-longitude').value = gate.longitude || '';
                            document.getElementById('gate-limit-a').value = gate.limit_a;
                            document.getElementById('gate-limit-b').value = gate.limit_b;
                            document.getElementById('gate-limit-c').value = gate.limit_c;
                            document.getElementById('gate-limit-manual').value = gate.limit_manual;
                            document.getElementById('gate-limit-in-use').value = gate.limit_in_use;
                            document.getElementById('gate-people-count').value = gate.people_count;
                            document.getElementById('gateModalTitle').textContent = 'Modifica Varco';
                        }
                    });
            }
        }

        // Edit gate
        function editGate(gateId) {
            openGateModal(gateId);
            new bootstrap.Modal(document.getElementById('gateModal')).show();
        }

        // Save gate
        function saveGate() {
            const form = document.getElementById('gateForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            const gateId = data.id;
            
            data.action = gateId ? 'update' : 'create';
            
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('gateModal')).hide();
                    loadGates();
                } else {
                    alert(data.message || 'Errore nel salvataggio del varco');
                }
            });
        }

        // Delete gate
        function deleteGate(gateId, gateNumber, gateName) {
            gateToDelete = gateId;
            document.getElementById('delete-gate-info').innerHTML = `
                <strong>Nr. ${gateNumber}: ${gateName}</strong>
            `;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
            
            // Add flashing animation
            const modal = document.getElementById('deleteModal');
            modal.querySelector('.modal-header').style.animation = 'flash 1s infinite';
        }

        // Confirm delete gate
        function confirmDeleteGate() {
            if (!gateToDelete) return;
            
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: gateToDelete })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    loadGates();
                } else {
                    alert(data.message || 'Errore nell\'eliminazione del varco');
                }
            });
        }

        // Update limit manual
        function updateLimitManual(gateId, value) {
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update', id: gateId, limit_manual: value })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Errore nell\'aggiornamento del limite');
                    loadGates();
                }
            });
        }

        // Update limit in use
        function updateLimitInUse(gateId, value) {
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update', id: gateId, limit_in_use: value })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Errore nell\'aggiornamento del limite in uso');
                }
                loadGates();
            });
        }

        // Update people count
        function updatePeopleCount(gateId, value) {
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set_count', id: gateId, count: value })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Errore nell\'aggiornamento del conteggio');
                    loadGates();
                }
            });
        }

        // Initialize map when tab is shown
        document.getElementById('gates-map-tab').addEventListener('shown.bs.tab', function () {
            if (!map) {
                initMap();
                loadGates();
            }
        });

        // Auto-refresh map every 5 seconds when visible
        document.getElementById('gates-map-tab').addEventListener('shown.bs.tab', function () {
            clearRefreshInterval();
            refreshInterval = setInterval(loadGates, 5000);
        });

        document.getElementById('gates-map-tab').addEventListener('hidden.bs.tab', function () {
            clearRefreshInterval();
        });

        // Auto-refresh list every 3 seconds when visible
        document.getElementById('gates-list-tab').addEventListener('shown.bs.tab', function () {
            clearRefreshInterval();
            refreshInterval = setInterval(loadGates, 3000);
        });

        document.getElementById('gates-list-tab').addEventListener('hidden.bs.tab', function () {
            clearRefreshInterval();
        });

        // Initial load
        document.addEventListener('DOMContentLoaded', function() {
            loadGates();
            // Start auto-refresh for list tab (which is the default active tab in the HTML)
            refreshInterval = setInterval(loadGates, 3000);
        });
    </script>
</body>
</html>
