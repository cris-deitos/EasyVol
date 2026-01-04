<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();
$app->requireLogin();

// Check if user is admin
$user = $app->getCurrentUser();
if (!isset($user['role_name']) || $user['role_name'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso negato. Solo gli amministratori possono accedere a questa pagina.');
}

$db = $app->getDb();

// Log this page view - using AutoLogger for consistency
AutoLogger::logPageAccess();

// Get filter parameters
$filterUserId = $_GET['user_id'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterModule = $_GET['module'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($filterUserId) {
    $where[] = "al.user_id = ?";
    $params[] = $filterUserId;
}

if ($filterAction) {
    $where[] = "al.action = ?";
    $params[] = $filterAction;
}

if ($filterModule) {
    $where[] = "al.module = ?";
    $params[] = $filterModule;
}

// Optimized date filters - use range queries for better index usage
if ($filterDateFrom) {
    $where[] = "al.created_at >= ?";
    $params[] = $filterDateFrom . ' 00:00:00';
}

if ($filterDateTo) {
    $where[] = "al.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $params[] = $filterDateTo . ' 00:00:00';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM activity_logs al $whereClause";
$countResult = $db->fetchOne($countSql, $params);
$totalRecords = $countResult['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);

// Get logs
$sql = "SELECT 
            al.id,
            al.user_id,
            al.action,
            al.module,
            al.record_id,
            al.description,
            al.ip_address,
            al.user_agent,
            al.created_at,
            u.username,
            u.full_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;

$logs = $db->fetchAll($sql, $params);

// Get all users for filter dropdown
$users = $db->fetchAll("SELECT id, username, full_name FROM users ORDER BY username");

// Get distinct actions and modules for filters
$actions = $db->fetchAll("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action");
$modules = $db->fetchAll("SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL ORDER BY module");

// Get statistics - optimized queries for better index usage
$stats = [];
$stats['total_logs'] = $totalRecords;
// Today's logs - using range query instead of DATE() for better index usage
$stats['today_logs'] = $db->fetchOne("SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY")['count'] ?? 0;
// This week's logs - using range query instead of YEARWEEK() for better index usage
$weekStart = date('Y-m-d', strtotime('monday this week'));
$stats['this_week_logs'] = $db->fetchOne("SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= ?", [$weekStart])['count'] ?? 0;
$stats['unique_users'] = $db->fetchOne("SELECT COUNT(DISTINCT user_id) as count FROM activity_logs WHERE user_id IS NOT NULL")['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Attività - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .log-table {
            font-size: 0.9rem;
        }
        .log-description {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .log-description:hover {
            white-space: normal;
            overflow: visible;
        }
        .badge-action {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .filter-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .stats-card {
            border-left: 4px solid;
        }
        .stats-card.primary { border-left-color: #0d6efd; }
        .stats-card.success { border-left-color: #198754; }
        .stats-card.info { border-left-color: #0dcaf0; }
        .stats-card.warning { border-left-color: #ffc107; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-journal-text"></i> Registro Attività Completo
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Stampa
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card primary shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Totale Attività</div>
                                        <h3 class="mb-0"><?= number_format($stats['total_logs']) ?></h3>
                                    </div>
                                    <i class="bi bi-journal-text fs-2 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card success shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Oggi</div>
                                        <h3 class="mb-0"><?= number_format($stats['today_logs']) ?></h3>
                                    </div>
                                    <i class="bi bi-calendar-check fs-2 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card info shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Questa Settimana</div>
                                        <h3 class="mb-0"><?= number_format($stats['this_week_logs']) ?></h3>
                                    </div>
                                    <i class="bi bi-calendar-week fs-2 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card warning shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Utenti Attivi</div>
                                        <h3 class="mb-0"><?= number_format($stats['unique_users']) ?></h3>
                                    </div>
                                    <i class="bi bi-people fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card filter-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-funnel"></i> Filtri
                        </h5>
                        <form method="GET" action="" id="filterForm">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="user_id" class="form-label">Utente</label>
                                    <select class="form-select" name="user_id" id="user_id">
                                        <option value="">Tutti gli utenti</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= $filterUserId == $u['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['full_name'] ?? $u['username']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="action" class="form-label">Azione</label>
                                    <select class="form-select" name="action" id="action">
                                        <option value="">Tutte le azioni</option>
                                        <?php foreach ($actions as $a): ?>
                                            <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($a['action']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="module" class="form-label">Modulo</label>
                                    <select class="form-select" name="module" id="module">
                                        <option value="">Tutti i moduli</option>
                                        <?php foreach ($modules as $m): ?>
                                            <option value="<?= htmlspecialchars($m['module']) ?>" <?= $filterModule === $m['module'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['module']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="date_from" class="form-label">Da</label>
                                    <input type="date" class="form-control" name="date_from" id="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="date_to" class="form-label">A</label>
                                    <input type="date" class="form-control" name="date_to" id="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <a href="activity_logs.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Rimuovi Filtri
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results -->
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul"></i> Attività 
                            <span class="badge bg-secondary"><?= number_format($totalRecords) ?> risultati</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($logs)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">Nessuna attività trovata con i filtri selezionati.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover log-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50">ID</th>
                                            <th width="150">Data/Ora</th>
                                            <th width="150">Utente</th>
                                            <th width="100">Azione</th>
                                            <th width="100">Modulo</th>
                                            <th width="80">Record</th>
                                            <th>Descrizione</th>
                                            <th width="120">IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): 
                                            // Translate action to Italian
                                            $actionLabels = [
                                                'page_view' => 'Visualizzazione',
                                                'create' => 'Creazione',
                                                'update' => 'Modifica',
                                                'edit' => 'Modifica',
                                                'delete' => 'Eliminazione',
                                                'search' => 'Ricerca',
                                                'export' => 'Esportazione',
                                                'login' => 'Login',
                                                'logout' => 'Logout',
                                                'view' => 'Visualizzazione'
                                            ];
                                            $actionLabel = $actionLabels[$log['action']] ?? $log['action'];
                                            
                                            // Get module label in Italian
                                            $moduleLabels = [
                                                'dashboard' => 'Dashboard',
                                                'members' => 'Soci',
                                                'junior_members' => 'Cadetti',
                                                'events' => 'Eventi',
                                                'vehicles' => 'Mezzi',
                                                'warehouse' => 'Magazzino',
                                                'documents' => 'Documenti',
                                                'meetings' => 'Riunioni',
                                                'training' => 'Formazione',
                                                'applications' => 'Domande',
                                                'users' => 'Utenti',
                                                'roles' => 'Ruoli',
                                                'reports' => 'Report',
                                                'settings' => 'Impostazioni',
                                                'profile' => 'Profilo',
                                                'scheduler' => 'Scadenziario',
                                                'operations_center' => 'Centro Operativo',
                                                'radio' => 'Radio',
                                                'dispatch' => 'Dispatch',
                                                'gate_management' => 'Gestione Varchi',
                                                'fee_payments' => 'Quote',
                                                'activity_logs' => 'Log Attività'
                                            ];
                                            $moduleLabel = $moduleLabels[$log['module']] ?? $log['module'];
                                        ?>
                                            <tr>
                                                <td><?= $log['id'] ?></td>
                                                <td>
                                                    <small>
                                                        <?= date('d/m/Y', strtotime($log['created_at'])) ?><br>
                                                        <span class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($log['user_id']): ?>
                                                        <strong><?= htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'N/D') ?></strong><br>
                                                        <small class="text-muted">@<?= htmlspecialchars($log['username'] ?? '') ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sistema</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-action bg-<?= 
                                                        $log['action'] === 'create' ? 'success' : 
                                                        ($log['action'] === 'edit' || $log['action'] === 'update' ? 'primary' : 
                                                        ($log['action'] === 'delete' ? 'danger' : 
                                                        ($log['action'] === 'view' || $log['action'] === 'page_view' ? 'info' : 
                                                        'secondary'))) ?>">
                                                        <?= htmlspecialchars($actionLabel) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($log['module']): ?>
                                                        <span class="badge bg-dark"><?= htmlspecialchars($moduleLabel) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $log['record_id'] ? '<code>#' . $log['record_id'] . '</code>' : '<span class="text-muted">-</span>' ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['description']): ?>
                                                        <div class="log-description" title="<?= htmlspecialchars($log['description']) ?>">
                                                            <?= htmlspecialchars($log['description']) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Navigazione pagine">
                                <ul class="pagination pagination-sm mb-0 justify-content-center">
                                    <?php
                                    $queryString = $_GET;
                                    unset($queryString['page']);
                                    $queryBase = http_build_query($queryString);
                                    $queryBase = $queryBase ? $queryBase . '&' : '';
                                    ?>
                                    
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= $queryBase ?>page=1">Prima</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= $queryBase ?>page=<?= $page - 1 ?>">Precedente</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    ?>
                                    
                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= $queryBase ?>page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= $queryBase ?>page=<?= $page + 1 ?>">Successiva</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= $queryBase ?>page=<?= $totalPages ?>">Ultima</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                <div class="text-center mt-2 small text-muted">
                                    Pagina <?= $page ?> di <?= $totalPages ?> (<?= number_format($totalRecords) ?> totali)
                                </div>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
