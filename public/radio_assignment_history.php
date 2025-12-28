<?php
/**
 * Storico Assegnazioni Radio
 * 
 * Pagina per visualizzare lo storico completo delle assegnazioni radio
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\OperationsCenterController;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDb(), $app->getConfig());

// Get filters
$filters = [
    'radio_id' => $_GET['radio_id'] ?? '',
    'member_id' => $_GET['member_id'] ?? '',
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;

// Get all radio assignments with filters
$sql = "SELECT ra.*, 
        rd.name as radio_name, rd.identifier as radio_identifier,
        m.first_name, m.last_name, m.registration_number,
        u1.username as assigned_by_username,
        u2.username as returned_by_username
        FROM radio_assignments ra
        LEFT JOIN radio_directory rd ON ra.radio_id = rd.id
        LEFT JOIN members m ON ra.member_id = m.id
        LEFT JOIN users u1 ON ra.assigned_by = u1.id
        LEFT JOIN users u2 ON ra.return_by = u2.id
        WHERE 1=1";

$params = [];

if (!empty($filters['radio_id'])) {
    $sql .= " AND ra.radio_id = ?";
    $params[] = intval($filters['radio_id']);
}

if (!empty($filters['member_id'])) {
    $sql .= " AND ra.member_id = ?";
    $params[] = intval($filters['member_id']);
}

if (!empty($filters['status'])) {
    $sql .= " AND ra.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['date_from'])) {
    $sql .= " AND DATE(ra.assignment_date) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $sql .= " AND DATE(ra.assignment_date) <= ?";
    $params[] = $filters['date_to'];
}

$sql .= " ORDER BY ra.assignment_date DESC";

// Get total count
$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as subquery";
$totalResult = $app->getDb()->fetchOne($countSql, $params);
$totalRecords = $totalResult['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);

// Add pagination
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $perPage OFFSET $offset";

$assignments = $app->getDb()->fetchAll($sql, $params);

// Get all radios for filter
$radios = $app->getDb()->fetchAll("SELECT id, name, identifier FROM radio_directory ORDER BY name");

// Get all active members for filter
$members = $app->getDb()->fetchAll("SELECT id, first_name, last_name, registration_number FROM members WHERE member_status = 'attivo' ORDER BY last_name, first_name");

$pageTitle = 'Storico Assegnazioni Radio';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    $user = $app->getCurrentUser();
    $isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
    ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo $isCoUser ? 'EasyCO' : 'EasyVol'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <?php if ($isCoUser): ?>
        <link rel="stylesheet" href="../assets/css/easyco.css">
    <?php endif; ?>
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
                    <h1 class="h2"><i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="radio_directory.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna alla Rubrica Radio
                        </a>
                    </div>
                </div>

                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-filter"></i> Filtri di Ricerca</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Radio</label>
                                <select name="radio_id" class="form-select form-select-sm">
                                    <option value="">Tutte le radio</option>
                                    <?php foreach ($radios as $radio): ?>
                                        <option value="<?php echo $radio['id']; ?>" 
                                                <?php echo $filters['radio_id'] == $radio['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($radio['name']); ?>
                                            <?php if ($radio['identifier']): ?>
                                                (<?php echo htmlspecialchars($radio['identifier']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Volontario</label>
                                <select name="member_id" class="form-select form-select-sm">
                                    <option value="">Tutti i volontari</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" 
                                                <?php echo $filters['member_id'] == $member['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['last_name'] . ' ' . $member['first_name']); ?>
                                            <?php if ($member['registration_number']): ?>
                                                (<?php echo htmlspecialchars($member['registration_number']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Stato</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">Tutti gli stati</option>
                                    <option value="assegnata" <?php echo $filters['status'] === 'assegnata' ? 'selected' : ''; ?>>
                                        Assegnata
                                    </option>
                                    <option value="restituita" <?php echo $filters['status'] === 'restituita' ? 'selected' : ''; ?>>
                                        Restituita
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Data Da</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Data A</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-search"></i> Filtra
                                </button>
                                <?php if (array_filter($filters)): ?>
                                    <a href="radio_assignment_history.php" class="btn btn-secondary btn-sm">
                                        <i class="bi bi-x-circle"></i> Reset Filtri
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Risultati (<?php echo $totalRecords; ?> totali)</h5>
                            <div>
                                <small class="text-muted">Pagina <?php echo $page; ?> di <?php echo max(1, $totalPages); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Nessuna assegnazione trovata con i filtri selezionati.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Radio</th>
                                            <th>Assegnata a</th>
                                            <th>Tipo</th>
                                            <th>Data Assegnazione</th>
                                            <th>Assegnata da</th>
                                            <th>Data Restituzione</th>
                                            <th>Restituita a</th>
                                            <th>Stato</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['radio_name']); ?></strong>
                                                    <?php if ($assignment['radio_identifier']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($assignment['radio_identifier']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['member_id']): ?>
                                                        <strong><?php echo htmlspecialchars($assignment['last_name'] . ' ' . $assignment['first_name']); ?></strong>
                                                        <?php if ($assignment['registration_number']): ?>
                                                            <br><small class="text-muted">Mat. <?php echo htmlspecialchars($assignment['registration_number']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <strong><?php echo htmlspecialchars($assignment['assignee_last_name'] . ' ' . $assignment['assignee_first_name']); ?></strong>
                                                        <?php if ($assignment['assignee_organization']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($assignment['assignee_organization']); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['member_id']): ?>
                                                        <span class="badge bg-primary">Volontario</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Esterno</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($assignment['assignment_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($assignment['assigned_by_username'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['return_date']): ?>
                                                        <?php echo date('d/m/Y H:i', strtotime($assignment['return_date'])); ?>
                                                        <?php
                                                        $assignmentDate = new DateTime($assignment['assignment_date']);
                                                        $returnDate = new DateTime($assignment['return_date']);
                                                        $duration = $assignmentDate->diff($returnDate);
                                                        ?>
                                                        <br><small class="text-muted">
                                                            Durata: 
                                                            <?php if ($duration->days > 0): ?>
                                                                <?php echo $duration->days; ?> giorni
                                                            <?php else: ?>
                                                                <?php echo $duration->h; ?>h <?php echo $duration->i; ?>m
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($assignment['returned_by_username'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'assegnata' => 'warning',
                                                        'restituita' => 'success'
                                                    ];
                                                    $class = $statusClass[$assignment['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo ucfirst($assignment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($assignment['notes'])): ?>
                                                        <small><?php echo htmlspecialchars($assignment['notes']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav class="mt-3">
                                    <ul class="pagination pagination-sm justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1])); ?>">
                                                    <i class="bi bi-chevron-left"></i> Precedente
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        if ($startPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => 1])); ?>">1</a>
                                            </li>
                                            <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($endPage < $totalPages): ?>
                                            <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $totalPages])); ?>">
                                                    <?php echo $totalPages; ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $page + 1])); ?>">
                                                    Successiva <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
