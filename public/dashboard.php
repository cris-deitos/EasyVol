<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();
$app->requireLogin();

$db = $app->getDb();
$user = $app->getCurrentUser();

// Get dashboard statistics
$stats = [];

try {
    // Total active members
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status = 'attivo'");
    $stats['active_members'] = $result['count'] ?? 0;
    
    // Total junior members
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM junior_members WHERE member_status = 'attivo'");
    $stats['junior_members'] = $result['count'] ?? 0;
    
    // Pending applications
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'pending'");
    $stats['pending_applications'] = $result['count'] ?? 0;
    
    // Active vehicles
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'operativo'");
    $stats['active_vehicles'] = $result['count'] ?? 0;
    
    // Upcoming events
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE status IN ('aperto', 'in_corso') AND start_date >= NOW()");
    $stats['upcoming_events'] = $result['count'] ?? 0;
    
    // Recent activity logs
    $recentLogs = $db->fetchAll(
        "SELECT al.*, u.username, u.full_name 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC LIMIT 10"
    );
    
    // Upcoming deadlines
    $upcomingDeadlines = $db->fetchAll(
        "SELECT * FROM scheduler_items 
        WHERE status != 'completato' AND due_date >= CURDATE() 
        ORDER BY due_date ASC LIMIT 5"
    );
    
    // Recent notifications
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC LIMIT 5",
        [$user['id']]
    );
    
} catch (Exception $e) {
    error_log($e->getMessage());
}

$app->logActivity('dashboard_view', 'dashboard');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/main.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-speedometer2"></i> Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> Esporta Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Soci Attivi
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_members'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Cadetti
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['junior_members'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-person-badge fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Domande in Sospeso
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['pending_applications'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-hourglass-split fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Mezzi Operativi
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_vehicles'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-truck fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Upcoming Deadlines -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-calendar-check"></i> Scadenze Prossime
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingDeadlines)): ?>
                                    <p class="text-muted">Nessuna scadenza imminente</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcomingDeadlines as $deadline): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($deadline['title']) ?></h6>
                                                    <small class="text-muted"><?= date('d/m/Y', strtotime($deadline['due_date'])) ?></small>
                                                </div>
                                                <span class="badge bg-<?= $deadline['priority'] === 'urgente' ? 'danger' : ($deadline['priority'] === 'alta' ? 'warning' : 'secondary') ?>">
                                                    <?= htmlspecialchars($deadline['priority']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-activity"></i> Attività Recenti
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentLogs)): ?>
                                    <p class="text-muted">Nessuna attività recente</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentLogs as $log): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <small class="mb-1">
                                                        <strong><?= htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'Sistema') ?></strong>
                                                        - <?= htmlspecialchars($log['action']) ?>
                                                    </small>
                                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-grid"></i> Accesso Rapido
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="members.php" class="btn btn-outline-primary btn-block w-100">
                                            <i class="bi bi-people"></i><br>Gestione Soci
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="events.php" class="btn btn-outline-success btn-block w-100">
                                            <i class="bi bi-calendar-event"></i><br>Eventi/Interventi
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="vehicles.php" class="btn btn-outline-info btn-block w-100">
                                            <i class="bi bi-truck"></i><br>Mezzi
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="warehouse.php" class="btn btn-outline-warning btn-block w-100">
                                            <i class="bi bi-box-seam"></i><br>Magazzino
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/main.js"></script>
</body>
</html>
