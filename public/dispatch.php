<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\DispatchController;
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

$controller = new DispatchController($app->getDb(), $app->getConfig());

// Get initial data
$talkgroups = $controller->getTalkGroups();
$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];

$pageTitle = 'Dispatch - Monitoraggio Radio';
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
        .slot-tab {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            height: 120px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .slot-tab.active-transmission {
            background: #d4edda;
            border-color: #28a745;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .slot-tab.muted {
            opacity: 0.6;
        }
        #map {
            height: 500px;
            border-radius: 8px;
            transition: height 0.3s ease;
        }
        #map.expanded {
            height: 800px;
        }
        .radio-marker-tooltip {
            font-size: 12px;
        }
        .event-item, .audio-item, .message-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .event-item:hover, .audio-item:hover, .message-item:hover {
            background: #f8f9fa;
        }
        .emergency-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            background: #dc3545;
            color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
            animation: flash 1s infinite;
            max-width: 600px;
            display: none;
        }
        @keyframes flash {
            0%, 100% { background: #dc3545; }
            50% { background: #c82333; }
        }
        .emergency-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9998;
            display: none;
        }
        .transmission-status {
            font-size: 0.9em;
            color: #6c757d;
        }
        .transmission-status.active {
            color: #28a745;
            font-weight: bold;
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
                    <h1 class="h2"><i class="bi bi-broadcast-pin"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="talkgroup_manage.php" class="btn btn-sm btn-outline-primary me-2">
                            <i class="bi bi-collection"></i> Gestione TalkGroup
                        </a>
                        <?php if ($app->checkPermission('gate_management', 'view')): ?>
                            <a href="gate_management.php" class="btn btn-sm btn-outline-success me-2">
                                <i class="bi bi-door-open"></i> Gestione Varchi
                            </a>
                        <?php endif; ?>
                        <?php if ($app->checkPermission('operations_center', 'edit')): ?>
                            <a href="dispatch_raspberry_config.php" class="btn btn-sm btn-outline-info me-2">
                                <i class="bi bi-gear-fill"></i> Configurazione
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Aggiorna
                        </button>
                        <a href="radio_directory.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Rubrica Radio
                        </a>
                    </div>
                </div>

                <!-- Slot Monitoring -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5><i class="bi bi-1-circle"></i> Slot 1</h5>
                        <div class="slot-tab" id="slot1-tab" data-slot="1">
                            <div class="transmission-status" id="slot1-status">
                                Nessuna trasmissione
                            </div>
                            <div id="slot1-details" style="display: none;">
                                <h6 class="mt-3">ID Trasmissione: <span id="slot1-radio-id"></span></h6>
                                <p class="mb-1"><strong>Radio:</strong> <span id="slot1-radio-name"></span></p>
                                <p class="mb-1"><strong>Assegnatario:</strong> <span id="slot1-assignee"></span></p>
                                <p class="mb-1"><strong>TalkGroup:</strong> <span id="slot1-talkgroup"></span></p>
                                <div class="mt-3">
                                    <audio id="slot1-audio" controls style="width: 100%; display: none;">
                                        Il tuo browser non supporta l'audio streaming
                                    </audio>
                                    <button class="btn btn-sm btn-warning" onclick="toggleMute(1)">
                                        <i class="bi bi-volume-mute"></i> Muta
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="bi bi-2-circle"></i> Slot 2</h5>
                        <div class="slot-tab" id="slot2-tab" data-slot="2">
                            <div class="transmission-status" id="slot2-status">
                                Nessuna trasmissione
                            </div>
                            <div id="slot2-details" style="display: none;">
                                <h6 class="mt-3">ID Trasmissione: <span id="slot2-radio-id"></span></h6>
                                <p class="mb-1"><strong>Radio:</strong> <span id="slot2-radio-name"></span></p>
                                <p class="mb-1"><strong>Assegnatario:</strong> <span id="slot2-assignee"></span></p>
                                <p class="mb-1"><strong>TalkGroup:</strong> <span id="slot2-talkgroup"></span></p>
                                <div class="mt-3">
                                    <audio id="slot2-audio" controls style="width: 100%; display: none;">
                                        Il tuo browser non supporta l'audio streaming
                                    </audio>
                                    <button class="btn btn-sm btn-warning" onclick="toggleMute(2)">
                                        <i class="bi bi-volume-mute"></i> Muta
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-map"></i> Mappa Posizioni Radio</h5>
                        <div>
                            <button id="toggleMapSize" class="btn btn-sm btn-outline-info me-2" onclick="toggleMapSize()">
                                <i class="bi bi-arrows-fullscreen"></i> <span id="mapSizeText">Espandi</span>
                            </button>
                            <a href="dispatch_map_fullscreen.php" target="_blank" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="bi bi-box-arrow-up-right"></i> Apri in Nuova Scheda
                            </a>
                            <a href="dispatch_position_history.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-clock-history"></i> Storico Posizioni
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="map"></div>
                    </div>
                </div>

                <!-- Information Tabs -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="infoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="events-tab" data-bs-toggle="tab" 
                                        data-bs-target="#events" type="button" role="tab">
                                    <i class="bi bi-list-ul"></i> Eventi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="recordings-tab" data-bs-toggle="tab" 
                                        data-bs-target="#recordings" type="button" role="tab">
                                    <i class="bi bi-mic-fill"></i> Registrazioni Audio
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="messages-tab" data-bs-toggle="tab" 
                                        data-bs-target="#messages" type="button" role="tab">
                                    <i class="bi bi-chat-dots"></i> Messaggi di Testo
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="infoTabsContent">
                            <!-- Events Tab -->
                            <div class="tab-pane fade show active" id="events" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6>Eventi Rete Radio</h6>
                                    <a href="dispatch_event_history.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-clock-history"></i> Storico Eventi
                                    </a>
                                </div>
                                <div id="events-list" style="max-height: 400px; overflow-y: auto;">
                                    <p class="text-muted">Caricamento eventi...</p>
                                </div>
                            </div>
                            
                            <!-- Audio Recordings Tab -->
                            <div class="tab-pane fade" id="recordings" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6>Registrazioni Audio Recenti</h6>
                                    <a href="dispatch_audio_history.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-clock-history"></i> Storico Audio
                                    </a>
                                </div>
                                <div id="recordings-list" style="max-height: 400px; overflow-y: auto;">
                                    <p class="text-muted">Caricamento registrazioni...</p>
                                </div>
                            </div>
                            
                            <!-- Text Messages Tab -->
                            <div class="tab-pane fade" id="messages" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6>Messaggi di Testo</h6>
                                    <a href="dispatch_message_history.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-clock-history"></i> Storico Messaggi
                                    </a>
                                </div>
                                <div id="messages-list" style="max-height: 400px; overflow-y: auto;">
                                    <p class="text-muted">Caricamento messaggi...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Emergency Popup -->
    <div class="emergency-backdrop" id="emergencyBackdrop"></div>
    <div class="emergency-popup" id="emergencyPopup">
        <h3><i class="bi bi-exclamation-triangle-fill"></i> CODICE DI EMERGENZA</h3>
        <hr>
        <div id="emergencyDetails"></div>
        <div class="mt-4">
            <button class="btn btn-light me-2" onclick="acknowledgeEmergency()">
                <i class="bi bi-check-circle"></i> Ricevuto
            </button>
            <button class="btn btn-light" onclick="closeEmergencyPopup()">
                <i class="bi bi-x-circle"></i> Chiudi
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const map = L.map('map').setView([45.4642, 9.1900], 10); // Default to Milan, Italy
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        
        const radioMarkers = {};
        const slotMuted = { 1: false, 2: false };
        let currentEmergencyId = null;
        let emergencySiren = null;
        
        // Update transmission status
        async function updateTransmissionStatus() {
            try {
                const response = await fetch('api/dispatch_transmission_status.php');
                const data = await response.json();
                
                // Update Slot 1
                updateSlot(1, data.slot1);
                
                // Update Slot 2
                updateSlot(2, data.slot2);
            } catch (error) {
                console.error('Error updating transmission status:', error);
            }
        }
        
        function updateSlot(slotNum, transmission) {
            const slotTab = document.getElementById(`slot${slotNum}-tab`);
            const slotStatus = document.getElementById(`slot${slotNum}-status`);
            const slotDetails = document.getElementById(`slot${slotNum}-details`);
            
            if (transmission && transmission.is_active) {
                slotTab.classList.add('active-transmission');
                slotStatus.textContent = 'TRASMISSIONE IN CORSO';
                slotStatus.classList.add('active');
                slotDetails.style.display = 'block';
                
                // Update details
                document.getElementById(`slot${slotNum}-radio-id`).textContent = transmission.radio_dmr_id || transmission.radio_identifier || 'N/A';
                document.getElementById(`slot${slotNum}-radio-name`).textContent = transmission.radio_name || 'Sconosciuta';
                
                let assignee = 'Non assegnata';
                if (transmission.first_name && transmission.last_name) {
                    assignee = `${transmission.first_name} ${transmission.last_name}`;
                    if (transmission.organization) {
                        assignee += ` (${transmission.organization})`;
                    }
                }
                document.getElementById(`slot${slotNum}-assignee`).textContent = assignee;
                
                const talkgroup = transmission.talkgroup_name ? 
                    `${transmission.talkgroup_name} (${transmission.talkgroup_id})` : 
                    transmission.talkgroup_id;
                document.getElementById(`slot${slotNum}-talkgroup`).textContent = talkgroup;
                
                // Show audio if muted
                if (slotMuted[slotNum]) {
                    slotTab.classList.add('muted');
                }
            } else {
                slotTab.classList.remove('active-transmission', 'muted');
                slotStatus.textContent = 'Nessuna trasmissione';
                slotStatus.classList.remove('active');
                slotDetails.style.display = 'none';
            }
        }
        
        function toggleMute(slotNum) {
            slotMuted[slotNum] = !slotMuted[slotNum];
            const slotTab = document.getElementById(`slot${slotNum}-tab`);
            
            if (slotMuted[slotNum]) {
                slotTab.classList.add('muted');
            } else {
                slotTab.classList.remove('muted');
            }
        }
        
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
        
        // Update events list
        async function updateEventsList() {
            try {
                const response = await fetch('api/dispatch_events.php');
                const events = await response.json();
                
                const eventsList = document.getElementById('events-list');
                if (events.length === 0) {
                    eventsList.innerHTML = '<p class="text-muted">Nessun evento recente</p>';
                    return;
                }
                
                let html = '';
                events.forEach(event => {
                    html += `
                        <div class="event-item">
                            <small class="text-muted">${event.event_timestamp}</small>
                            <strong> [Slot ${event.slot || 'N/A'}] ${event.event_type}</strong><br>
                            <span>${event.radio_name || event.radio_dmr_id || 'N/A'}</span>
                            ${event.talkgroup_name ? ` - TG: ${event.talkgroup_name}` : ''}
                        </div>
                    `;
                });
                eventsList.innerHTML = html;
            } catch (error) {
                console.error('Error updating events:', error);
            }
        }
        
        // Update audio recordings list
        async function updateRecordingsList() {
            try {
                const response = await fetch('api/dispatch_audio.php');
                const recordings = await response.json();
                
                const recordingsList = document.getElementById('recordings-list');
                if (recordings.length === 0) {
                    recordingsList.innerHTML = '<p class="text-muted">Nessuna registrazione recente</p>';
                    return;
                }
                
                let html = '';
                recordings.forEach(rec => {
                    const duration = rec.duration_seconds ? `${Math.floor(rec.duration_seconds / 60)}:${(rec.duration_seconds % 60).toString().padStart(2, '0')}` : 'N/A';
                    html += `
                        <div class="audio-item">
                            <small class="text-muted">${rec.recorded_at}</small>
                            <strong> [Slot ${rec.slot}]</strong><br>
                            <span>${rec.radio_name || rec.radio_dmr_id} - TG: ${rec.talkgroup_name || rec.talkgroup_id}</span><br>
                            <small>Durata: ${duration}</small>
                            <audio controls style="width: 100%; height: 30px; margin-top: 5px;">
                                <source src="${rec.file_path}" type="audio/wav">
                            </audio>
                        </div>
                    `;
                });
                recordingsList.innerHTML = html;
            } catch (error) {
                console.error('Error updating recordings:', error);
            }
        }
        
        // Update text messages list
        async function updateMessagesList() {
            try {
                const response = await fetch('api/dispatch_messages.php');
                const messages = await response.json();
                
                const messagesList = document.getElementById('messages-list');
                if (messages.length === 0) {
                    messagesList.innerHTML = '<p class="text-muted">Nessun messaggio recente</p>';
                    return;
                }
                
                let html = '';
                messages.forEach(msg => {
                    const recipient = msg.to_radio_name ? msg.to_radio_name : 
                                    (msg.to_talkgroup_name ? `TG: ${msg.to_talkgroup_name}` : 'Broadcast');
                    html += `
                        <div class="message-item">
                            <small class="text-muted">${msg.message_timestamp} [Slot ${msg.slot}]</small><br>
                            <strong>Da:</strong> ${msg.from_radio_name || msg.from_radio_dmr_id}<br>
                            <strong>A:</strong> ${recipient}<br>
                            <strong>Messaggio:</strong> ${msg.message_text}
                        </div>
                    `;
                });
                messagesList.innerHTML = html;
            } catch (error) {
                console.error('Error updating messages:', error);
            }
        }
        
        // Check for new emergencies
        async function checkEmergencies() {
            try {
                const response = await fetch('api/dispatch_emergencies.php');
                const emergencies = await response.json();
                
                if (emergencies.length > 0 && emergencies[0].id !== currentEmergencyId) {
                    showEmergencyPopup(emergencies[0]);
                }
            } catch (error) {
                console.error('Error checking emergencies:', error);
            }
        }
        
        function showEmergencyPopup(emergency) {
            currentEmergencyId = emergency.id;
            
            // Play siren sound (3 seconds)
            if (!emergencySiren) {
                emergencySiren = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGm+DyvmwhBCl+zPLTgjEHHGSy6+mcTQ0QTKXh8bllHg=='); // Placeholder
                emergencySiren.volume = 0.5;
            }
            
            try {
                emergencySiren.play().catch(e => console.log('Could not play siren:', e));
                setTimeout(() => {
                    if (emergencySiren) emergencySiren.pause();
                }, 3000);
            } catch (e) {
                console.log('Could not play siren:', e);
            }
            
            // Build emergency details
            let assigneeInfo = 'Non assegnata';
            if (emergency.first_name && emergency.last_name) {
                assigneeInfo = `
                    <strong>Nome:</strong> ${emergency.first_name} ${emergency.last_name}<br>
                    ${emergency.organization ? `<strong>Ente:</strong> ${emergency.organization}<br>` : ''}
                    ${emergency.phone ? `<strong>Telefono:</strong> ${emergency.phone}<br>` : ''}
                    ${emergency.assignment_notes ? `<strong>Note:</strong> ${emergency.assignment_notes}<br>` : ''}
                `;
            }
            
            const mapLink = emergency.latitude && emergency.longitude ? 
                `<a href="https://www.openstreetmap.org/?mlat=${emergency.latitude}&mlon=${emergency.longitude}&zoom=15" 
                   target="_blank" class="btn btn-light btn-sm mt-2">
                    <i class="bi bi-map"></i> Vedi su Mappa
                </a>` : '';
            
            document.getElementById('emergencyDetails').innerHTML = `
                <p><strong>ID Radio:</strong> ${emergency.radio_dmr_id}</p>
                <p><strong>Nome Radio:</strong> ${emergency.radio_name || 'N/A'}</p>
                <p><strong>Assegnatario:</strong><br>${assigneeInfo}</p>
                ${emergency.latitude && emergency.longitude ? 
                    `<p><strong>Posizione GPS:</strong><br>Lat: ${emergency.latitude}, Lon: ${emergency.longitude}</p>` : 
                    '<p><strong>Posizione GPS:</strong> Non disponibile</p>'
                }
                <p><strong>Ora Emergenza:</strong> ${emergency.emergency_timestamp}</p>
                ${mapLink}
            `;
            
            document.getElementById('emergencyBackdrop').style.display = 'block';
            document.getElementById('emergencyPopup').style.display = 'block';
        }
        
        function closeEmergencyPopup() {
            document.getElementById('emergencyBackdrop').style.display = 'none';
            document.getElementById('emergencyPopup').style.display = 'none';
            if (emergencySiren) {
                emergencySiren.pause();
            }
        }
        
        async function acknowledgeEmergency() {
            if (!currentEmergencyId) return;
            
            try {
                await fetch('api/dispatch_emergency_acknowledge.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ emergency_id: currentEmergencyId })
                });
                
                closeEmergencyPopup();
            } catch (error) {
                console.error('Error acknowledging emergency:', error);
            }
        }
        
        // Toggle map size
        function toggleMapSize() {
            const mapElement = document.getElementById('map');
            const mapSizeText = document.getElementById('mapSizeText');
            
            if (mapElement.classList.contains('expanded')) {
                mapElement.classList.remove('expanded');
                mapSizeText.textContent = 'Espandi';
            } else {
                mapElement.classList.add('expanded');
                mapSizeText.textContent = 'Riduci';
            }
            
            // Trigger map resize after the CSS transition
            setTimeout(() => {
                map.invalidateSize();
            }, 300);
        }
        
        // Initialize and start polling
        updateTransmissionStatus();
        updateRadioPositions();
        updateEventsList();
        updateRecordingsList();
        updateMessagesList();
        checkEmergencies();
        
        // Update every 2 seconds for real-time feel
        setInterval(updateTransmissionStatus, 2000);
        setInterval(updateRadioPositions, 5000);
        setInterval(updateEventsList, 3000);
        setInterval(updateRecordingsList, 5000);
        setInterval(updateMessagesList, 5000);
        setInterval(checkEmergencies, 2000);
    </script>
</body>
</html>
