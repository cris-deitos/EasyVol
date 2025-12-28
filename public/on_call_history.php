<?php
/**
 * Storico Reperibilità Volontari
 * 
 * Pagina per visualizzare lo storico completo delle reperibilità
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
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

// Get filters
$filters = [
    'member_id' => $_GET['member_id'] ?? '',
    'status' => $_GET['status'] ?? 'all', // all, active, past
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;

// Build query
$sql = "SELECT ocs.*, 
        m.first_name, m.last_name, m.registration_number, m.badge_number,
        mc.value as phone,
        rd.name as radio_name,
        rd.identifier as radio_identifier,
        u.username as created_by_username
        FROM on_call_schedule ocs
        JOIN members m ON ocs.member_id = m.id
        LEFT JOIN member_contacts mc ON (m.id = mc.member_id AND mc.contact_type = 'cellulare')
        LEFT JOIN radio_assignments ra ON (m.id = ra.member_id AND ra.status = 'assegnata' AND ra.return_date IS NULL)
        LEFT JOIN radio_directory rd ON ra.radio_id = rd.id
        LEFT JOIN users u ON ocs.created_by = u.id
        WHERE 1=1";

$params = [];

if (!empty($filters['member_id'])) {
    $sql .= " AND ocs.member_id = ?";
    $params[] = intval($filters['member_id']);
}

if ($filters['status'] === 'active') {
    $sql .= " AND ocs.start_datetime <= NOW() AND ocs.end_datetime >= NOW()";
} elseif ($filters['status'] === 'past') {
    $sql .= " AND ocs.end_datetime < NOW()";
}

if (!empty($filters['date_from'])) {
    $sql .= " AND DATE(ocs.start_datetime) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $sql .= " AND DATE(ocs.end_datetime) <= ?";
    $params[] = $filters['date_to'];
}

$sql .= " ORDER BY ocs.start_datetime DESC";

// Get total count
$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as subquery";
$totalResult = $app->getDb()->fetchOne($countSql, $params);
$totalRecords = $totalResult['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);

// Add pagination
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $perPage OFFSET $offset";

$schedules = $app->getDb()->fetchAll($sql, $params);

// Get all active members for filter
$members = $app->getDb()->fetchAll("SELECT id, first_name, last_name, registration_number, badge_number FROM members WHERE member_status = 'attivo' ORDER BY last_name, first_name");

$pageTitle = 'Storico Reperibilità';

$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <h1 class="h2">
                        <a href="operations_center.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>

                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtri</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Volontario</label>
                                <select name="member_id" class="form-select form-select-sm">
                                    <option value="">Tutti</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" 
                                                <?php echo $filters['member_id'] == $member['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['last_name'] . ' ' . $member['first_name']); ?>
                                            (<?php echo htmlspecialchars($member['badge_number'] ?? $member['registration_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Stato</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>
                                        Tutte
                                    </option>
                                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>
                                        In corso
                                    </option>
                                    <option value="past" <?php echo $filters['status'] === 'past' ? 'selected' : ''; ?>>
                                        Concluse
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
                                    <a href="on_call_history.php" class="btn btn-secondary btn-sm">
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
                        <?php if (empty($schedules)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Nessuna reperibilità trovata con i filtri selezionati.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Volontario</th>
                                            <th>Telefono</th>
                                            <th>Radio</th>
                                            <th>Inizio Reperibilità</th>
                                            <th>Fine Reperibilità</th>
                                            <th>Durata</th>
                                            <th>Stato</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <?php
                                            $now = new DateTime();
                                            $start = new DateTime($schedule['start_datetime']);
                                            $end = new DateTime($schedule['end_datetime']);
                                            $isActive = ($now >= $start && $now <= $end);
                                            $isPast = ($now > $end);
                                            $duration = $start->diff($end);
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($schedule['last_name'] . ' ' . $schedule['first_name']); ?></strong>
                                                    <br><small class="text-muted">Mat. <?php echo htmlspecialchars($schedule['badge_number'] ?? $schedule['registration_number']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($schedule['phone']): ?>
                                                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($schedule['phone']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($schedule['radio_name'])): ?>
                                                        <i class="bi bi-broadcast text-success"></i> <?php echo htmlspecialchars($schedule['radio_name']); ?>
                                                        <?php if (!empty($schedule['radio_identifier'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($schedule['radio_identifier']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $start->format('d/m/Y H:i'); ?>
                                                </td>
                                                <td>
                                                    <?php echo $end->format('d/m/Y H:i'); ?>
                                                </td>
                                                <td>
                                                    <?php if ($duration->days > 0): ?>
                                                        <?php echo $duration->days; ?> giorni
                                                    <?php else: ?>
                                                        <?php echo $duration->h; ?>h <?php echo $duration->i; ?>m
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isActive): ?>
                                                        <span class="badge bg-success">In corso</span>
                                                    <?php elseif ($isPast): ?>
                                                        <span class="badge bg-secondary">Conclusa</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Programmata</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($schedule['notes']): ?>
                                                        <small><?php echo htmlspecialchars($schedule['notes']); ?></small>
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
                                <nav aria-label="Navigazione pagine">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1])); ?>">
                                                    <i class="bi bi-chevron-left"></i> Precedente
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
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
