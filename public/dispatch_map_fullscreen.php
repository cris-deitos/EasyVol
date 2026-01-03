<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

$pageTitle = 'Mappa Radio - Fullscreen';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }
        #map {
            width: 100%;
            height: 100%;
        }
        .radio-marker-tooltip {
            font-size: 12px;
        }
        .map-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        .map-controls button {
            padding: 5px 10px;
            cursor: pointer;
            border: 1px solid #ccc;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .map-controls button:hover {
            background: #e2e6ea;
        }
    </style>
</head>
<body>
    <div class="map-controls">
        <button onclick="window.close()" aria-label="Chiudi mappa fullscreen">
            Chiudi
        </button>
    </div>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const map = L.map('map').setView([45.4642, 9.1900], 10); // Default to Milan, Italy
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        const radioMarkers = {};
        
        // Update radio positions on map
        async function updateRadioPositions() {
            try {
                const response = await fetch('api/dispatch_positions.php');
                const positions = await response.json();
                
                // Remove old markers
                const currentDmrIds = new Set(positions.map(p => p.radio_dmr_id));
                for (const dmrId in radioMarkers) {
                    if (!currentDmrIds.has(dmrId)) {
                        map.removeLayer(radioMarkers[dmrId]);
                        delete radioMarkers[dmrId];
                    }
                }
                
                // Add/update markers
                positions.forEach(pos => {
                    const tooltipContent = `
                        <div class="radio-marker-tooltip">
                            <strong>ID Radio:</strong> ${pos.radio_dmr_id}<br>
                            <strong>Nome Radio:</strong> ${pos.radio_name || 'N/A'}<br>
                            ${pos.first_name && pos.last_name ? 
                                `<strong>Assegnatario:</strong> ${pos.first_name} ${pos.last_name}<br>` : ''}
                            ${pos.organization ? `<strong>Ente:</strong> ${pos.organization}<br>` : ''}
                            ${pos.phone ? `<strong>Telefono:</strong> ${pos.phone}<br>` : ''}
                            <strong>Data/Ora:</strong> ${pos.timestamp}<br>
                            <strong>Coordinate:</strong> ${pos.latitude}, ${pos.longitude}
                        </div>
                    `;
                    
                    if (radioMarkers[pos.radio_dmr_id]) {
                        // Update existing marker
                        radioMarkers[pos.radio_dmr_id].setLatLng([pos.latitude, pos.longitude]);
                        radioMarkers[pos.radio_dmr_id].setTooltipContent(tooltipContent);
                    } else {
                        // Create new marker
                        const marker = L.marker([pos.latitude, pos.longitude], {
                            icon: L.icon({
                                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41]
                            })
                        }).addTo(map);
                        
                        marker.bindTooltip(tooltipContent);
                        radioMarkers[pos.radio_dmr_id] = marker;
                    }
                });
            } catch (error) {
                console.error('Error updating radio positions:', error);
            }
        }
        
        // Initialize and start polling
        updateRadioPositions();
        
        // Update every 5 seconds
        setInterval(updateRadioPositions, 5000);
    </script>
</body>
</html>
