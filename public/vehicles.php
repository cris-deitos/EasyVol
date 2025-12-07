<?php
/**
 * Gestione Mezzi - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\VehicleController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('vehicles', 'view')) {

// Log page access
AutoLogger::logPageAccess();
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new VehicleController($db, $config);

$filters = [
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$vehicles = $controller->index($filters, $page, $perPage);

// Conteggi per status
$statusCounts = [
    'operativo' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'operativo'")['count'] ?? 0,
    'in_manutenzione' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'in_manutenzione'")['count'] ?? 0,
    'fuori_servizio' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'fuori_servizio'")['count'] ?? 0,
];

$pageTitle = 'Gestione Mezzi';
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
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->checkPermission('vehicles', 'create')): ?>
                            <a href="vehicle_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Mezzo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Mezzi Operativi</h5>
                                <h2><?php echo number_format($statusCounts['operativo']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">In Manutenzione</h5>
                                <h2><?php echo number_format($statusCounts['in_manutenzione']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Fuori Servizio</h5>
                                <h2><?php echo number_format($statusCounts['fuori_servizio']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome, targa, marca...">
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tutti</option>
                                    <option value="veicolo" <?php echo $filters['type'] === 'veicolo' ? 'selected' : ''; ?>>Veicolo</option>
                                    <option value="natante" <?php echo $filters['type'] === 'natante' ? 'selected' : ''; ?>>Natante</option>
                                    <option value="rimorchio" <?php echo $filters['type'] === 'rimorchio' ? 'selected' : ''; ?>>Rimorchio</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="operativo" <?php echo $filters['status'] === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                                    <option value="in_manutenzione" <?php echo $filters['status'] === 'in_manutenzione' ? 'selected' : ''; ?>>In Manutenzione</option>
                                    <option value="fuori_servizio" <?php echo $filters['status'] === 'fuori_servizio' ? 'selected' : ''; ?>>Fuori Servizio</option>
                                    <option value="dismesso" <?php echo $filters['status'] === 'dismesso' ? 'selected' : ''; ?>>Dismesso</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Mezzi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Mezzi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Nome</th>
                                        <th>Targa/Matricola</th>
                                        <th>Marca/Modello</th>
                                        <th>Anno</th>
                                        <th>Scad. Assicurazione</th>
                                        <th>Scad. Revisione</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vehicles)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                Nessun mezzo trovato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-<?php 
                                                        echo $vehicle['vehicle_type'] === 'veicolo' ? 'truck' : 
                                                            ($vehicle['vehicle_type'] === 'natante' ? 'water' : 'box-seam'); 
                                                    ?>"></i>
                                                    <?php echo ucfirst($vehicle['vehicle_type']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($vehicle['name']); ?></td>
                                                <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? $vehicle['serial_number'] ?? '-'); ?></td>
                                                <td>
                                                    <?php 
                                                    $brandModel = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
                                                    echo htmlspecialchars($brandModel ?: '-'); 
                                                    ?>
                                                </td>
                                                <td><?php echo $vehicle['year'] ?? '-'; ?></td>
                                                <td>
                                                    <?php if ($vehicle['insurance_expiry']): ?>
                                                        <?php
                                                        $expiry = new DateTime($vehicle['insurance_expiry']);
                                                        $today = new DateTime();
                                                        $diff = $today->diff($expiry)->days;
                                                        $expired = $expiry < $today;
                                                        $class = $expired ? 'danger' : ($diff <= 30 ? 'warning' : 'success');
                                                        ?>
                                                        <span class="badge bg-<?php echo $class; ?>">
                                                            <?php echo date('d/m/Y', strtotime($vehicle['insurance_expiry'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($vehicle['inspection_expiry']): ?>
                                                        <?php
                                                        $expiry = new DateTime($vehicle['inspection_expiry']);
                                                        $today = new DateTime();
                                                        $diff = $today->diff($expiry)->days;
                                                        $expired = $expiry < $today;
                                                        $class = $expired ? 'danger' : ($diff <= 30 ? 'warning' : 'success');
                                                        ?>
                                                        <span class="badge bg-<?php echo $class; ?>">
                                                            <?php echo date('d/m/Y', strtotime($vehicle['inspection_expiry'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'operativo' => 'success',
                                                        'in_manutenzione' => 'warning',
                                                        'fuori_servizio' => 'danger',
                                                        'dismesso' => 'secondary'
                                                    ];
                                                    $color = $statusColors[$vehicle['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $vehicle['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="vehicle_view.php?id=<?php echo $vehicle['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('vehicles', 'edit')): ?>
                                                            <a href="vehicle_edit.php?id=<?php echo $vehicle['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
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
