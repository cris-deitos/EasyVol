<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\StructureController;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('structure_management', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$controller = new StructureController($app->getDb(), $app->getConfig());
$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];

// Get structures
$structures = $controller->getAllStructures();
$association = $app->getAssociation();

$pageTitle = 'Gestione Strutture';
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
        .structure-marker-label {
            background: white;
            padding: 5px;
            border-radius: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-size: 12px;
        }
        .address-suggestions {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .address-suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .address-suggestion-item:hover {
            background-color: #f8f9fa;
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
                    <h1 class="h2"><i class="bi bi-building"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="structureTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="structures-list-tab" data-bs-toggle="tab" 
                                data-bs-target="#structures-list" type="button" role="tab">
                            <i class="bi bi-list-ul"></i> Elenco Strutture
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="structures-map-tab" data-bs-toggle="tab" 
                                data-bs-target="#structures-map" type="button" role="tab">
                            <i class="bi bi-map"></i> Mappa Strutture
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="structureTabsContent">
                    <!-- Structures List Tab -->
                    <div class="tab-pane fade show active" id="structures-list" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Elenco Strutture</h5>
                                <?php if ($app->checkPermission('structure_management', 'edit')): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#structureModal" onclick="openStructureModal()">
                                        <i class="bi bi-plus-lg"></i> Aggiungi Struttura
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="structuresTable">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Tipologia</th>
                                                <th>Indirizzo</th>
                                                <th>Proprietario</th>
                                                <th>GPS</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody id="structures-tbody">
                                            <!-- Structures will be loaded here via JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Structures Map Tab -->
                    <div class="tab-pane fade" id="structures-map" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Mappa Posizioni Strutture</h5>
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

    <!-- Structure Modal -->
    <div class="modal fade" id="structureModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="structureModalTitle">Aggiungi Struttura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="structureForm">
                        <input type="hidden" id="structure-id" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="structure-name" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="structure-name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="structure-type" class="form-label">Tipologia</label>
                                <input type="text" class="form-control" id="structure-type" name="type">
                            </div>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="structure-address" class="form-label">Indirizzo Completo</label>
                            <input type="text" class="form-control" id="structure-address" name="full_address" autocomplete="off">
                            <div id="address-suggestions" class="address-suggestions" style="display: none;"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="structure-latitude" class="form-label">Latitudine GPS</label>
                                <input type="number" step="0.00000001" class="form-control" id="structure-latitude" name="latitude">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="structure-longitude" class="form-label">Longitudine GPS</label>
                                <input type="number" step="0.00000001" class="form-control" id="structure-longitude" name="longitude">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="structure-owner" class="form-label">Proprietario</label>
                            <input type="text" class="form-control" id="structure-owner" name="owner">
                        </div>
                        <div class="mb-3">
                            <label for="structure-owner-contacts" class="form-label">Contatti Proprietario</label>
                            <textarea class="form-control" id="structure-owner-contacts" name="owner_contacts" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="structure-contracts" class="form-label">Contratti e Scadenze</label>
                            <textarea class="form-control" id="structure-contracts" name="contracts_deadlines" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="structure-keys" class="form-label">Chiavi e Codici</label>
                            <textarea class="form-control" id="structure-keys" name="keys_codes" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="structure-notes" class="form-label">Note</label>
                            <textarea class="form-control" id="structure-notes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="saveStructure()">Salva</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Structure Modal -->
    <div class="modal fade" id="viewStructureModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scheda Struttura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="view-structure-content">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
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
                    <p class="text-danger fw-bold">ATTENZIONE! Stai per eliminare la struttura:</p>
                    <p id="delete-structure-info"></p>
                    <p>Questa operazione non può essere annullata. Sei sicuro di voler procedere?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" onclick="confirmDeleteStructure()">
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
        let structureToDelete = null;
        let addressSearchTimeout = null;

        // Initialize map
        function initMap() {
            if (!map) {
                map = L.map('map').setView([45.4642, 9.1900], 13); // Default to Milan
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
            }
        }

        // Load structures data
        function loadStructures() {
            fetch('api/structures.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStructuresTable(data.structures);
                        updateMapMarkers(data.structures);
                    }
                })
                .catch(error => console.error('Error loading structures:', error));
        }

        // Update structures table
        function updateStructuresTable(structures) {
            const tbody = document.getElementById('structures-tbody');
            if (!structures || structures.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nessuna struttura configurata</td></tr>';
                return;
            }

            tbody.innerHTML = structures.map(structure => {
                return `
                    <tr>
                        <td>${escapeHtml(structure.name)}</td>
                        <td>${escapeHtml(structure.type || '-')}</td>
                        <td>${escapeHtml(structure.full_address || '-')}</td>
                        <td>${escapeHtml(structure.owner || '-')}</td>
                        <td>${structure.latitude && structure.longitude ? `${structure.latitude}, ${structure.longitude}` : '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-info me-1" onclick="viewStructure(${structure.id})" title="Visualizza">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($app->checkPermission('structure_management', 'edit')): ?>
                            <button class="btn btn-sm btn-warning me-1" onclick="editStructure(${structure.id})" title="Modifica">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($app->checkPermission('structure_management', 'delete')): ?>
                            <button class="btn btn-sm btn-danger" onclick="deleteStructure(${structure.id}, ${JSON.stringify(structure.name)})" title="Elimina">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Update map markers
        function updateMapMarkers(structures) {
            // Clear existing markers
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];

            if (!structures || structures.length === 0) return;

            let bounds = [];
            structures.forEach(structure => {
                if (structure.latitude && structure.longitude) {
                    const lat = parseFloat(structure.latitude);
                    const lng = parseFloat(structure.longitude);
                    bounds.push([lat, lng]);

                    const icon = L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background-color: #0d6efd; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`
                    });

                    const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
                    marker.bindTooltip(`
                        <div class="structure-marker-label">
                            <strong>${escapeHtml(structure.name)}</strong><br>
                            ${structure.type ? escapeHtml(structure.type) + '<br>' : ''}
                            ${structure.full_address ? escapeHtml(structure.full_address) : ''}
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

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Address autocomplete
        document.getElementById('structure-address')?.addEventListener('input', function(e) {
            const query = e.target.value;
            
            clearTimeout(addressSearchTimeout);
            
            if (query.length < 3) {
                document.getElementById('address-suggestions').style.display = 'none';
                return;
            }
            
            addressSearchTimeout = setTimeout(() => {
                fetch(`geocoding_api.php?action=search&q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.results && data.results.length > 0) {
                            showAddressSuggestions(data.results);
                        } else {
                            document.getElementById('address-suggestions').style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error searching addresses:', error);
                        document.getElementById('address-suggestions').style.display = 'none';
                    });
            }, 300);
        });

        // Show address suggestions
        function showAddressSuggestions(results) {
            const container = document.getElementById('address-suggestions');
            container.innerHTML = results.map(result => `
                <div class="address-suggestion-item" onclick='selectAddress(${JSON.stringify(result)})'>
                    <strong>${escapeHtml(result.display_name)}</strong>
                </div>
            `).join('');
            container.style.display = 'block';
        }

        // Select address from suggestions
        function selectAddress(result) {
            document.getElementById('structure-address').value = result.display_name;
            document.getElementById('structure-latitude').value = result.lat;
            document.getElementById('structure-longitude').value = result.lon;
            document.getElementById('address-suggestions').style.display = 'none';
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#structure-address') && !e.target.closest('#address-suggestions')) {
                document.getElementById('address-suggestions').style.display = 'none';
            }
        });

        // Open structure modal
        function openStructureModal(structureId = null) {
            document.getElementById('structureForm').reset();
            document.getElementById('structure-id').value = '';
            document.getElementById('structureModalTitle').textContent = 'Aggiungi Struttura';
            
            if (structureId) {
                fetch(`api/structures.php?action=get&id=${structureId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.structure) {
                            const structure = data.structure;
                            document.getElementById('structure-id').value = structure.id;
                            document.getElementById('structure-name').value = structure.name;
                            document.getElementById('structure-type').value = structure.type || '';
                            document.getElementById('structure-address').value = structure.full_address || '';
                            document.getElementById('structure-latitude').value = structure.latitude || '';
                            document.getElementById('structure-longitude').value = structure.longitude || '';
                            document.getElementById('structure-owner').value = structure.owner || '';
                            document.getElementById('structure-owner-contacts').value = structure.owner_contacts || '';
                            document.getElementById('structure-contracts').value = structure.contracts_deadlines || '';
                            document.getElementById('structure-keys').value = structure.keys_codes || '';
                            document.getElementById('structure-notes').value = structure.notes || '';
                            document.getElementById('structureModalTitle').textContent = 'Modifica Struttura';
                        }
                    });
            }
        }

        // View structure
        function viewStructure(structureId) {
            fetch(`api/structures.php?action=get&id=${structureId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.structure) {
                        const structure = data.structure;
                        const content = `
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <strong>Nome:</strong><br>${escapeHtml(structure.name)}
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Tipologia:</strong><br>${escapeHtml(structure.type || '-')}
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong>Indirizzo:</strong><br>${escapeHtml(structure.full_address || '-')}
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <strong>Coordinate GPS:</strong><br>
                                    ${structure.latitude && structure.longitude ? `Lat: ${structure.latitude}, Lon: ${structure.longitude}` : '-'}
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Proprietario:</strong><br>${escapeHtml(structure.owner || '-')}
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong>Contatti Proprietario:</strong><br>
                                <pre class="border p-2 bg-light">${escapeHtml(structure.owner_contacts || '-')}</pre>
                            </div>
                            <div class="mb-3">
                                <strong>Contratti e Scadenze:</strong><br>
                                <pre class="border p-2 bg-light">${escapeHtml(structure.contracts_deadlines || '-')}</pre>
                            </div>
                            <div class="mb-3">
                                <strong>Chiavi e Codici:</strong><br>
                                <pre class="border p-2 bg-light">${escapeHtml(structure.keys_codes || '-')}</pre>
                            </div>
                            <div class="mb-3">
                                <strong>Note:</strong><br>
                                <pre class="border p-2 bg-light">${escapeHtml(structure.notes || '-')}</pre>
                            </div>
                        `;
                        document.getElementById('view-structure-content').innerHTML = content;
                        new bootstrap.Modal(document.getElementById('viewStructureModal')).show();
                    }
                });
        }

        // Edit structure
        function editStructure(structureId) {
            openStructureModal(structureId);
            new bootstrap.Modal(document.getElementById('structureModal')).show();
        }

        // Helper function to normalize coordinate values
        function normalizeCoordinate(value) {
            return value === '' ? null : value;
        }

        // Save structure
        function saveStructure() {
            const form = document.getElementById('structureForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            const structureId = data.id;
            
            data.action = structureId ? 'update' : 'create';
            
            // Convert empty coordinate strings to null to avoid database errors
            // Note: We explicitly check for empty string to preserve 0 as valid value
            data.latitude = normalizeCoordinate(data.latitude);
            data.longitude = normalizeCoordinate(data.longitude);
            
            fetch('api/structures.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('structureModal')).hide();
                    loadStructures();
                } else {
                    alert(data.message || 'Errore nel salvataggio della struttura');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore nel salvataggio della struttura');
            });
        }

        // Delete structure
        function deleteStructure(structureId, structureName) {
            structureToDelete = structureId;
            const infoElement = document.getElementById('delete-structure-info');
            const nameElement = document.createElement('strong');
            nameElement.textContent = structureName;
            infoElement.innerHTML = '';
            infoElement.appendChild(nameElement);
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Confirm delete structure
        function confirmDeleteStructure() {
            if (!structureToDelete) return;
            
            fetch('api/structures.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: structureToDelete })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    loadStructures();
                } else {
                    alert(data.message || 'Errore nell\'eliminazione della struttura');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore nell\'eliminazione della struttura');
            });
        }

        // Initialize map when tab is shown
        document.getElementById('structures-map-tab').addEventListener('shown.bs.tab', function () {
            if (!map) {
                initMap();
                loadStructures();
            }
            setTimeout(() => {
                map.invalidateSize();
            }, 100);
        });

        // Initial load
        document.addEventListener('DOMContentLoaded', function() {
            loadStructures();
        });
    </script>
</body>
</html>
