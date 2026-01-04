<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\GateController;

$app = App::getInstance();
$controller = new GateController($app->getDb(), $app->getConfig());
$association = $app->getAssociation();

// Get gates and system status
$systemStatus = $controller->getSystemStatus();
$gates = $controller->getAllGates();
$totalCount = $controller->getTotalPeopleCount();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabellone Gestione Varchi - <?php echo htmlspecialchars($association['name'] ?? 'EasyVol'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        
        #app {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo {
            max-height: 80px;
            max-width: 150px;
        }
        
        .association-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .system-title {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            text-align: center;
        }
        
        .total-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin: 20px 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .total-count-label {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .total-count-value {
            font-size: 72px;
            font-weight: bold;
        }
        
        .content {
            flex: 1;
            display: flex;
            padding: 0 40px 20px;
            gap: 20px;
            overflow: hidden;
        }
        
        .table-section {
            flex: 1;
            background: white;
            border-radius: 15px;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .map-section {
            flex: 1;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        #map {
            height: 100%;
            border-radius: 10px;
        }
        
        .gates-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .gates-table thead th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-size: 18px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .gates-table thead th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        .gates-table thead th:last-child {
            border-radius: 0 10px 0 0;
        }
        
        .gates-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .gates-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .gates-table tbody td {
            padding: 15px;
            font-size: 16px;
        }
        
        .gate-number {
            font-weight: bold;
            color: #667eea;
            font-size: 20px;
        }
        
        .gate-name {
            color: #333;
        }
        
        .people-count {
            font-weight: bold;
            font-size: 24px;
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .status-aperto {
            background: #4caf50;
            color: white;
        }
        
        .status-chiuso {
            background: #dc3545;
            color: white;
        }
        
        .status-non-gestito {
            background: #9e9e9e;
            color: white;
        }
        
        .limit-exceeded {
            background: #fff3cd !important;
        }
        
        .limit-value {
            color: #666;
            font-size: 16px;
        }
        
        .no-gates {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 18px;
        }
        
        .disabled-message {
            text-align: center;
            padding: 100px 40px;
            color: #dc3545;
        }
        
        .disabled-message i {
            font-size: 96px;
            margin-bottom: 20px;
        }
        
        .disabled-message h2 {
            font-size: 36px;
            margin-bottom: 20px;
        }
        
        .disabled-message p {
            font-size: 20px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div id="app">
        <?php if (!$systemStatus['is_active']): ?>
            <!-- System Disabled Message -->
            <div class="disabled-message">
                <i class="bi bi-exclamation-triangle"></i>
                <h2>Sistema Gestione Varchi Disabilitato</h2>
                <p>Il sistema di gestione varchi è attualmente disattivato.</p>
                <p>Contattare la Centrale Operativa o il Responsabile del Nucleo Informatico e Telecomunicazioni.</p>
            </div>
        <?php else: ?>
            <!-- Header -->
            <div class="header">
                <div class="logo-section">
                    <?php if (!empty($association['logo'])): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($association['logo']); ?>" 
                             alt="Logo" class="logo">
                    <?php endif; ?>
                    <div class="association-name">
                        <?php echo htmlspecialchars($association['name'] ?? 'Associazione'); ?>
                    </div>
                </div>
                <div class="system-title">
                    <i class="bi bi-door-open"></i> Sistema Gestione Varchi
                </div>
            </div>
            
            <!-- Total Count -->
            <div class="total-count">
                <div class="total-count-label">Totale Persone Presenti</div>
                <div class="total-count-value" id="total-count"><?php echo $totalCount; ?></div>
            </div>
            
            <!-- Content -->
            <div class="content">
                <!-- Table Section -->
                <div class="table-section">
                    <?php if (empty($gates)): ?>
                        <div class="no-gates">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 20px;"></i>
                            Nessun varco configurato
                        </div>
                    <?php else: ?>
                        <table class="gates-table" id="gates-table">
                            <thead>
                                <tr>
                                    <th>Nr. Varco - Nome</th>
                                    <th>Stato</th>
                                    <th>Limite</th>
                                    <th>Persone</th>
                                </tr>
                            </thead>
                            <tbody id="gates-tbody">
                                <!-- Table will be populated by JavaScript -->
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Map Section -->
                <div class="map-section">
                    <div id="map"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let markers = [];
        const systemActive = <?php echo $systemStatus['is_active'] ? 'true' : 'false'; ?>;

        // Initialize map
        function initMap() {
            map = L.map('map').setView([45.4642, 9.1900], 13); // Default to Milan
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
        }

        // Update gates display
        function updateGates() {
            if (!systemActive) return;
            
            fetch('api/gates.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateTable(data.gates);
                        updateMap(data.gates);
                        updateTotalCount();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Update table
        function updateTable(gates) {
            const tbody = document.getElementById('gates-tbody');
            if (!tbody) return;
            
            if (!gates || gates.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="no-gates">Nessun varco configurato</td></tr>';
                return;
            }

            tbody.innerHTML = gates.map(gate => {
                const currentLimit = getCurrentLimit(gate);
                const isExceeded = gate.people_count > currentLimit;
                const rowClass = isExceeded ? 'limit-exceeded' : '';
                
                return `
                    <tr class="${rowClass}">
                        <td>
                            <span class="gate-number">${escapeHtml(gate.gate_number)}</span>
                            <span class="gate-name"> - ${escapeHtml(gate.name)}</span>
                        </td>
                        <td>
                            <span class="status-badge status-${gate.status}">
                                ${getStatusLabel(gate.status)}
                            </span>
                        </td>
                        <td class="limit-value">${currentLimit}</td>
                        <td class="people-count">${gate.people_count}</td>
                    </tr>
                `;
            }).join('');
        }

        // Update map
        function updateMap(gates) {
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

                    const color = gate.status === 'aperto' ? 'green' : 
                                 (gate.status === 'chiuso' ? 'red' : 'gray');
                    
                    const icon = L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background-color: ${color}; width: 40px; height: 40px; border-radius: 50%; border: 4px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.4);"></div>`
                    });

                    const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
                    marker.bindTooltip(`
                        <div style="background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); font-size: 14px;">
                            <strong style="font-size: 16px;">Nr. ${gate.gate_number}: ${gate.name}</strong>
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

        // Update total count
        function updateTotalCount() {
            fetch('api/gates.php?action=total_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('total-count').textContent = data.total;
                    }
                })
                .catch(error => console.error('Error:', error));
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

        // Initialize
        if (systemActive) {
            initMap();
            updateGates();
            
            // Auto-update every 1 second
            setInterval(updateGates, 1000);
        }
    </script>
</body>
</html>
