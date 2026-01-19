<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];

$pageTitle = 'Monitoraggio Terremoti - Italia';
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
        #earthquakeMap {
            height: 70vh;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .earthquake-controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .earthquake-list {
            max-height: 400px;
            overflow-y: auto;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .earthquake-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: background 0.2s;
        }
        .earthquake-item:hover {
            background: #f8f9fa;
        }
        .earthquake-item:last-child {
            border-bottom: none;
        }
        .magnitude-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            min-width: 50px;
            text-align: center;
        }
        .magnitude-1 { background-color: #28a745; }
        .magnitude-2 { background-color: #5cb85c; }
        .magnitude-3 { background-color: #ffc107; }
        .magnitude-4 { background-color: #fd7e14; }
        .magnitude-5 { background-color: #dc3545; }
        .magnitude-6 { background-color: #bd2130; }
        
        .legend {
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .legend-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid #333;
        }
        .loading-indicator {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .stats-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #dc3545;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
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
                    <h1 class="h2"><i class="bi bi-globe"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="operations_center.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Torna alla Centrale Operativa
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadEarthquakes()">
                                <i class="bi bi-arrow-clockwise"></i> Aggiorna
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-card">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-value" id="totalCount">0</div>
                                <div class="stat-label">Terremoti (24h)</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-value" id="maxMagnitude">0.0</div>
                                <div class="stat-label">Magnitudo Massima</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-value text-warning" id="significantCount">0</div>
                                <div class="stat-label">Rilevanti (M ≥ 3.0)</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-value text-info" id="lastUpdate">--:--</div>
                                <div class="stat-label">Ultimo Aggiornamento</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="earthquake-controls">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="filter-section">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label"><small>Periodo:</small></label>
                                        <select class="form-select form-select-sm" id="timeFilter" onchange="loadEarthquakes()">
                                            <option value="24">Ultime 24 ore</option>
                                            <option value="48">Ultime 48 ore</option>
                                            <option value="168">Ultima settimana</option>
                                            <option value="720">Ultimo mese</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label"><small>Magnitudo minima:</small></label>
                                        <select class="form-select form-select-sm" id="magnitudeFilter" onchange="filterEarthquakes()">
                                            <option value="1">M ≥ 1.0 (Tutte)</option>
                                            <option value="2">M ≥ 2.0</option>
                                            <option value="3">M ≥ 3.0</option>
                                            <option value="4">M ≥ 4.0</option>
                                            <option value="5">M ≥ 5.0</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label"><small>Ricerca località:</small></label>
                                        <input type="text" class="form-control form-control-sm" id="searchLocation" 
                                               placeholder="Cerca località..." onkeyup="filterEarthquakes()">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="legend">
                                <strong>Legenda Magnitudo:</strong>
                                <div class="legend-item">
                                    <div class="legend-marker magnitude-1"></div>
                                    <span>M &lt; 2.0 - Molto Leggera</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-marker magnitude-2"></div>
                                    <span>2.0 ≤ M &lt; 3.0 - Leggera</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-marker magnitude-3"></div>
                                    <span>3.0 ≤ M &lt; 4.0 - Moderata</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-marker magnitude-4"></div>
                                    <span>4.0 ≤ M &lt; 5.0 - Forte</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-marker magnitude-5"></div>
                                    <span>M ≥ 5.0 - Molto Forte</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Map -->
                    <div class="col-md-8">
                        <div class="position-relative">
                            <div id="earthquakeMap"></div>
                            <div class="loading-indicator" id="loadingIndicator" style="display: none;">
                                <div class="spinner-border text-danger" role="status">
                                    <span class="visually-hidden">Caricamento...</span>
                                </div>
                                <div class="mt-2">Caricamento dati sismici...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Earthquake List -->
                    <div class="col-md-4">
                        <div class="earthquake-list">
                            <h5 class="mb-3">
                                <i class="bi bi-list-ul"></i> Eventi Sismici 
                                <span class="badge bg-danger" id="listCount">0</span>
                            </h5>
                            <div id="earthquakeListContent">
                                <p class="text-muted text-center">Caricamento dati...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map centered on Italy
        const map = L.map('earthquakeMap').setView([42.5, 12.5], 6);

        // Add base map layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Italy bounds
        const italyBounds = [
            [36.0, 6.5],  // Southwest corner (Sicily)
            [47.5, 19.0]  // Northeast corner (Alps)
        ];
        map.fitBounds(italyBounds);

        let earthquakesData = [];
        let markers = [];
        let markersLayer = L.layerGroup().addTo(map);

        // Get magnitude color
        function getMagnitudeColor(magnitude) {
            if (magnitude < 2.0) return '#28a745';
            if (magnitude < 3.0) return '#5cb85c';
            if (magnitude < 4.0) return '#ffc107';
            if (magnitude < 5.0) return '#fd7e14';
            if (magnitude < 6.0) return '#dc3545';
            return '#bd2130';
        }

        // Get magnitude class
        function getMagnitudeClass(magnitude) {
            if (magnitude < 2.0) return 'magnitude-1';
            if (magnitude < 3.0) return 'magnitude-2';
            if (magnitude < 4.0) return 'magnitude-3';
            if (magnitude < 5.0) return 'magnitude-4';
            if (magnitude < 6.0) return 'magnitude-5';
            return 'magnitude-6';
        }

        // Format date for display
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('it-IT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Load earthquakes from INGV API
        async function loadEarthquakes() {
            document.getElementById('loadingIndicator').style.display = 'block';
            
            try {
                const hours = document.getElementById('timeFilter').value;
                const startTime = new Date();
                startTime.setHours(startTime.getHours() - hours);
                const startTimeISO = startTime.toISOString();
                
                // INGV Earthquake API
                // Documentation: https://webservices.ingv.it/fdsnws/event/1/
                // Using minmag=1.0 for better performance and to filter out micro-seismic events
                const apiUrl = `https://webservices.ingv.it/fdsnws/event/1/query?starttime=${startTimeISO}&format=geojson&minmag=1.0&orderby=time-desc`;
                
                const response = await fetch(apiUrl);
                
                if (!response.ok) {
                    throw new Error('Errore nel caricamento dei dati INGV');
                }
                
                const data = await response.json();
                earthquakesData = data.features || [];
                
                // Update statistics
                updateStatistics();
                
                // Display earthquakes
                filterEarthquakes();
                
                // Update last update time
                document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('it-IT', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
            } catch (error) {
                console.error('Error loading earthquakes:', error);
                document.getElementById('earthquakeListContent').innerHTML = 
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-exclamation-triangle"></i> <strong>Errore nel caricamento dei dati INGV.</strong><br>' +
                    '<small>Verificare la connessione internet e riprovare.</small><br>' +
                    '<small>Servizio INGV: <a href="https://webservices.ingv.it" target="_blank">https://webservices.ingv.it</a></small>' +
                    '</div>';
                
                // Update statistics to show error
                document.getElementById('totalCount').textContent = '--';
                document.getElementById('maxMagnitude').textContent = '--';
                document.getElementById('significantCount').textContent = '--';
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        // Update statistics
        function updateStatistics() {
            const total = earthquakesData.length;
            let maxMag = 0;
            let significant = 0;
            
            earthquakesData.forEach(eq => {
                const mag = eq.properties.mag || 0;
                if (mag > maxMag) maxMag = mag;
                if (mag >= 3.0) significant++;
            });
            
            document.getElementById('totalCount').textContent = total;
            document.getElementById('maxMagnitude').textContent = maxMag.toFixed(1);
            document.getElementById('significantCount').textContent = significant;
        }

        // Filter and display earthquakes
        function filterEarthquakes() {
            const minMagnitude = parseFloat(document.getElementById('magnitudeFilter').value);
            const searchText = document.getElementById('searchLocation').value.toLowerCase();
            
            // Filter earthquakes
            const filtered = earthquakesData.filter(eq => {
                const mag = eq.properties.mag || 0;
                const location = (eq.properties.place || '').toLowerCase();
                
                return mag >= minMagnitude && 
                       (searchText === '' || location.includes(searchText));
            });
            
            // Clear existing markers
            markersLayer.clearLayers();
            
            // Check if we should show only last 24 hours on map
            const mapTimeLimit = 24; // hours
            const mapCutoff = new Date();
            mapCutoff.setHours(mapCutoff.getHours() - mapTimeLimit);
            
            // Add markers for last 24 hours only
            filtered.forEach(eq => {
                const coords = eq.geometry.coordinates;
                const lat = coords[1];
                const lon = coords[0];
                const depth = coords[2];
                const magnitude = eq.properties.mag || 0;
                const location = eq.properties.place || 'Località sconosciuta';
                const time = new Date(eq.properties.time);
                
                // Only show on map if within last 24 hours
                if (time >= mapCutoff) {
                    const color = getMagnitudeColor(magnitude);
                    const radius = Math.max(5, magnitude * 3);
                    
                    const marker = L.circleMarker([lat, lon], {
                        radius: radius,
                        fillColor: color,
                        color: '#333',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.7
                    });
                    
                    const popupContent = `
                        <strong>${location}</strong><br>
                        <strong>Magnitudo:</strong> ${magnitude.toFixed(1)}<br>
                        <strong>Profondità:</strong> ${depth.toFixed(1)} km<br>
                        <strong>Data/Ora:</strong> ${formatDate(time)}<br>
                        <strong>Coordinate:</strong> ${lat.toFixed(4)}, ${lon.toFixed(4)}
                    `;
                    
                    marker.bindPopup(popupContent);
                    marker.addTo(markersLayer);
                }
            });
            
            // Update list
            displayEarthquakeList(filtered);
        }

        // Display earthquake list
        function displayEarthquakeList(earthquakes) {
            document.getElementById('listCount').textContent = earthquakes.length;
            
            if (earthquakes.length === 0) {
                document.getElementById('earthquakeListContent').innerHTML = 
                    '<p class="text-muted text-center">Nessun terremoto trovato con i filtri selezionati.</p>';
                return;
            }
            
            let html = '';
            earthquakes.forEach((eq, index) => {
                const magnitude = eq.properties.mag || 0;
                const location = eq.properties.place || 'Località sconosciuta';
                const time = new Date(eq.properties.time);
                const coords = eq.geometry.coordinates;
                const lat = coords[1];
                const lon = coords[0];
                const depth = coords[2];
                
                const magnitudeClass = getMagnitudeClass(magnitude);
                
                html += `
                    <div class="earthquake-item" onclick="zoomToEarthquake(${lat}, ${lon})">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="magnitude-badge ${magnitudeClass}">M ${magnitude.toFixed(1)}</span>
                            <small class="text-muted">${formatDate(time)}</small>
                        </div>
                        <div>
                            <strong>${location}</strong><br>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt"></i> ${lat.toFixed(4)}, ${lon.toFixed(4)} - 
                                Profondità: ${depth.toFixed(1)} km
                            </small>
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('earthquakeListContent').innerHTML = html;
        }

        // Zoom to earthquake on map
        function zoomToEarthquake(lat, lon) {
            map.setView([lat, lon], 10);
            
            // Find and open the marker popup
            // Using simple iteration - for large datasets, consider maintaining a coordinate-to-marker map
            markersLayer.eachLayer(layer => {
                if (layer.getLatLng && 
                    Math.abs(layer.getLatLng().lat - lat) < 0.001 && 
                    Math.abs(layer.getLatLng().lng - lon) < 0.001) {
                    layer.openPopup();
                }
            });
        }

        // Load earthquakes on page load
        loadEarthquakes();

        // Auto-refresh every 5 minutes
        setInterval(loadEarthquakes, 5 * 60 * 1000);
    </script>
    <script src="../assets/js/notifications-auto-update.js"></script>
</body>
</html>
