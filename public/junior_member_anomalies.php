<?php
/**
 * Anomalie Soci Minorenni - Visualizzazione
 * 
 * Pagina per visualizzare tutte le anomalie rilevate nei dati dei soci minorenni
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\JuniorMemberController;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('junior_members', 'view_anomalies')) {
    die('Accesso negato - Permesso "Visualizza Anomalie Cadetti" richiesto');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new JuniorMemberController($db, $config);

// Log page access
AutoLogger::logPageAccess();

// Get anomalies
$anomalies = $controller->getAnomalies();

// Calculate totals
$totalAnomalies = 
    count($anomalies['no_mobile']) + 
    count($anomalies['no_email']) + 
    count($anomalies['invalid_fiscal_code']) + 
    count($anomalies['no_guardian_data']) + 
    count($anomalies['no_health_surveillance']) + 
    count($anomalies['expired_health_surveillance']);

$pageTitle = 'Anomalie Soci Minorenni';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-exclamation-triangle text-warning"></i> 
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="junior_members.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna ai Soci Minorenni
                        </a>
                    </div>
                </div>
                
                <?php if ($totalAnomalies === 0): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> 
                        <strong>Nessuna anomalia rilevata!</strong> 
                        Tutti i dati dei soci minorenni sono completi e validi.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Attenzione!</strong> 
                        Trovate <strong><?php echo $totalAnomalies; ?></strong> anomalie da verificare.
                    </div>
                <?php endif; ?>
                
                <!-- Cadetti senza numero di cellulare -->
                <?php if (!empty($anomalies['no_mobile'])): ?>
                <div class="card mb-4 anomaly-card" data-anomaly-type="junior_member_no_mobile">
                    <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-phone"></i> 
                            Cadetti senza Numero di Cellulare (<?php echo count($anomalies['no_mobile']); ?>)
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary anomaly-toggle-btn" type="button">
                            <i class="bi bi-eye-slash"></i> Nascondi
                        </button>
                    </div>
                    <div class="card-body anomaly-content">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Matricola</th>
                                    <th>Nome</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anomalies['no_mobile'] as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($member['member_status']); ?></span></td>
                                    <td>
                                        <a href="junior_member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Modifica
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Cadetti senza email -->
                <?php if (!empty($anomalies['no_email'])): ?>
                <div class="card mb-4 anomaly-card" data-anomaly-type="junior_member_no_email">
                    <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-envelope"></i> 
                            Cadetti senza Email (<?php echo count($anomalies['no_email']); ?>)
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary anomaly-toggle-btn" type="button">
                            <i class="bi bi-eye-slash"></i> Nascondi
                        </button>
                    </div>
                    <div class="card-body anomaly-content">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Matricola</th>
                                    <th>Nome</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anomalies['no_email'] as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($member['member_status']); ?></span></td>
                                    <td>
                                        <a href="junior_member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Modifica
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Codici fiscali non validi -->
                <?php if (!empty($anomalies['invalid_fiscal_code'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-credit-card"></i> 
                            Codici Fiscali Non Validi (<?php echo count($anomalies['invalid_fiscal_code']); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Matricola</th>
                                    <th>Nome</th>
                                    <th>Codice Fiscale</th>
                                    <th>Errori</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anomalies['invalid_fiscal_code'] as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($member['fiscal_code']); ?></code></td>
                                    <td><small class="text-danger"><?php echo htmlspecialchars($member['errors']); ?></small></td>
                                    <td>
                                        <a href="junior_member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Modifica
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Cadetti senza dati genitori/tutori -->
                <?php if (!empty($anomalies['no_guardian_data'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-people"></i> 
                            Cadetti senza Dati Genitori/Tutori (<?php echo count($anomalies['no_guardian_data']); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Matricola</th>
                                    <th>Nome</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anomalies['no_guardian_data'] as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($member['member_status']); ?></span></td>
                                    <td>
                                        <a href="junior_member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Modifica
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Sorveglianza sanitaria assente -->
                <?php if (!empty($anomalies['no_health_surveillance'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-heart-pulse"></i> 
                            Sorveglianza Sanitaria Assente (<?php echo count($anomalies['no_health_surveillance']); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Matricola</th>
                                    <th>Nome</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anomalies['no_health_surveillance'] as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($member['member_status']); ?></span></td>
                                    <td>
                                        <a href="junior_member_view.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> Visualizza
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Sorveglianza sanitaria scaduta -->
                <?php if (!empty($anomalies['expired_health_surveillance'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-heart-pulse"></i> 
                            Sorveglianza Sanitaria Scaduta (<?php echo count($anomalies['expired_health_surveillance']); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Matricola</th>
                                    <th>Nome</th>
                                    <th>Data Scadenza</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anomalies['expired_health_surveillance'] as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><span class="badge bg-danger"><?php echo date('d/m/Y', strtotime($member['expiry_date'])); ?></span></td>
                                    <td>
                                        <a href="junior_member_view.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> Visualizza
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Anomaly visibility management
        document.addEventListener('DOMContentLoaded', function() {
            const STORAGE_KEY = 'easyvol_hidden_anomalies';
            
            // Load hidden anomalies from localStorage
            function loadHiddenAnomalies() {
                const stored = localStorage.getItem(STORAGE_KEY);
                return stored ? JSON.parse(stored) : {};
            }
            
            // Save hidden anomalies to localStorage
            function saveHiddenAnomalies(hiddenAnomalies) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(hiddenAnomalies));
            }
            
            // Toggle anomaly visibility
            function toggleAnomaly(card, button) {
                const anomalyType = card.dataset.anomalyType;
                const content = card.querySelector('.anomaly-content');
                const icon = button.querySelector('i');
                const hiddenAnomalies = loadHiddenAnomalies();
                
                if (content.style.display === 'none') {
                    // Show anomaly
                    content.style.display = 'block';
                    icon.className = 'bi bi-eye-slash';
                    button.innerHTML = '<i class="bi bi-eye-slash"></i> Nascondi';
                    delete hiddenAnomalies[anomalyType];
                } else {
                    // Hide anomaly
                    content.style.display = 'none';
                    icon.className = 'bi bi-eye';
                    button.innerHTML = '<i class="bi bi-eye"></i> Mostra';
                    hiddenAnomalies[anomalyType] = true;
                }
                
                saveHiddenAnomalies(hiddenAnomalies);
            }
            
            // Initialize anomaly cards
            const anomalyCards = document.querySelectorAll('.anomaly-card');
            const hiddenAnomalies = loadHiddenAnomalies();
            
            anomalyCards.forEach(card => {
                const anomalyType = card.dataset.anomalyType;
                const toggleBtn = card.querySelector('.anomaly-toggle-btn');
                const content = card.querySelector('.anomaly-content');
                
                // Apply saved state
                if (hiddenAnomalies[anomalyType]) {
                    content.style.display = 'none';
                    toggleBtn.innerHTML = '<i class="bi bi-eye"></i> Mostra';
                }
                
                // Add click event
                toggleBtn.addEventListener('click', function() {
                    toggleAnomaly(card, toggleBtn);
                });
            });
        });
    </script>
</body>
</html>
