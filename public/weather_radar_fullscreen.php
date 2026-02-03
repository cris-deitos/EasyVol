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

$pageTitle = 'Radar Meteo - Nord Italia';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }
        #weatherMap {
            height: 100vh;
            width: 100%;
        }
        .radar-info-panel {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            z-index: 1000;
            min-width: 300px;
        }
        .legend-panel {
            position: absolute;
            bottom: 20px;
            right: 10px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            z-index: 1000;
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
        .loading-indicator {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 2000;
            text-align: center;
        }
        .animation-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            z-index: 1000;
            min-width: 400px;
        }
        .time-display {
            font-size: 1.1rem;
            font-weight: bold;
            text-align: center;
            color: #0066cc;
            margin-bottom: 10px;
        }
        .progress {
            height: 8px;
            margin-bottom: 10px;
        }
        .progress-bar {
            transition: width 0.3s ease-out;
        }
        #playPauseBtn {
            width: 100px;
        }
    </style>
</head>
<body>
    <div id="weatherMap"></div>
    
    <!-- Radar Info Panel -->
    <div class="radar-info-panel">
        <h6 class="mb-2"><i class="bi bi-info-circle"></i> Informazioni Radar</h6>
        <div id="radarInfo">
            <small class="text-muted">Caricamento dati...</small>
        </div>
        <hr class="my-2">
        <button class="btn btn-sm btn-primary w-100" onclick="loadRadarData()">
            <i class="bi bi-arrow-clockwise"></i> Aggiorna
        </button>
    </div>
    
    <!-- Legend Panel -->
    <div class="legend-panel">
        <strong class="d-block mb-2">Intensità Precipitazioni</strong>
        <small class="text-muted d-block mb-2">(Colori approssimati)</small>
        <div class="legend-item">
            <div class="legend-color" style="background: rgba(0, 255, 0, 0.6);"></div>
            <small>Leggere</small>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: rgba(255, 255, 0, 0.6);"></div>
            <small>Moderate</small>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: rgba(255, 165, 0, 0.6);"></div>
            <small>Intense</small>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: rgba(255, 0, 0, 0.6);"></div>
            <small>Molto Intense</small>
        </div>
    </div>
    
    <!-- Animation Controls -->
    <div class="animation-controls">
        <div class="time-display" id="currentTimeDisplay">
            --:-- -- / -- / ----
        </div>
        <div class="progress">
            <div class="progress-bar progress-bar-striped" role="progressbar" id="animationProgress" 
                 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex justify-content-center gap-2">
            <button class="btn btn-secondary btn-sm" onclick="previousFrame()">
                <i class="bi bi-skip-backward-fill"></i> Indietro
            </button>
            <button class="btn btn-primary" id="playPauseBtn" onclick="toggleAnimation()">
                <i class="bi bi-play-fill"></i> Play
            </button>
            <button class="btn btn-secondary btn-sm" onclick="nextFrame()">
                Avanti <i class="bi bi-skip-forward-fill"></i>
            </button>
        </div>
    </div>
    
    <div class="loading-indicator" id="loadingIndicator" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Caricamento...</span>
        </div>
        <div class="mt-2">Caricamento radar meteo...</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map centered on North Italy
        const map = L.map('weatherMap', {
            zoomControl: true,
            attributionControl: true
        }).setView([45.4667, 10.5333], 11);

        // Add base map layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Animation state
        let apiData = {};
        let radarFrames = [];
        let radarLayers = {};
        let currentFrameIndex = 0;
        let isPlaying = false;
        let animationTimeout = null;
        let lastPastFrameIndex = -1; // Track where past frames end and nowcast begins
        let fadeAnimationId = null; // Track ongoing fade animation
        
        // RainViewer API options
        const optionTileSize = 256;
        const optionColorScheme = 2; // Universal Blue
        const optionSmoothData = 1;
        const optionSnowColors = 1;
        const optionExtension = 'webp';
        
        // Animation settings
        const frameDuration = 500; // Total time per frame in ms (faster for smoother feel)
        const crossfadeDuration = 300; // Crossfade transition time in ms

        // Add radar layer for a specific frame
        function addRadarLayer(frame) {
            if (!radarLayers[frame.path]) {
                const tileUrl = `${apiData.host}${frame.path}/${optionTileSize}/{z}/{x}/{y}/${optionColorScheme}/${optionSmoothData}_${optionSnowColors}.${optionExtension}`;
                
                const layer = L.tileLayer(tileUrl, {
                    opacity: 0,
                    maxZoom: 19,
                    zIndex: frame.time,
                    attribution: '&copy; <a href="https://www.rainviewer.com/">RainViewer</a>'
                });
                
                radarLayers[frame.path] = layer;
            }
            
            if (!map.hasLayer(radarLayers[frame.path])) {
                map.addLayer(radarLayers[frame.path]);
            }
            
            return radarLayers[frame.path];
        }

        // Load radar data from RainViewer API
        async function loadRadarData() {
            document.getElementById('loadingIndicator').style.display = 'block';
            
            try {
                const response = await fetch('https://api.rainviewer.com/public/weather-maps.json');
                const data = await response.json();
                
                if (data && data.radar && data.radar.past && data.radar.past.length > 0) {
                    // Store the full API response for using the host field
                    apiData = data;
                    
                    // Remove old layers
                    for (const path in radarLayers) {
                        map.removeLayer(radarLayers[path]);
                    }
                    radarLayers = {};
                    visibleFramePath = null;
                    
                    // Get all past radar images (typically at ~5-minute intervals)
                    radarFrames = [...data.radar.past];
                    lastPastFrameIndex = data.radar.past.length - 1;
                    
                    // Add nowcast frames if available
                    if (data.radar.nowcast && data.radar.nowcast.length > 0) {
                        radarFrames = radarFrames.concat(data.radar.nowcast);
                    }
                    
                    // Update info
                    const latestTime = new Date(radarFrames[radarFrames.length - 1].time * 1000);
                    const intervalMinutes = radarFrames.length > 1 
                        ? Math.round((radarFrames[1].time - radarFrames[0].time) / 60) 
                        : 5;
                    
                    document.getElementById('radarInfo').innerHTML = 
                        `<small><i class="bi bi-check-circle text-success"></i> <strong>Dati disponibili</strong><br>` +
                        `Ultimo aggiornamento: ${latestTime.toLocaleString('it-IT')}<br>` +
                        `Frames disponibili: ${radarFrames.length}<br>` +
                        `Intervallo: ogni ${intervalMinutes} minuti<br>` +
                        `Fonte: RainViewer</small>`;
                    
                    // Pre-load all frames for smooth animation
                    radarFrames.forEach(frame => addRadarLayer(frame));
                    
                    // Start from the last past frame (instant transition for initial display)
                    currentFrameIndex = data.radar.past.length - 1;
                    showFrame(currentFrameIndex, true);
                } else {
                    document.getElementById('radarInfo').innerHTML = 
                        '<small><i class="bi bi-exclamation-triangle text-warning"></i> Nessun dato radar disponibile</small>';
                }
            } catch (error) {
                console.error('Error loading radar data:', error);
                document.getElementById('radarInfo').innerHTML = 
                    '<small><i class="bi bi-x-circle text-danger"></i> Errore caricamento dati</small>';
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        // Track the currently visible frame path
        let visibleFramePath = null;
        
        // Helper function to cancel any ongoing fade animation
        function cancelFadeAnimation() {
            if (fadeAnimationId) {
                cancelAnimationFrame(fadeAnimationId);
                fadeAnimationId = null;
            }
        }

        // Crossfade animation using requestAnimationFrame for smooth transitions
        function crossfade(fromLayer, toLayer, duration, callback) {
            // Cancel any ongoing fade animation before starting new one
            cancelFadeAnimation();
            
            const startTime = performance.now();
            const targetOpacity = 0.7;
            
            // Ensure target layer starts at 0 opacity
            if (toLayer) {
                toLayer.setOpacity(0);
            }
            
            function animate(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Use easeInOutCubic for smoother feel
                const eased = progress < 0.5 
                    ? 4 * progress * progress * progress 
                    : 1 - Math.pow(-2 * progress + 2, 3) / 2;
                
                // Update opacities
                if (fromLayer) {
                    fromLayer.setOpacity(targetOpacity * (1 - eased));
                }
                if (toLayer) {
                    toLayer.setOpacity(targetOpacity * eased);
                }
                
                if (progress < 1) {
                    fadeAnimationId = requestAnimationFrame(animate);
                } else {
                    fadeAnimationId = null;
                    // Ensure final state
                    if (fromLayer) {
                        fromLayer.setOpacity(0);
                    }
                    if (toLayer) {
                        toLayer.setOpacity(targetOpacity);
                    }
                    if (callback) callback();
                }
            }
            
            fadeAnimationId = requestAnimationFrame(animate);
        }

        // Show specific frame with smooth crossfade transition
        function showFrame(index, instant = false) {
            if (radarFrames.length === 0 || index < 0 || index >= radarFrames.length) {
                return;
            }
            
            const previousFramePath = visibleFramePath;
            currentFrameIndex = index;
            const currentFrame = radarFrames[index];
            visibleFramePath = currentFrame.path;
            
            const fromLayer = previousFramePath && previousFramePath !== currentFrame.path 
                ? radarLayers[previousFramePath] 
                : null;
            const toLayer = radarLayers[currentFrame.path];
            
            if (instant || !fromLayer) {
                // Instant switch: for initial load (no previous frame) or manual navigation
                // Hide the previous frame if it exists
                if (fromLayer) {
                    fromLayer.setOpacity(0);
                }
                // Show the new frame
                if (toLayer) {
                    toLayer.setOpacity(0.7);
                }
            } else {
                // Smooth crossfade for animation
                crossfade(fromLayer, toLayer, crossfadeDuration);
            }
            
            // Update time display
            const frameTime = new Date(currentFrame.time * 1000);
            const isNowcast = index > lastPastFrameIndex;
            const timePrefix = isNowcast ? '(Previsione) ' : '';
            
            document.getElementById('currentTimeDisplay').textContent = 
                timePrefix + frameTime.toLocaleString('it-IT', {
                    hour: '2-digit',
                    minute: '2-digit',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            
            // Update progress bar
            const progress = ((index + 1) / radarFrames.length) * 100;
            const progressBar = document.getElementById('animationProgress');
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
        }

        // Toggle animation play/pause
        function toggleAnimation() {
            if (isPlaying) {
                pauseAnimation();
            } else {
                playAnimation();
            }
        }

        // Play animation
        function playAnimation() {
            if (radarFrames.length === 0) return;
            
            isPlaying = true;
            document.getElementById('playPauseBtn').innerHTML = 
                '<i class="bi bi-pause-fill"></i> Pausa';
            
            function animate() {
                if (!isPlaying) return;
                
                currentFrameIndex++;
                if (currentFrameIndex >= radarFrames.length) {
                    currentFrameIndex = 0;
                }
                showFrame(currentFrameIndex);
                
                animationTimeout = setTimeout(animate, frameDuration);
            }
            
            animationTimeout = setTimeout(animate, frameDuration);
        }

        // Pause animation
        function pauseAnimation() {
            isPlaying = false;
            document.getElementById('playPauseBtn').innerHTML = 
                '<i class="bi bi-play-fill"></i> Play';
            
            if (animationTimeout) {
                clearTimeout(animationTimeout);
                animationTimeout = null;
            }
            
            // Cancel any ongoing fade animation
            cancelFadeAnimation();
        }

        // Previous frame
        function previousFrame() {
            pauseAnimation();
            currentFrameIndex--;
            if (currentFrameIndex < 0) {
                currentFrameIndex = radarFrames.length - 1;
            }
            showFrame(currentFrameIndex, true);
        }

        // Next frame
        function nextFrame() {
            pauseAnimation();
            currentFrameIndex++;
            if (currentFrameIndex >= radarFrames.length) {
                currentFrameIndex = 0;
            }
            showFrame(currentFrameIndex, true);
        }

        // Load radar data on page load
        loadRadarData();

        // Auto-refresh every 5 minutes
        setInterval(async () => {
            const wasPlaying = isPlaying;
            pauseAnimation();
            await loadRadarData();
            if (wasPlaying) {
                playAnimation();
            }
        }, 5 * 60 * 1000);
        
        // Auto-start animation after 2 seconds
        setTimeout(() => {
            if (radarFrames.length > 0) {
                playAnimation();
            }
        }, 2000);
    </script>
</body>
</html>
