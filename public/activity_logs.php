<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();
$app->requireLogin();

// Check permission for activity logs viewing
if (!$app->checkPermission('activity_logs', 'view')) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso negato. Non hai i permessi per visualizzare i log delle attività.');
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

// Get logs with meaningful record information
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
            u.full_name,
            -- Members
            m.registration_number as member_reg_number,
            m.badge_number as member_badge_number,
            m.first_name as member_first_name,
            m.last_name as member_last_name,
            -- Junior Members
            jm.registration_number as junior_reg_number,
            jm.first_name as junior_first_name,
            jm.last_name as junior_last_name,
            -- Events
            e.title as event_title,
            e.start_date as event_start_date,
            e.event_type as event_type,
            -- Interventions
            i.title as intervention_title,
            i.start_time as intervention_start_time,
            -- Training Courses
            tc.course_name,
            tc.start_date as course_start_date,
            -- Vehicles
            v.name as vehicle_name,
            v.license_plate as vehicle_license_plate,
            -- Documents
            d.title as document_title,
            -- Meetings
            mt.meeting_type,
            mt.meeting_date,
            mt.title as meeting_title,
            -- Scheduler Items
            si.title as scheduler_title,
            si.due_date as scheduler_due_date,
            -- Users (when record is a user)
            u2.username as target_username,
            u2.full_name as target_user_full_name,
            -- Roles
            r.name as role_name,
            -- Warehouse Items
            wi.name as warehouse_item_name,
            wi.code as warehouse_item_code,
            -- Warehouse Movements
            wm.movement_type as movement_type,
            wm.quantity as movement_quantity,
            wm.created_at as movement_date,
            wmi.name as movement_item_name,
            -- Applications
            ma.application_code,
            ma.application_type,
            -- Gates
            g.gate_number,
            g.name as gate_name,
            -- Radio Directory
            rd.name as radio_name,
            rd.identifier as radio_identifier,
            rd.dmr_id as radio_dmr_id,
            -- Dispatch Talkgroups
            dt.name as talkgroup_name,
            dt.talkgroup_id as talkgroup_id
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN members m ON al.module IN ('member', 'members', 'member_portal') AND al.record_id = m.id
        LEFT JOIN junior_members jm ON al.module IN ('junior_member', 'junior_members') AND al.record_id = jm.id
        LEFT JOIN events e ON al.module IN ('event', 'events', 'event_participants', 'event_vehicles') AND al.record_id = e.id
        LEFT JOIN interventions i ON al.module IN ('interventions', 'intervention_members', 'intervention_vehicles') AND al.record_id = i.id
        LEFT JOIN training_courses tc ON al.module = 'training' AND al.record_id = tc.id
        LEFT JOIN vehicles v ON al.module IN ('vehicle', 'vehicles', 'vehicle_maintenance') AND al.record_id = v.id
        LEFT JOIN documents d ON al.module = 'documents' AND al.record_id = d.id
        LEFT JOIN meetings mt ON al.module IN ('meeting', 'meetings') AND al.record_id = mt.id
        LEFT JOIN scheduler_items si ON al.module = 'scheduler' AND al.record_id = si.id
        LEFT JOIN users u2 ON al.module = 'users' AND al.record_id = u2.id
        LEFT JOIN roles r ON al.module = 'roles' AND al.record_id = r.id
        LEFT JOIN warehouse_items wi ON al.module IN ('warehouse', 'warehouse_item', 'warehouse_items') AND al.record_id = wi.id
        LEFT JOIN warehouse_movements wm ON al.module = 'warehouse_movement' AND al.record_id = wm.id
        LEFT JOIN warehouse_items wmi ON wm.item_id = wmi.id
        LEFT JOIN member_applications ma ON al.module = 'applications' AND al.record_id = ma.id
        LEFT JOIN gates g ON al.module = 'gate_management' AND al.record_id = g.id
        LEFT JOIN radio_directory rd ON al.module = 'radio' AND al.record_id = rd.id
        LEFT JOIN dispatch_talkgroups dt ON al.module = 'dispatch' AND al.record_id = dt.id
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

/**
 * Format the record identifier based on module type
 */
