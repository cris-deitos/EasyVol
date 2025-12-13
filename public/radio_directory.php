<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

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

// Handle filters
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['device_type'])) {
    $filters['device_type'] = $_GET['device_type'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$radios = $controller->indexRadios($filters, $page, 50);
$stats = $controller->getRadioStats();

$pageTitle = 'Rubrica Radio';
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
    // Use EasyCO components if user is CO user
    $user = $app->getCurrentUser();
    $isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
    
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
                    <h1 class="h2"><i class="bi bi-broadcast"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->checkPermission('operations_center', 'create')): ?>
                            <a href="radio_edit.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Radio
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Totale Radio</h6>
                                <h2 class="card-title mb-0"><?php echo $stats['total']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Disponibili</h6>
                                <h2 class="card-title mb-0 text-success">
                                    <?php 
                                    $available = 0;
                                    foreach ($stats['by_status'] as $stat) {
                                        if ($stat['status'] === 'disponibile') {
                                            $available = $stat['count'];
                                        }
                                    }
                                    echo $available;
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Assegnate</h6>
                                <h2 class="card-title mb-0 text-warning"><?php echo $stats['assigned']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">In Manutenzione</h6>
                                <h2 class="card-title mb-0 text-danger">
                                    <?php 
                                    $maintenance = 0;
                                    foreach ($stats['by_status'] as $stat) {
                                        if ($stat['status'] === 'manutenzione') {
                                            $maintenance = $stat['count'];
                                        }
                                    }
                                    echo $maintenance;
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti gli stati</option>
                                    <option value="disponibile" <?php echo (isset($filters['status']) && $filters['status'] === 'disponibile') ? 'selected' : ''; ?>>Disponibile</option>
                                    <option value="assegnata" <?php echo (isset($filters['status']) && $filters['status'] === 'assegnata') ? 'selected' : ''; ?>>Assegnata</option>
                                    <option value="manutenzione" <?php echo (isset($filters['status']) && $filters['status'] === 'manutenzione') ? 'selected' : ''; ?>>Manutenzione</option>
                                    <option value="fuori_servizio" <?php echo (isset($filters['status']) && $filters['status'] === 'fuori_servizio') ? 'selected' : ''; ?>>Fuori Servizio</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="device_type" class="form-label">Tipo Dispositivo</label>
                                <select class="form-select" id="device_type" name="device_type">
                                    <option value="">Tutti i tipi</option>
                                    <option value="Radio Portatile" <?php echo (isset($filters['device_type']) && $filters['device_type'] === 'Radio Portatile') ? 'selected' : ''; ?>>Radio Portatile</option>
                                    <option value="Radio Veicolare" <?php echo (isset($filters['device_type']) && $filters['device_type'] === 'Radio Veicolare') ? 'selected' : ''; ?>>Radio Veicolare</option>
                                    <option value="Stazione Base" <?php echo (isset($filters['device_type']) && $filters['device_type'] === 'Stazione Base') ? 'selected' : ''; ?>>Stazione Base</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Ricerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>"
                                       placeholder="Nome, identificativo, seriale...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Cerca
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Radios Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Radio</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($radios)): ?>
                            <p class="text-muted mb-0">Nessuna radio trovata</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Identificativo</th>
                                            <th>Tipo</th>
                                            <th>Marca/Modello</th>
                                            <th>Seriale</th>
                                            <th>Stato</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($radios as $radio): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($radio['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($radio['identifier'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($radio['device_type'] ?? '-'); ?></td>
                                                <td>
                                                    <?php 
                                                    $brand = $radio['brand'] ?? '';
                                                    $model = $radio['model'] ?? '';
                                                    echo htmlspecialchars(trim("$brand $model")) ?: '-';
                                                    ?>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($radio['serial_number'] ?? '-'); ?></code></td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'disponibile' => 'success',
                                                        'assegnata' => 'warning',
                                                        'manutenzione' => 'danger',
                                                        'fuori_servizio' => 'secondary'
                                                    ];
                                                    $class = $statusClass[$radio['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $radio['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="radio_view.php?id=<?php echo $radio['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Visualizza">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($app->checkPermission('operations_center', 'edit')): ?>
                                                        <a href="radio_edit.php?id=<?php echo $radio['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary" title="Modifica">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
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
</body>
</html>
