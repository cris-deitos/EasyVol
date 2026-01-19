<?php
/**
 * Log Accessi Dati Sensibili - Sola Lettura
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

if (!$app->checkPermission('gdpr_compliance', 'view_access_logs')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'entity_type' => $_GET['entity_type'] ?? '',
    'access_type' => $_GET['access_type'] ?? '',
    'module' => $_GET['module'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Get data with error handling
try {
    $logs = $controller->indexAccessLogs($filters, $page, $perPage);
    $totalResults = $controller->countAccessLogs($filters);
    $totalPages = max(1, ceil($totalResults / $perPage));
    
    // Get users for filter
    $users = $controller->getUsers();
} catch (Exception $e) {
    error_log("Errore caricamento log accessi: " . $e->getMessage());
    $logs = [];
    $totalResults = 0;
    $totalPages = 1;
    $users = [];
    $error_message = "Errore nel caricamento dei dati. Verificare la connessione al database.";
}

AutoLogger::logPageAccess();

$pageTitle = 'Log Accessi Dati Sensibili';
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
                    <div class="text-muted">
                        <i class="bi bi-info-circle"></i> Solo lettura - Log auditoria GDPR
                    </div>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-2">
                                <label for="user_id" class="form-label">Utente</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">Tutti</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="entity_type" class="form-label">Tipo Entità</label>
                                <select class="form-select" id="entity_type" name="entity_type">
                                    <option value="">Tutti</option>
                                    <option value="member" <?php echo $filters['entity_type'] === 'member' ? 'selected' : ''; ?>>Socio</option>
                                    <option value="junior_member" <?php echo $filters['entity_type'] === 'junior_member' ? 'selected' : ''; ?>>Cadetto</option>
                                    <option value="user" <?php echo $filters['entity_type'] === 'user' ? 'selected' : ''; ?>>Utente</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="access_type" class="form-label">Tipo Accesso</label>
                                <select class="form-select" id="access_type" name="access_type">
                                    <option value="">Tutti</option>
                                    <option value="view" <?php echo $filters['access_type'] === 'view' ? 'selected' : ''; ?>>Visualizzazione</option>
                                    <option value="edit" <?php echo $filters['access_type'] === 'edit' ? 'selected' : ''; ?>>Modifica</option>
                                    <option value="export" <?php echo $filters['access_type'] === 'export' ? 'selected' : ''; ?>>Export</option>
                                    <option value="print" <?php echo $filters['access_type'] === 'print' ? 'selected' : ''; ?>>Stampa</option>
                                    <option value="delete" <?php echo $filters['access_type'] === 'delete' ? 'selected' : ''; ?>>Eliminazione</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Data Da</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Data A</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Filtra
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Log -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Log Accessi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Data/Ora</th>
                                        <th>Utente</th>
                                        <th>Tipo Accesso</th>
                                        <th>Entità</th>
                                        <th>Modulo</th>
                                        <th>IP</th>
                                        <th>Finalità</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nessun accesso registrato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?php echo $log['id']; ?></td>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['accessed_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($log['user_username'] ?? 'N/D'); ?></td>
                                                <td>
                                                    <?php
                                                    $accessColors = [
                                                        'view' => 'info',
                                                        'edit' => 'warning',
                                                        'export' => 'primary',
                                                        'print' => 'secondary',
                                                        'delete' => 'danger'
                                                    ];
                                                    $color = $accessColors[$log['access_type']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($log['access_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst($log['entity_type']); ?>
                                                    </span>
                                                    <?php if ($log['entity_name']): ?>
                                                        <br><small><?php echo htmlspecialchars($log['entity_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['module']); ?></td>
                                                <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($log['purpose'] ?? '-'); ?></td>
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
</body>
</html>
