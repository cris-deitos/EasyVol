<?php
/**
 * Gestione Consensi Privacy - Lista
 * 
 * Pagina per visualizzare e gestire i consensi privacy GDPR
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\GdprController;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('gdpr_compliance', 'view')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

// Gestione filtri
$filters = [
    'entity_type' => $_GET['entity_type'] ?? '',
    'consent_type' => $_GET['consent_type'] ?? '',
    'consent_given' => $_GET['consent_given'] ?? '',
    'revoked' => $_GET['revoked'] ?? '',
    'expiring_soon' => $_GET['expiring_soon'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Ottieni consensi
$consents = $controller->indexConsents($filters, $page, $perPage);
$totalResults = $controller->countConsents($filters);
$totalPages = max(1, ceil($totalResults / $perPage));

// Log page access
AutoLogger::logPageAccess();
if (!empty($filters['search'])) {
    AutoLogger::logSearch('privacy_consents', $filters['search'], $filters);
}

$pageTitle = 'Gestione Consensi Privacy';
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
                    <h1 class="h2"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->checkPermission('gdpr_compliance', 'manage_consents')): ?>
                            <a href="privacy_consent_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Consenso
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome, cognome...">
                            </div>
                            <div class="col-md-2">
                                <label for="entity_type" class="form-label">Tipo Entità</label>
                                <select class="form-select" id="entity_type" name="entity_type">
                                    <option value="">Tutti</option>
                                    <option value="member" <?php echo $filters['entity_type'] === 'member' ? 'selected' : ''; ?>>Socio</option>
                                    <option value="junior_member" <?php echo $filters['entity_type'] === 'junior_member' ? 'selected' : ''; ?>>Cadetto</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="consent_type" class="form-label">Tipo Consenso</label>
                                <select class="form-select" id="consent_type" name="consent_type">
                                    <option value="">Tutti</option>
                                    <option value="privacy_policy" <?php echo $filters['consent_type'] === 'privacy_policy' ? 'selected' : ''; ?>>Privacy Policy</option>
                                    <option value="data_processing" <?php echo $filters['consent_type'] === 'data_processing' ? 'selected' : ''; ?>>Trattamento Dati</option>
                                    <option value="sensitive_data" <?php echo $filters['consent_type'] === 'sensitive_data' ? 'selected' : ''; ?>>Dati Sensibili</option>
                                    <option value="marketing" <?php echo $filters['consent_type'] === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                    <option value="third_party_communication" <?php echo $filters['consent_type'] === 'third_party_communication' ? 'selected' : ''; ?>>Comunicazione Terzi</option>
                                    <option value="image_rights" <?php echo $filters['consent_type'] === 'image_rights' ? 'selected' : ''; ?>>Diritti Immagine</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="consent_given" class="form-label">Stato Consenso</label>
                                <select class="form-select" id="consent_given" name="consent_given">
                                    <option value="">Tutti</option>
                                    <option value="1" <?php echo $filters['consent_given'] === '1' ? 'selected' : ''; ?>>Dato</option>
                                    <option value="0" <?php echo $filters['consent_given'] === '0' ? 'selected' : ''; ?>>Non Dato</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="revoked" class="form-label">Revocato</label>
                                <select class="form-select" id="revoked" name="revoked">
                                    <option value="">Tutti</option>
                                    <option value="1" <?php echo $filters['revoked'] === '1' ? 'selected' : ''; ?>>Sì</option>
                                    <option value="0" <?php echo $filters['revoked'] === '0' ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="expiring_soon" name="expiring_soon"
                                           <?php echo !empty($filters['expiring_soon']) ? 'checked' : ''; ?> 
                                           onchange="toggleExpiring()">
                                    <label class="form-check-label" for="expiring_soon">
                                        Mostra solo consensi in scadenza (prossimi 30 giorni)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabella Consensi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Consensi Privacy</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Entità</th>
                                        <th>Nome</th>
                                        <th>Tipo Consenso</th>
                                        <th>Data Consenso</th>
                                        <th>Scadenza</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($consents)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nessun consenso trovato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($consents as $consent): ?>
                                            <tr>
                                                <td><?php echo $consent['id']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $consent['entity_type'] === 'member' ? 'primary' : 'info'; ?>">
                                                        <?php echo $consent['entity_type'] === 'member' ? 'Socio' : 'Cadetto'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($consent['entity_name'] ?? 'N/D'); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $consent['consent_type'])); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($consent['consent_date'])); ?></td>
                                                <td>
                                                    <?php if ($consent['consent_expiry_date']): ?>
                                                        <?php 
                                                        $expiry = new DateTime($consent['consent_expiry_date']);
                                                        $now = new DateTime();
                                                        $isExpiring = $expiry <= (new DateTime())->modify('+30 days');
                                                        ?>
                                                        <span class="<?php echo $isExpiring ? 'text-danger fw-bold' : ''; ?>">
                                                            <?php echo date('d/m/Y', strtotime($consent['consent_expiry_date'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($consent['revoked']): ?>
                                                        <span class="badge bg-danger">Revocato</span>
                                                    <?php elseif ($consent['consent_given']): ?>
                                                        <span class="badge bg-success">Dato</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Non Dato</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($app->checkPermission('gdpr_compliance', 'manage_consents')): ?>
                                                            <a href="privacy_consent_edit.php?id=<?php echo $consent['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="confirmDelete(<?php echo $consent['id']; ?>)" 
                                                                    title="Elimina">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php
                        $showInfo = true;
                        include __DIR__ . '/../src/Views/includes/pagination.php';
                        ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(consentId) {
            if (confirm('Sei sicuro di voler eliminare questo consenso?')) {
                window.location.href = 'privacy_consent_edit.php?delete=' + consentId;
            }
        }
        
        function toggleExpiring() {
            const urlParams = new URLSearchParams(window.location.search);
            const isChecked = document.getElementById('expiring_soon').checked;
            
            if (isChecked) {
                urlParams.set('expiring_soon', '1');
            } else {
                urlParams.delete('expiring_soon');
            }
            
            urlParams.set('page', '1');
            window.location.search = urlParams.toString();
        }
    </script>
</body>
</html>
