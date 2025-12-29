<?php
/**
 * Mappa Eventi e Interventi in Tempo Reale
 * 
 * Visualizza su mappa tutti gli eventi e interventi aperti/in corso
 * con aggiornamento automatico ogni 10 secondi
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('events', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$pageTitle = 'Mappa Eventi e Interventi';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin=""/>
    
    <!-- Bootstrap Icons per marker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        /* Stile per popup */
        .leaflet-popup-content {
            margin: 10px;
            min-width: 250px;
        }
        
        .popup-header {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }
        
        .popup-event-id {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: bold;
            margin-right: 5px;
        }
        
        .popup-intervention-id {
            display: inline-block;
            background: #ffc107;
            color: #333;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: bold;
            margin-right: 5px;
        }
        
        .popup-field {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .popup-label {
            font-weight: 600;
            color: #666;
        }
        
        .popup-value {
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-aperto {
            background: #17a2b8;
            color: white;
        }
        
        .status-in_corso {
            background: #ffc107;
            color: #333;
        }
        
        /* Info box in alto a destra */
        .map-info {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            font-family: Arial, sans-serif;
        }
        
        .map-info h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #333;
        }
        
        .map-info-item {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .map-info-item .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            margin-right: 5px;
            font-weight: bold;
        }
        
        .badge-red {
            background: #dc3545;
            color: white;
        }
        
        .badge-yellow {
            background: #ffc107;
            color: #333;
        }
        
        .last-update {
            font-size: 11px;
            color: #666;
            margin-top: 10px;
        }
        
        /* Marker personalizzato */
        .custom-marker {
            background: transparent;
            border: none;
        }
        
        .marker-icon {
            font-size: 32px;
            text-align: center;
            line-height: 1;
        }
        
        .marker-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 12px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>
    <div id="map"></div>
    
    <div class="map-info">
        <h4>📍 Mappa Eventi</h4>
        <div class="map-info-item">
            <span class="badge badge-red">●</span>
            <span id="eventCount">0</span> Eventi
        </div>
        <div class="map-info-item">
            <span class="badge badge-yellow">●</span>
            <span id="interventionCount">0</span> Interventi
        </div>
        <div class="last-update">
            Aggiornamento: <span id="lastUpdate">-</span>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
            crossorigin=""></script>
    
    <script>
        let map;
        let markersLayer;
        let refreshInterval;
        
        // Inizializza la mappa
        function initMap() {
            // Centro Italia come posizione di default
            map = L.map('map').setView([42.5, 12.5], 6);
            
            // Usa OpenStreetMap tiles (gratuito, open source)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Layer per i markers
            markersLayer = L.layerGroup().addTo(map);
            
            // Carica i dati iniziali
            loadMapData();
            
            // Aggiorna ogni 10 secondi
            refreshInterval = setInterval(loadMapData, 10000);
        }
        
        // Carica dati dalla API
        function loadMapData() {
            fetch('event_map_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateMap(data);
                        updateInfoBox(data);
                    } else {
                        console.error('Errore caricamento dati mappa:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Errore fetch dati mappa:', error);
                });
        }
        
        // Aggiorna i marker sulla mappa
        function updateMap(data) {
            // Pulisci markers esistenti
            markersLayer.clearLayers();
            
            let bounds = [];
            
            // Aggiungi eventi (marker rossi)
            data.events.forEach(event => {
                const marker = createEventMarker(event);
                markersLayer.addLayer(marker);
                bounds.push([event.latitude, event.longitude]);
            });
            
            // Aggiungi interventi (marker gialli)
            data.interventions.forEach(intervention => {
                const marker = createInterventionMarker(intervention);
                markersLayer.addLayer(marker);
                bounds.push([intervention.latitude, intervention.longitude]);
            });
            
            // Centra la mappa se ci sono markers
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }
        
        // Crea marker per evento
        function createEventMarker(event) {
            const marker = L.marker([event.latitude, event.longitude], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `
                        <div style="position: relative;">
                            <i class="bi bi-geo-alt-fill" style="font-size: 40px; color: #dc3545;"></i>
                            <div class="marker-label">${event.id}</div>
                        </div>
                    `,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40]
                })
            });
            
            const popupContent = createEventPopup(event);
            marker.bindPopup(popupContent);
            
            return marker;
        }
        
        // Crea marker per intervento
        function createInterventionMarker(intervention) {
            const marker = L.marker([intervention.latitude, intervention.longitude], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `
                        <div style="position: relative;">
                            <i class="bi bi-geo-alt-fill" style="font-size: 40px; color: #ffc107;"></i>
                            <div class="marker-label" style="color: #333;">${intervention.id}</div>
                        </div>
                    `,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40]
                })
            });
            
            const popupContent = createInterventionPopup(intervention);
            marker.bindPopup(popupContent);
            
            return marker;
        }
        
        // Crea contenuto popup per evento
        function createEventPopup(event) {
            const typeLabels = {
                'emergenza': '🚨 Emergenza',
                'esercitazione': '🎯 Esercitazione',
                'attivita': '📅 Attività'
            };
            
            return `
                <div>
                    <div class="popup-header">
                        <span class="popup-event-id">#${event.id}</span>
                        ${typeLabels[event.event_type] || event.event_type}
                    </div>
                    <div class="popup-field">
                        <span class="popup-label">Titolo:</span>
                        <span class="popup-value">${escapeHtml(event.title)}</span>
                    </div>
                    ${event.description ? `
                        <div class="popup-field">
                            <span class="popup-label">Descrizione:</span>
                            <span class="popup-value">${escapeHtml(event.description.substring(0, 100))}${event.description.length > 100 ? '...' : ''}</span>
                        </div>
                    ` : ''}
                    <div class="popup-field">
                        <span class="popup-label">Stato:</span>
                        <span class="status-badge status-${event.status}">${event.status_label}</span>
                    </div>
                    <div class="popup-field">
                        <span class="popup-label">Inizio:</span>
                        <span class="popup-value">${formatDateTime(event.start_date)}</span>
                    </div>
                    ${event.full_address ? `
                        <div class="popup-field">
                            <span class="popup-label">Indirizzo:</span>
                            <span class="popup-value">${escapeHtml(event.full_address)}</span>
                        </div>
                    ` : ''}
                    ${event.municipality ? `
                        <div class="popup-field">
                            <span class="popup-label">Comune:</span>
                            <span class="popup-value">${escapeHtml(event.municipality)}</span>
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        // Crea contenuto popup per intervento
        function createInterventionPopup(intervention) {
            return `
                <div>
                    <div class="popup-header">
                        <span class="popup-intervention-id">#${intervention.id}</span>
                        🔧 Intervento
                    </div>
                    <div class="popup-field">
                        <span class="popup-label">Titolo:</span>
                        <span class="popup-value">${escapeHtml(intervention.title)}</span>
                    </div>
                    <div class="popup-field">
                        <span class="popup-label">Evento:</span>
                        <span class="popup-value">${escapeHtml(intervention.event_title)}</span>
                    </div>
                    ${intervention.description ? `
                        <div class="popup-field">
                            <span class="popup-label">Descrizione:</span>
                            <span class="popup-value">${escapeHtml(intervention.description.substring(0, 100))}${intervention.description.length > 100 ? '...' : ''}</span>
                        </div>
                    ` : ''}
                    <div class="popup-field">
                        <span class="popup-label">Stato:</span>
                        <span class="status-badge status-${intervention.status}">${intervention.status_label}</span>
                    </div>
                    <div class="popup-field">
                        <span class="popup-label">Inizio:</span>
                        <span class="popup-value">${formatDateTime(intervention.start_time)}</span>
                    </div>
                    ${intervention.full_address ? `
                        <div class="popup-field">
                            <span class="popup-label">Indirizzo:</span>
                            <span class="popup-value">${escapeHtml(intervention.full_address)}</span>
                        </div>
                    ` : ''}
                    ${intervention.municipality ? `
                        <div class="popup-field">
                            <span class="popup-label">Comune:</span>
                            <span class="popup-value">${escapeHtml(intervention.municipality)}</span>
                        </div>
                    ` : ''}
                </div>
            `;
        }
        
        // Aggiorna info box
        function updateInfoBox(data) {
            document.getElementById('eventCount').textContent = data.totals.events;
            document.getElementById('interventionCount').textContent = data.totals.interventions;
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('it-IT');
        }
        
        // Utility: formatta data/ora
        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return '-';
            const date = new Date(dateTimeStr);
            return date.toLocaleString('it-IT', { 
                day: '2-digit', 
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Utility: escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Inizializza quando il DOM è pronto
        document.addEventListener('DOMContentLoaded', initMap);
        
        // Cleanup quando si chiude la pagina
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>
