<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\SchedulerController;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('scheduler', 'view')) {
    die('Accesso negato');
}

$controller = new SchedulerController($app->getDb(), $app->getConfig());

// Handle filters
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}
if (!empty($_GET['category'])) {
    $filters['category'] = $_GET['category'];
}
if (!empty($_GET['from_date'])) {
    $filters['from_date'] = $_GET['from_date'];
}
if (!empty($_GET['to_date'])) {
    $filters['to_date'] = $_GET['to_date'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items = $controller->index($filters, $page, 50);
$stats = $controller->getStats();
$counts = $controller->getCounts();

$pageTitle = 'Scadenzario';
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
    <?php include '../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-calendar-check"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->checkPermission('scheduler', 'create')): ?>
                            <a href="scheduler_edit.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Scadenza
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Attive</h6>
                                <h2 class="card-title mb-0"><?php echo $counts['active']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Scadute</h6>
                                <h2 class="card-title mb-0 text-danger"><?php echo $counts['overdue']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Questa Settimana</h6>
                                <h2 class="card-title mb-0 text-warning"><?php echo $counts['this_week']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Urgenti</h6>
                                <h2 class="card-title mb-0 text-danger"><?php echo $counts['urgent']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="in_attesa" <?php echo (isset($filters['status']) && $filters['status'] === 'in_attesa') ? 'selected' : ''; ?>>In Attesa</option>
                                    <option value="in_corso" <?php echo (isset($filters['status']) && $filters['status'] === 'in_corso') ? 'selected' : ''; ?>>In Corso</option>
                                    <option value="completato" <?php echo (isset($filters['status']) && $filters['status'] === 'completato') ? 'selected' : ''; ?>>Completato</option>
                                    <option value="scaduto" <?php echo (isset($filters['status']) && $filters['status'] === 'scaduto') ? 'selected' : ''; ?>>Scaduto</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="priority" class="form-label">Priorità</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="">Tutte</option>
                                    <option value="urgente" <?php echo (isset($filters['priority']) && $filters['priority'] === 'urgente') ? 'selected' : ''; ?>>Urgente</option>
                                    <option value="alta" <?php echo (isset($filters['priority']) && $filters['priority'] === 'alta') ? 'selected' : ''; ?>>Alta</option>
                                    <option value="media" <?php echo (isset($filters['priority']) && $filters['priority'] === 'media') ? 'selected' : ''; ?>>Media</option>
                                    <option value="bassa" <?php echo (isset($filters['priority']) && $filters['priority'] === 'bassa') ? 'selected' : ''; ?>>Bassa</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="from_date" class="form-label">Dal</label>
                                <input type="date" class="form-control" id="from_date" name="from_date" 
                                       value="<?php echo isset($filters['from_date']) ? htmlspecialchars($filters['from_date']) : ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="to_date" class="form-label">Al</label>
                                <input type="date" class="form-control" id="to_date" name="to_date" 
                                       value="<?php echo isset($filters['to_date']) ? htmlspecialchars($filters['to_date']) : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Ricerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>"
                                       placeholder="Titolo, descrizione...">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Scadenze</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($items)): ?>
                            <p class="text-muted mb-0">Nessuna scadenza trovata</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Scadenza</th>
                                            <th>Titolo</th>
                                            <th>Categoria</th>
                                            <th>Priorità</th>
                                            <th>Assegnato a</th>
                                            <th>Stato</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $dueDate = new DateTime($item['due_date']);
                                            $today = new DateTime();
                                            $diff = $today->diff($dueDate);
                                            $isOverdue = $dueDate < $today && $item['status'] !== 'completato';
                                            $rowClass = $isOverdue ? 'table-danger' : '';
                                            ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td>
                                                    <strong><?php echo $dueDate->format('d/m/Y'); ?></strong>
                                                    <?php if ($isOverdue): ?>
                                                        <br><small class="text-danger">
                                                            <i class="bi bi-exclamation-triangle"></i> Scaduta
                                                        </small>
                                                    <?php elseif ($diff->days <= 7 && !$diff->invert): ?>
                                                        <br><small class="text-warning">
                                                            <i class="bi bi-clock"></i> <?php echo $diff->days; ?> giorni
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                    <?php if ($item['description']): ?>
                                                        <br><small class="text-muted">
                                                            <?php echo htmlspecialchars(substr($item['description'], 0, 60)); ?>
                                                            <?php echo strlen($item['description']) > 60 ? '...' : ''; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['category']): ?>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $priorityClass = [
                                                        'urgente' => 'danger',
                                                        'alta' => 'warning',
                                                        'media' => 'info',
                                                        'bassa' => 'secondary'
                                                    ];
                                                    $class = $priorityClass[$item['priority']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo ucfirst($item['priority']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['assigned_name'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'in_attesa' => 'secondary',
                                                        'in_corso' => 'primary',
                                                        'completato' => 'success',
                                                        'scaduto' => 'danger'
                                                    ];
                                                    $class = $statusClass[$item['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($app->checkPermission('scheduler', 'edit')): ?>
                                                        <a href="scheduler_edit.php?id=<?php echo $item['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary" title="Modifica">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if ($item['status'] !== 'completato'): ?>
                                                            <button class="btn btn-sm btn-outline-success" 
                                                                    onclick="markComplete(<?php echo $item['id']; ?>)" 
                                                                    title="Segna come completato">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markComplete(id) {
            if (confirm('Segnare questa scadenza come completata?')) {
                // This would be implemented with AJAX in production
                window.location.href = 'scheduler_complete.php?id=' + id;
            }
        }
    </script>
</body>
</html>
