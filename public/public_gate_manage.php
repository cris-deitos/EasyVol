<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\GateController;

$app = App::getInstance();
$controller = new GateController($app->getDb(), $app->getConfig());

// Check system status
$systemStatus = $controller->getSystemStatus();
$gates = $controller->getAllGates();
$association = $app->getAssociation();

// Get selected gate from query string
$selectedGateId = $_GET['gate_id'] ?? null;
$selectedGate = null;

if ($selectedGateId) {
    $selectedGate = $controller->getGateById($selectedGateId);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>EasyVol - Gestione Varchi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
            font-family: Arial, sans-serif;
        }
        
        #app {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .header h1 {
            font-size: 24px;
            margin: 0;
        }
        
        .content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .gate-select-container {
            margin-bottom: 20px;
        }
        
        .select-label {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
        }
        
        .gate-select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .btn-manage {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 8px;
        }
        
        /* Gate Management Screen */
        .gate-info {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 10px;
        }
        
        .gate-info h2 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .people-count {
            text-align: center;
            margin: 10px 0;
        }
        
        .people-count-label {
            font-size: 18px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .people-count-value {
            font-size: 72px;
            font-weight: bold;
            color: #333;
            transition: color 0.3s;
        }
        
        .people-count-value.warning-5 {
            color: #ff9800;
        }
        
        .people-count-value.at-limit {
            color: #dc3545;
        }
        
        .limit-warning {
            background: #dc3545;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            border-radius: 8px;
            margin: 10px 0;
            animation: flash 1s infinite;
            display: none;
        }
        
        .limit-warning.show {
            display: block;
        }
        
        .countdown-warning {
            background: #ffc107;
            color: #000;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            border-radius: 8px;
            margin: 10px 0;
            animation: flash 1s infinite;
            display: none;
        }
        
        .countdown-warning.show {
            display: block;
        }
        
        @keyframes flash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .button-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .btn-action {
            flex: 1;
            padding: 20px;
            font-size: 20px;
            font-weight: bold;
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 80px;
        }
        
        .btn-action:active {
            transform: scale(0.95);
        }
        
        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-remove {
            background: #ff9800;
        }
        
        .btn-add {
            background: #4caf50;
        }
        
        .btn-open {
            background: #2e7d32;
        }
        
        .btn-close {
            background: #d32f2f;
        }
        
        .btn-back {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .disabled-message {
            text-align: center;
            padding: 40px 20px;
            color: #dc3545;
        }
        
        .disabled-message h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .disabled-message p {
            font-size: 16px;
            line-height: 1.6;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .status-aperto {
            background: #4caf50;
            color: white;
        }
        
        .status-chiuso {
            background: #d32f2f;
            color: white;
        }
        
        .status-non-gestito {
            background: #9e9e9e;
            color: white;
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="header">
            <h1><i class="bi bi-door-open"></i> EasyVol - Gestione Varchi</h1>
        </div>
        
        <div class="content">
            <?php if (!$systemStatus['is_active']): ?>
                <!-- System Disabled Message -->
                <div class="disabled-message">
                    <i class="bi bi-exclamation-triangle" style="font-size: 72px; color: #dc3545;"></i>
                    <h2>Sistema Gestione Varchi Disabilitato</h2>
                    <p>Il sistema di gestione varchi è attualmente disattivato.</p>
                    <p>Contattare la Centrale Operativa o il Responsabile del Nucleo Informatico e Telecomunicazioni per maggiori informazioni.</p>
                </div>
            <?php elseif (!$selectedGate): ?>
                <!-- Gate Selection Screen -->
                <div class="gate-select-container">
                    <label class="select-label">Seleziona il varco da gestire:</label>
                    <select class="gate-select form-select" id="gateSelect">
                        <option value="">-- Seleziona un varco --</option>
                        <?php foreach ($gates as $gate): ?>
                            <option value="<?php echo $gate['id']; ?>">
                                Nr. <?php echo htmlspecialchars($gate['gate_number']); ?> - <?php echo htmlspecialchars($gate['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-manage" onclick="selectGate()">
                        <i class="bi bi-arrow-right-circle"></i> Gestisci Varco
                    </button>
                </div>
            <?php else: ?>
                <!-- Gate Management Screen -->
                <div class="gate-info">
                    <h2>
                        Nr. <?php echo htmlspecialchars($selectedGate['gate_number']); ?>: 
                        <?php echo htmlspecialchars($selectedGate['name']); ?>
                    </h2>
                    <div class="info-row">
                        <span class="info-label">Stato Varco:</span>
                        <span class="info-value">
                            <span class="status-badge status-<?php echo $selectedGate['status']; ?>" id="gate-status-badge">
                                <?php 
                                    $statusLabels = [
                                        'aperto' => 'Aperto',
                                        'chiuso' => 'Chiuso',
                                        'non_gestito' => 'Non Gestito'
                                    ];
                                    echo $statusLabels[$selectedGate['status']];
                                ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Limite in Uso:</span>
                        <span class="info-value">
                            <span id="limit-in-use-label">
                                <?php 
                                    $limitLabels = ['a' => 'A', 'b' => 'B', 'c' => 'C', 'manual' => 'Manuale'];
                                    echo $limitLabels[$selectedGate['limit_in_use']];
                                ?>
                            </span>
                            (<span id="limit-value">
                                <?php 
                                    $currentLimit = match($selectedGate['limit_in_use']) {
                                        'a' => $selectedGate['limit_a'],
                                        'b' => $selectedGate['limit_b'],
                                        'c' => $selectedGate['limit_c'],
                                        'manual' => $selectedGate['limit_manual'],
                                    };
                                    echo $currentLimit;
                                ?>
                            </span>)
                        </span>
                    </div>
                </div>
                
                <div class="people-count">
                    <div class="people-count-label">Numero Persone</div>
                    <div class="people-count-value" id="people-count">
                        <?php echo $selectedGate['people_count']; ?>
                    </div>
                </div>
                
                <div class="countdown-warning" id="countdown-warning">
                    <i class="bi bi-exclamation-circle"></i>
                    <span id="countdown-text">Mancano X persone al limite!</span>
                </div>
                
                <div class="limit-warning" id="limit-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    LIMITE RAGGIUNTO - CHIUDI VARCO!
                </div>
                
                <div class="button-row">
                    <button class="btn-action btn-remove" id="btn-remove" onclick="removePerson()" 
                            <?php echo $selectedGate['status'] === 'chiuso' ? 'disabled' : ''; ?>>
                        <i class="bi bi-dash-circle"></i><br>Rimuovi Persona
                    </button>
                    <button class="btn-action btn-add" id="btn-add" onclick="addPerson()"
                            <?php echo $selectedGate['status'] === 'chiuso' ? 'disabled' : ''; ?>>
                        <i class="bi bi-plus-circle"></i><br>Aggiungi Persona
                    </button>
                </div>
                
                <div class="button-row">
                    <button class="btn-action btn-open" id="btn-open" onclick="openGate()"
                            <?php echo $selectedGate['status'] === 'aperto' ? 'disabled' : ''; ?>>
                        <i class="bi bi-door-open"></i><br>Apri Varco
                    </button>
                    <button class="btn-action btn-close" id="btn-close" onclick="closeGate()"
                            <?php echo $selectedGate['status'] === 'chiuso' ? 'disabled' : ''; ?>>
                        <i class="bi bi-door-closed"></i><br>Chiudi Varco
                    </button>
                </div>
                
                <button class="btn-back" onclick="goBack()">
                    <i class="bi bi-arrow-left"></i> Torna Indietro
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const gateId = <?php echo $selectedGateId ? $selectedGateId : 'null'; ?>;
        const currentLimit = <?php echo isset($currentLimit) ? $currentLimit : 0; ?>;
        let updateInterval;

        function getCountdownText(remaining) {
            return `Mancano ${remaining} persone al limite!`;
        }

        function selectGate() {
            const select = document.getElementById('gateSelect');
            if (select.value) {
                window.location.href = '?gate_id=' + select.value;
            }
        }

        function goBack() {
            window.location.href = 'public_gate_manage.php';
        }

        function updateGateDisplay(gate) {
            // Update people count
            const peopleCountElement = document.getElementById('people-count');
            peopleCountElement.textContent = gate.people_count;
            
            // Update status badge
            const statusBadge = document.getElementById('gate-status-badge');
            const statusLabels = {
                'aperto': 'Aperto',
                'chiuso': 'Chiuso',
                'non_gestito': 'Non Gestito'
            };
            statusBadge.textContent = statusLabels[gate.status];
            statusBadge.className = 'status-badge status-' + gate.status;
            
            // Update buttons state
            const isClosed = gate.status === 'chiuso';
            const isOpen = gate.status === 'aperto';
            
            document.getElementById('btn-remove').disabled = isClosed;
            document.getElementById('btn-add').disabled = isClosed;
            document.getElementById('btn-open').disabled = isOpen;
            document.getElementById('btn-close').disabled = isClosed;
            
            // Calculate remaining people to limit
            const remaining = currentLimit - gate.people_count;
            
            // Reset all warning classes
            peopleCountElement.classList.remove('warning-5', 'at-limit');
            
            // Show/hide warnings and update number color
            const limitWarning = document.getElementById('limit-warning');
            const countdownWarning = document.getElementById('countdown-warning');
            const countdownText = document.getElementById('countdown-text');
            
            if (gate.people_count >= currentLimit) {
                // At or over limit - red number and red alert
                limitWarning.classList.add('show');
                countdownWarning.classList.remove('show');
                peopleCountElement.classList.add('at-limit');
            } else if (remaining <= 5) {
                // 5 or less people to limit - orange number and yellow countdown
                limitWarning.classList.remove('show');
                countdownWarning.classList.add('show');
                countdownText.textContent = getCountdownText(remaining);
                peopleCountElement.classList.add('warning-5');
            } else if (remaining <= 20) {
                // 20 or less people to limit - normal number and yellow countdown
                limitWarning.classList.remove('show');
                countdownWarning.classList.add('show');
                countdownText.textContent = getCountdownText(remaining);
            } else {
                // More than 20 people to limit - no warnings
                limitWarning.classList.remove('show');
                countdownWarning.classList.remove('show');
            }
        }

        function addPerson() {
            if (!gateId) return;
            
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add_person', id: gateId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.gate) {
                    updateGateDisplay(data.gate);
                } else {
                    alert(data.message || 'Errore nell\'aggiunta persona');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di connessione');
            });
        }

        function removePerson() {
            if (!gateId) return;
            
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove_person', id: gateId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.gate) {
                    updateGateDisplay(data.gate);
                } else {
                    alert(data.message || 'Errore nella rimozione persona');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di connessione');
            });
        }

        function openGate() {
            if (!gateId) return;
            
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'open_gate', id: gateId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.gate) {
                    updateGateDisplay(data.gate);
                } else {
                    alert(data.message || 'Errore nell\'apertura varco');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di connessione');
            });
        }

        function closeGate() {
            if (!gateId) return;
            
            fetch('api/gates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'close_gate', id: gateId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.gate) {
                    updateGateDisplay(data.gate);
                } else {
                    alert(data.message || 'Errore nella chiusura varco');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di connessione');
            });
        }

        // Auto-update gate data every 2 seconds
        if (gateId) {
            updateInterval = setInterval(function() {
                fetch('api/gates.php?action=get&id=' + gateId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.gate) {
                            updateGateDisplay(data.gate);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }, 2000);
        }

        // Check initial warnings
        if (gateId) {
            const peopleCount = parseInt(document.getElementById('people-count').textContent);
            const remaining = currentLimit - peopleCount;
            const peopleCountElement = document.getElementById('people-count');
            const limitWarning = document.getElementById('limit-warning');
            const countdownWarning = document.getElementById('countdown-warning');
            const countdownText = document.getElementById('countdown-text');
            
            if (peopleCount >= currentLimit) {
                // At or over limit
                limitWarning.classList.add('show');
                peopleCountElement.classList.add('at-limit');
            } else if (remaining <= 5) {
                // 5 or less people to limit
                countdownWarning.classList.add('show');
                countdownText.textContent = getCountdownText(remaining);
                peopleCountElement.classList.add('warning-5');
            } else if (remaining <= 20) {
                // 20 or less people to limit
                countdownWarning.classList.add('show');
                countdownText.textContent = getCountdownText(remaining);
            }
        }
    </script>
</body>
</html>
