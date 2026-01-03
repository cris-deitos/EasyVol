<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\DispatchController;
use EasyVol\Controllers\OperationsCenterController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

$dispatchController = new DispatchController($app->getDb(), $app->getConfig());
$opsController = new OperationsCenterController($app->getDb(), $app->getConfig());

// Get filters
$filters = [];
if (!empty($_GET['slot'])) {
    $filters['slot'] = $_GET['slot'];
}
if (!empty($_GET['event_type'])) {
    $filters['event_type'] = $_GET['event_type'];
}
if (!empty($_GET['radio_dmr_id'])) {
    $filters['radio_dmr_id'] = $_GET['radio_dmr_id'];
}
if (!empty($_GET['talkgroup_id'])) {
    $filters['talkgroup_id'] = $_GET['talkgroup_id'];
}
if (!empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$events = $dispatchController->getEventHistory($filters, $page, 100);

// Get all radios for filter dropdown
$radios = $opsController->indexRadios([], 1, 1000);

// Get all talkgroups for filter dropdown
$talkgroups = $dispatchController->getTalkGroups();

$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
$pageTitle = 'Storico Eventi Radio';
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
                    <h1 class="h2"><i class="bi bi-list-ul"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dispatch.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna al Dispatch
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtri</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Slot</label>
                                <select class="form-select" name="slot">
                                    <option value="">Tutti</option>
                                    <option value="1" <?php echo (isset($_GET['slot']) && $_GET['slot'] === '1') ? 'selected' : ''; ?>>Slot 1</option>
                                    <option value="2" <?php echo (isset($_GET['slot']) && $_GET['slot'] === '2') ? 'selected' : ''; ?>>Slot 2</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tipo Evento</label>
                                <select class="form-select" name="event_type">
                                    <option value="">Tutti</option>
                                    <option value="call_start" <?php echo (isset($_GET['event_type']) && $_GET['event_type'] === 'call_start') ? 'selected' : ''; ?>>Call Start</option>
                                    <option value="call_end" <?php echo (isset($_GET['event_type']) && $_GET['event_type'] === 'call_end') ? 'selected' : ''; ?>>Call End</option>
                                    <option value="registration" <?php echo (isset($_GET['event_type']) && $_GET['event_type'] === 'registration') ? 'selected' : ''; ?>>Registration</option>
                                    <option value="deregistration" <?php echo (isset($_GET['event_type']) && $_GET['event_type'] === 'deregistration') ? 'selected' : ''; ?>>Deregistration</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Radio</label>
                                <select class="form-select" name="radio_dmr_id">
                                    <option value="">Tutte</option>
                                    <?php foreach ($radios as $radio): ?>
                                        <?php if (!empty($radio['dmr_id'])): ?>
                                            <option value="<?php echo htmlspecialchars($radio['dmr_id']); ?>"
                                                    <?php echo (isset($_GET['radio_dmr_id']) && $_GET['radio_dmr_id'] === $radio['dmr_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($radio['name'] . ' (' . $radio['dmr_id'] . ')'); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">TalkGroup</label>
                                <select class="form-select" name="talkgroup_id">
                                    <option value="">Tutti</option>
                                    <?php foreach ($talkgroups as $tg): ?>
                                        <option value="<?php echo htmlspecialchars($tg['talkgroup_id']); ?>"
                                                <?php echo (isset($_GET['talkgroup_id']) && $_GET['talkgroup_id'] === $tg['talkgroup_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tg['name'] . ' (' . $tg['talkgroup_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data Inizio</label>
                                <input type="datetime-local" class="form-control" name="start_date" 
                                       value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data Fine</label>
                                <input type="datetime-local" class="form-control" name="end_date" 
                                       value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Cerca
                                </button>
                                <a href="dispatch_event_history.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Event List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lista Eventi (<?php echo count($events); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data/Ora</th>
                                        <th>Slot</th>
                                        <th>Tipo Evento</th>
                                        <th>Radio</th>
                                        <th>DMR ID</th>
                                        <th>TalkGroup</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($events)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Nessun evento trovato</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($event['event_timestamp']); ?></td>
                                                <td><span class="badge bg-info">Slot <?php echo htmlspecialchars($event['slot']); ?></span></td>
                                                <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                                <td><?php echo htmlspecialchars($event['radio_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($event['radio_dmr_id']); ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($event['talkgroup_name'])) {
                                                        echo htmlspecialchars($event['talkgroup_name'] . ' (' . $event['talkgroup_id'] . ')');
                                                    } elseif (!empty($event['talkgroup_id'])) {
                                                        echo htmlspecialchars($event['talkgroup_id']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
