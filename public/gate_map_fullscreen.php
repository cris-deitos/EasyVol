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
$association = $app->getAssociation();

$pageTitle = 'Mappa Varchi - Schermo Intero';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        
        #map {
            height: 100vh;
            width: 100vw;
        }
        
        .back-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <div id="map"></div>
    <a href="gate_management.php" class="back-button">✕ Chiudi</a>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let markers = [];

        // Initialize map
        function initMap() {
            map = L.map('map').setView([45.4642, 9.1900], 13); // Default to Milan
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
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
                        html: `<div style="background-color: ${color}; width: 40px; height: 40px; border-radius: 50%; border: 4px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4);"></div>`
                    });

                    const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
                    marker.bindTooltip(`
                        <div style="background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); font-size: 14px;">
                            <strong style="font-size: 16px;">Nr. ${gate.gate_number}: ${gate.name}</strong><br>
                            Limite: ${currentLimit} (${gate.limit_in_use.toUpperCase()})<br>
                            Persone: ${gate.people_count}
                        </div>
                    `, { permanent: false, direction: 'top' });
                    
                    markers.push(marker);
                }
            });

            // Fit map to bounds
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [100, 100] });
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

        // Load gates
        function loadGates() {
            fetch('api/gates.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateMapMarkers(data.gates);
                    }
                })
                .catch(error => console.error('Error loading gates:', error));
        }

        // Initialize
        initMap();
        loadGates();

        // Auto-refresh every 5 seconds
        setInterval(loadGates, 5000);
    </script>
</body>
</html>
