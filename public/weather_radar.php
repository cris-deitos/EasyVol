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

$pageTitle = 'Radar Meteo - Nord Italia';
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
        #weatherMap {
            height: 85vh;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .weather-controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
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
        .legend-color {
            width: 30px;
            height: 20px;
            margin-right: 10px;
            border: 1px solid #ccc;
        }
        .radar-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
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
                    <h1 class="h2"><i class="bi bi-cloud-rain"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="operations_center.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Torna alla Centrale Operativa
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Aggiorna
                            </button>
                        </div>
                    </div>
                </div>

                <div class="weather-controls">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="radar-info">
                                <strong><i class="bi bi-info-circle"></i> Info:</strong>
                                <span id="lastUpdate">Caricamento dati meteo...</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="legend">
                                <strong>Legenda Precipitazioni:</strong>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #00FF00;"></div>
                                    <span>Leggere</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #FFFF00;"></div>
                                    <span>Moderate</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #FFA500;"></div>
                                    <span>Intense</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #FF0000;"></div>
                                    <span>Molto Intense</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="position-relative">
                    <div id="weatherMap"></div>
                    <div class="loading-indicator" id="loadingIndicator" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Caricamento...</span>
                        </div>
                        <div class="mt-2">Caricamento dati meteo...</div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map centered on North Italy
        const map = L.map('weatherMap').setView([45.5, 10.5], 7);

        // Add base map layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Define bounds for North Italy (Lombardia, Veneto, Trentino, Emilia Romagna)
        const northItalyBounds = [
            [43.5, 8.5],  // Southwest corner
            [47.0, 13.5]  // Northeast corner
        ];

        // Fit map to North Italy bounds
        map.fitBounds(northItalyBounds);

        // Add weather radar overlay from OpenWeatherMap
        const apiKey = 'YOUR_OPENWEATHERMAP_API_KEY'; // This should be configured in the system
        
        // Note: For production, you should use official Italian weather services APIs:
        // - Aeronautica Militare Italiana
        // - Dipartimento Protezione Civile
        // - Arpae Emilia-Romagna DEXTER API
        // - Arpa Lombardia
        // - Arpav Veneto
        
        // Using RainViewer for free weather radar (no API key required)
        let radarLayer = null;
        let currentTimestamp = null;
        
        async function loadWeatherRadar() {
            document.getElementById('loadingIndicator').style.display = 'block';
            
            try {
                // RainViewer API for weather radar
                const response = await fetch('https://api.rainviewer.com/public/weather-maps.json');
                const data = await response.json();
                
                if (data && data.radar && data.radar.past && data.radar.past.length > 0) {
                    // Get the most recent radar image
                    const latestRadar = data.radar.past[data.radar.past.length - 1];
                    currentTimestamp = latestRadar.time;
                    
                    // Remove existing radar layer if present
                    if (radarLayer) {
                        map.removeLayer(radarLayer);
                    }
                    
                    // Add new radar layer
                    const radarUrl = `https://tilecache.rainviewer.com${latestRadar.path}/256/{z}/{x}/{y}/2/1_1.png`;
                    radarLayer = L.tileLayer(radarUrl, {
                        opacity: 0.7,
                        maxZoom: 19,
                        attribution: 'Weather data &copy; <a href="https://www.rainviewer.com/">RainViewer</a>'
                    }).addTo(map);
                    
                    // Update timestamp
                    const date = new Date(currentTimestamp * 1000);
                    document.getElementById('lastUpdate').innerHTML = 
                        `<i class="bi bi-check-circle text-success"></i> Ultimo aggiornamento: ${date.toLocaleString('it-IT')} - Dati da RainViewer`;
                } else {
                    document.getElementById('lastUpdate').innerHTML = 
                        '<i class="bi bi-exclamation-triangle text-warning"></i> Nessun dato radar disponibile al momento';
                }
            } catch (error) {
                console.error('Error loading weather radar:', error);
                document.getElementById('lastUpdate').innerHTML = 
                    '<i class="bi bi-x-circle text-danger"></i> Errore nel caricamento dei dati meteo';
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        // Add region markers
        const regions = [
            { name: 'Milano (Lombardia)', lat: 45.4642, lng: 9.1900 },
            { name: 'Venezia (Veneto)', lat: 45.4408, lng: 12.3155 },
            { name: 'Trento (Trentino)', lat: 46.0664, lng: 11.1257 },
            { name: 'Bologna (Emilia Romagna)', lat: 44.4949, lng: 11.3426 }
        ];

        regions.forEach(region => {
            L.marker([region.lat, region.lng])
                .addTo(map)
                .bindPopup(`<strong>${region.name}</strong>`);
        });

        // Load weather radar on page load
        loadWeatherRadar();

        // Auto-refresh every 5 minutes
        setInterval(loadWeatherRadar, 5 * 60 * 1000);
    </script>
    <script src="../assets/js/notifications-auto-update.js"></script>
</body>
</html>
