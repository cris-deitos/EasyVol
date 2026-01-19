<?php
/**
 * Registro Trattamenti - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\GdprController;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('gdpr_compliance', 'view')) {
    die('Accesso negato');
}

AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

// Gestione filtri
$filters = [
    'legal_basis' => $_GET['legal_basis'] ?? '',
    'is_active' => $_GET['is_active'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Ottieni registro con error handling
try {
    $registries = $controller->indexProcessingRegistry($filters, $page, $perPage);
    $totalResults = $controller->countProcessingRegistry($filters);
    $totalPages = max(1, ceil($totalResults / $perPage));
} catch (Exception $e) {
    error_log("Errore caricamento registro trattamenti: " . $e->getMessage());
    $registries = [];
    $totalResults = 0;
    $totalPages = 1;
    $error_message = "Errore nel caricamento dei dati. Verificare la connessione al database.";
}

// Log search if performed
if (!empty($filters['search'])) {
    AutoLogger::logSearch('data_processing_registry', $filters['search'], $filters);
}

$pageTitle = 'Registro Trattamenti';
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
                        <?php if ($app->checkPermission('gdpr_compliance', 'manage_processing_registry')): ?>
                        <a href="data_processing_registry_edit.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Nuovo Trattamento
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        if ($_GET['success'] === 'deleted') {
                            echo 'Registro trattamento eliminato con successo';
                        } else {
                            echo 'Registro trattamento salvato con successo';
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php
                        if ($_GET['error'] === 'not_found') {
                            echo 'Registro trattamento non trovato';
                        } else {
                            echo 'Si è verificato un errore';
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filtri -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="legal_basis" class="form-label">Base Giuridica</label>
                                <select class="form-select" id="legal_basis" name="legal_basis">
                                    <option value="">Tutte</option>
                                    <option value="consent" <?php echo $filters['legal_basis'] === 'consent' ? 'selected' : ''; ?>>Consenso</option>
                                    <option value="contract" <?php echo $filters['legal_basis'] === 'contract' ? 'selected' : ''; ?>>Contratto</option>
                                    <option value="legal_obligation" <?php echo $filters['legal_basis'] === 'legal_obligation' ? 'selected' : ''; ?>>Obbligo Legale</option>
                                    <option value="vital_interests" <?php echo $filters['legal_basis'] === 'vital_interests' ? 'selected' : ''; ?>>Interessi Vitali</option>
                                    <option value="public_interest" <?php echo $filters['legal_basis'] === 'public_interest' ? 'selected' : ''; ?>>Interesse Pubblico</option>
                                    <option value="legitimate_interest" <?php echo $filters['legal_basis'] === 'legitimate_interest' ? 'selected' : ''; ?>>Interesse Legittimo</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="is_active" class="form-label">Stato</label>
                                <select class="form-select" id="is_active" name="is_active">
                                    <option value="">Tutti</option>
                                    <option value="1" <?php echo $filters['is_active'] === '1' ? 'selected' : ''; ?>>Attivi</option>
                                    <option value="0" <?php echo $filters['is_active'] === '0' ? 'selected' : ''; ?>>Non Attivi</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Nome trattamento..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtra
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Risultati -->
                <div class="card">
                    <div class="card-header">
                        <strong>Trovati <?php echo $totalResults; ?> trattamenti</strong>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($registries)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Nessun registro trattamento trovato</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nome Trattamento</th>
                                            <th>Base Giuridica</th>
                                            <th>Categorie Dati</th>
                                            <th>Trasferimento Paesi Terzi</th>
                                            <th>Stato</th>
                                            <th width="180">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registries as $registry): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($registry['processing_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($registry['processing_purpose'], 0, 80)) . (strlen($registry['processing_purpose']) > 80 ? '...' : ''); ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $basisLabels = [
                                                        'consent' => '<span class="badge bg-primary">Consenso</span>',
                                                        'contract' => '<span class="badge bg-info">Contratto</span>',
                                                        'legal_obligation' => '<span class="badge bg-warning">Obbligo Legale</span>',
                                                        'vital_interests' => '<span class="badge bg-danger">Interessi Vitali</span>',
                                                        'public_interest' => '<span class="badge bg-success">Interesse Pubblico</span>',
                                                        'legitimate_interest' => '<span class="badge bg-secondary">Interesse Legittimo</span>'
                                                    ];
                                                    echo $basisLabels[$registry['legal_basis']] ?? $registry['legal_basis'];
                                                    ?>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars(substr($registry['data_categories'], 0, 50)) . (strlen($registry['data_categories']) > 50 ? '...' : ''); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($registry['third_country_transfer']): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-exclamation-triangle"></i> Sì
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle"></i> No
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($registry['is_active']): ?>
                                                        <span class="badge bg-success">Attivo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non Attivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php if ($app->checkPermission('gdpr_compliance', 'manage_processing_registry')): ?>
                                                            <a href="data_processing_registry_edit.php?id=<?php echo $registry['id']; ?>" 
                                                               class="btn btn-outline-primary" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Paginazione -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Precedente</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Successiva</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