function formatRecordInfo($log) {
    if (!$log['record_id']) {
        return '<span class="text-muted">-</span>';
    }
    
    $module = $log['module'];
    $output = '';
    
    switch ($module) {
        case 'member':
        case 'members':
            if ($log['member_reg_number'] || $log['member_badge_number']) {
                $identifier = $log['member_badge_number'] ?: $log['member_reg_number'];
                $name = trim(($log['member_first_name'] ?? '') . ' ' . ($log['member_last_name'] ?? ''));
                $output = '<strong>' . htmlspecialchars($identifier) . '</strong>';
                if ($name) {
                    $output .= '<br><small class="text-muted">' . htmlspecialchars($name) . '</small>';
                }
            }
            break;
            
        case 'junior_member':
        case 'junior_members':
            if ($log['junior_reg_number']) {
                $name = trim(($log['junior_first_name'] ?? '') . ' ' . ($log['junior_last_name'] ?? ''));
                $output = '<strong>' . htmlspecialchars($log['junior_reg_number']) . '</strong>';
                if ($name) {
                    $output .= '<br><small class="text-muted">' . htmlspecialchars($name) . '</small>';
                }
            }
            break;
            
        case 'event':
        case 'events':
        case 'event_participants':
        case 'event_vehicles':
            if ($log['event_title']) {
                $output = '<strong>' . htmlspecialchars($log['event_title']) . '</strong>';
                if ($log['event_start_date']) {
                    $output .= '<br><small class="text-muted">' . date('d/m/Y', strtotime($log['event_start_date'])) . '</small>';
                }
            }
            break;
            
        case 'interventions':
        case 'intervention_members':
        case 'intervention_vehicles':
            if ($log['intervention_title']) {
                $output = '<strong>' . htmlspecialchars($log['intervention_title']) . '</strong>';
                if ($log['intervention_start_time']) {
                    $output .= '<br><small class="text-muted">' . date('d/m/Y H:i', strtotime($log['intervention_start_time'])) . '</small>';
                }
            }
            break;
            
        case 'training':
            if ($log['course_name']) {
                $output = '<strong>' . htmlspecialchars($log['course_name']) . '</strong>';
                if ($log['course_start_date']) {
                    $output .= '<br><small class="text-muted">Inizio: ' . date('d/m/Y', strtotime($log['course_start_date'])) . '</small>';
                }
            }
            break;
            
        case 'vehicle':
        case 'vehicles':
        case 'vehicle_maintenance':
            if ($log['vehicle_license_plate'] || $log['vehicle_name']) {
                $output = '<strong>' . htmlspecialchars($log['vehicle_license_plate'] ?: $log['vehicle_name']) . '</strong>';
                if ($log['vehicle_license_plate'] && $log['vehicle_name']) {
                    $output .= '<br><small class="text-muted">' . htmlspecialchars($log['vehicle_name']) . '</small>';
                }
            }
            break;
            
        case 'documents':
            if ($log['document_title']) {
                $output = '<strong>' . htmlspecialchars($log['document_title']) . '</strong>';
            }
            break;
            
        case 'meeting':
        case 'meetings':
            if ($log['meeting_type']) {
                $meetingTypes = [
                    'assemblea_ordinaria' => 'Assemblea Ordinaria',
                    'assemblea_straordinaria' => 'Assemblea Straordinaria',
                    'consiglio_direttivo' => 'Consiglio Direttivo',
                    'riunione_capisquadra' => 'Riunione Capisquadra',
                    'riunione_nucleo' => 'Riunione Nucleo',
                    'altra_riunione' => 'Altra Riunione'
                ];
                $typeLabel = $meetingTypes[$log['meeting_type']] ?? $log['meeting_type'];
                $output = '<strong>' . htmlspecialchars($log['meeting_title'] ?: $typeLabel) . '</strong>';
                if ($log['meeting_date']) {
                    $output .= '<br><small class="text-muted">' . date('d/m/Y', strtotime($log['meeting_date'])) . '</small>';
                }
            }
            break;
            
        case 'scheduler':
            if ($log['scheduler_title']) {
                $output = '<strong>' . htmlspecialchars($log['scheduler_title']) . '</strong>';
                if ($log['scheduler_due_date']) {
                    $output .= '<br><small class="text-muted">Scadenza: ' . date('d/m/Y', strtotime($log['scheduler_due_date'])) . '</small>';
                }
            }
            break;
            
        case 'users':
            if ($log['target_username']) {
                $output = '<strong>@' . htmlspecialchars($log['target_username']) . '</strong>';
                if ($log['target_user_full_name']) {
                    $output .= '<br><small class="text-muted">' . htmlspecialchars($log['target_user_full_name']) . '</small>';
                }
            }
            break;
            
        case 'roles':
            if ($log['role_name']) {
                $output = '<strong>' . htmlspecialchars($log['role_name']) . '</strong>';
            }
            break;
            
        case 'warehouse':
        case 'warehouse_item':
        case 'warehouse_items':
            if ($log['warehouse_item_name']) {
                $output = '<strong>' . htmlspecialchars($log['warehouse_item_name']) . '</strong>';
                if ($log['warehouse_item_code']) {
                    $output .= '<br><small class="text-muted">Cod. ' . htmlspecialchars($log['warehouse_item_code']) . '</small>';
                }
            }
            break;
            
        case 'warehouse_movement':
            if ($log['movement_item_name']) {
                $movementTypes = [
                    'carico' => 'Carico',
                    'scarico' => 'Scarico',
                    'assegnazione' => 'Assegnazione',
                    'restituzione' => 'Restituzione',
                    'trasferimento' => 'Trasferimento'
                ];
                $typeLabel = $movementTypes[$log['movement_type']] ?? $log['movement_type'];
                $output = '<strong>' . htmlspecialchars($log['movement_item_name']) . '</strong>';
                $output .= '<br><small class="text-muted">' . $typeLabel . ': ' . $log['movement_quantity'] . '</small>';
            }
            break;
            
        case 'applications':
            if ($log['application_code']) {
                $typeLabels = ['adult' => 'Socio', 'junior' => 'Cadetto'];
                $typeLabel = $typeLabels[$log['application_type']] ?? '';
                $output = '<strong>' . htmlspecialchars($log['application_code']) . '</strong>';
                if ($typeLabel) {
                    $output .= '<br><small class="text-muted">' . $typeLabel . '</small>';
                }
            }
            break;
            
        case 'gate_management':
            if ($log['gate_number'] || $log['gate_name']) {
                $output = '<strong>' . htmlspecialchars($log['gate_number'] ?: $log['gate_name']) . '</strong>';
                if ($log['gate_number'] && $log['gate_name']) {
                    $output .= '<br><small class="text-muted">' . htmlspecialchars($log['gate_name']) . '</small>';
                }
            }
            break;
            
        case 'radio':
            if ($log['radio_name']) {
                $output = '<strong>' . htmlspecialchars($log['radio_name']) . '</strong>';
                if ($log['radio_dmr_id']) {
                    $output .= '<br><small class="text-muted">DMR: ' . htmlspecialchars($log['radio_dmr_id']) . '</small>';
                } elseif ($log['radio_identifier']) {
                    $output .= '<br><small class="text-muted">' . htmlspecialchars($log['radio_identifier']) . '</small>';
                }
            }
            break;
            
        case 'dispatch':
            if ($log['talkgroup_name']) {
                $output = '<strong>' . htmlspecialchars($log['talkgroup_name']) . '</strong>';
                if ($log['talkgroup_id']) {
                    $output .= '<br><small class="text-muted">TG: ' . htmlspecialchars($log['talkgroup_id']) . '</small>';
                }
            }
            break;
            
        case 'member_portal':
            // Member portal activities related to members show member info
            if ($log['member_reg_number'] || $log['member_badge_number']) {
                $identifier = $log['member_badge_number'] ?: $log['member_reg_number'];
                $name = trim(($log['member_first_name'] ?? '') . ' ' . ($log['member_last_name'] ?? ''));
                $output = '<strong>' . htmlspecialchars($identifier) . '</strong>';
                if ($name) {
                    $output .= '<br><small class="text-muted">' . htmlspecialchars($name) . '</small>';
                }
            }
            break;
    }
    
    // If we couldn't determine the record info, show the ID as fallback
    if (empty($output)) {
        $output = '<code>#' . $log['record_id'] . '</code>';
    }
    
    return $output;
}

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
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
                                            <th width="180">Record</th>
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
                                                'applications' => 'Domande Iscrizione',
                                                'users' => 'Utenti',
                                                'roles' => 'Ruoli',
                                                'reports' => 'Report',
                                                'settings' => 'Impostazioni',
                                                'profile' => 'Profilo',
                                                'scheduler' => 'Scadenziario',
                                                'operations_center' => 'Centrale Operativa',
                                                'radio' => 'Radio',
                                                'dispatch' => 'Dispatch',
                                                'gate_management' => 'Gestione Varchi',
                                                'structure_management' => 'Gestione Strutture',
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
                                                    <?= formatRecordInfo($log) ?>
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
