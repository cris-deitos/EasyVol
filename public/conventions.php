<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ConventionController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('conventions', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$controller = new ConventionController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!$app->checkPermission('conventions', 'delete')) {
        die('Accesso negato');
    }
    if ($csrf->validateToken($_POST['csrf_token'] ?? '')) {
        $controller->delete((int)$_POST['id'], $_SESSION['user_id']);
        header('Location: conventions.php?deleted=1');
        exit;
    }
}

// Handle filters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items = $controller->index($filters, $page, 50);
$totalItems = $controller->count($filters);

$pageTitle = 'Convenzioni';
include '../src/Views/includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include '../src/Views/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-file-earmark-text"></i> Convenzioni</h1>
                <?php if ($app->checkPermission('conventions', 'create')): ?>
                <a href="convention_edit.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuova Convenzione
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Convenzione eliminata con successo.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Cerca..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">Tutte</option>
                                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Attive</option>
                                <option value="expired" <?= ($filters['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Scadute</option>
                                <option value="future" <?= ($filters['status'] ?? '') === 'future' ? 'selected' : '' ?>>Future</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtra</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Data Inizio</th>
                            <th>Data Fine</th>
                            <th>Enti</th>
                            <th>Scadenze</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="text-center text-muted">Nessuna convenzione trovata</td></tr>
                        <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <?php
                            $now = date('Y-m-d');
                            $statusBadge = 'bg-success';
                            $statusText = 'Attiva';
                            if (!empty($item['end_date']) && $item['end_date'] < $now) {
                                $statusBadge = 'bg-danger';
                                $statusText = 'Scaduta';
                            } elseif ($item['start_date'] > $now) {
                                $statusBadge = 'bg-info';
                                $statusText = 'Futura';
                            }
                        ?>
                        <tr>
                            <td><a href="convention_view.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?></a></td>
                            <td><?= date('d/m/Y', strtotime($item['start_date'])) ?></td>
                            <td><?= $item['end_date'] ? date('d/m/Y', strtotime($item['end_date'])) : '-' ?></td>
                            <td><span class="badge bg-secondary"><?= $item['entity_count'] ?></span></td>
                            <td><span class="badge bg-secondary"><?= $item['deadline_count'] ?></span></td>
                            <td><span class="badge <?= $statusBadge ?>"><?= $statusText ?></span></td>
                            <td>
                                <a href="convention_view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizza"><i class="bi bi-eye"></i></a>
                                <?php if ($app->checkPermission('conventions', 'edit')): ?>
                                <a href="convention_edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-warning" title="Modifica"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                                <?php if ($app->checkPermission('conventions', 'delete')): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Eliminare questa convenzione?')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf->getToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Elimina"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
