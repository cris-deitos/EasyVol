<?php
/**
 * Gestione Magazzino - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\WarehouseController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('warehouse', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new WarehouseController($db, $config);

$filters = [
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? '',
    'low_stock' => $_GET['low_stock'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$items = $controller->index($filters, $page, $perPage);

// Conteggi
$totalItems = $db->fetchOne("SELECT COUNT(*) as count FROM warehouse_items")['count'] ?? 0;
$lowStock = $db->fetchOne("SELECT COUNT(*) as count FROM warehouse_items WHERE quantity <= minimum_quantity")['count'] ?? 0;

$pageTitle = 'Gestione Magazzino';
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
                        <a href="warehouse_movements.php" class="btn btn-secondary me-2">
                            <i class="bi bi-arrow-left-right"></i> Movimenti
                        </a>
                        <?php if ($app->checkPermission('warehouse', 'create')): ?>
                            <a href="warehouse_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Articolo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Totale Articoli</h5>
                                <h2><?php echo number_format($totalItems); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Scorta Minima</h5>
                                <h2><?php echo number_format($lowStock); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome, codice, descrizione...">
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Categoria</label>
                                <input type="text" class="form-control" id="category" name="category" 
                                       value="<?php echo htmlspecialchars($filters['category']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="disponibile" <?php echo $filters['status'] === 'disponibile' ? 'selected' : ''; ?>>Disponibile</option>
                                    <option value="in_manutenzione" <?php echo $filters['status'] === 'in_manutenzione' ? 'selected' : ''; ?>>In Manutenzione</option>
                                    <option value="fuori_servizio" <?php echo $filters['status'] === 'fuori_servizio' ? 'selected' : ''; ?>>Fuori Servizio</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="low_stock" class="form-label">Scorta Bassa</label>
                                <select class="form-select" id="low_stock" name="low_stock">
                                    <option value="">No</option>
                                    <option value="1" <?php echo $filters['low_stock'] === '1' ? 'selected' : ''; ?>>Sì</option>
                                </select>
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
                
                <!-- Tabella Articoli -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Articoli</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Codice</th>
                                        <th>Nome</th>
                                        <th>Categoria</th>
                                        <th>Quantità</th>
                                        <th>Minimo</th>
                                        <th>Ubicazione</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nessun articolo trovato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['code'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $lowStock = $item['quantity'] <= $item['minimum_quantity'];
                                                    $class = $lowStock ? 'danger' : 'success';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? 'pz'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $item['minimum_quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? 'pz'); ?></td>
                                                <td><?php echo htmlspecialchars($item['location'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'disponibile' => 'success',
                                                        'in_manutenzione' => 'warning',
                                                        'fuori_servizio' => 'danger'
                                                    ];
                                                    $color = $statusColors[$item['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="warehouse_view.php?id=<?php echo $item['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('warehouse', 'edit')): ?>
                                                            <a href="warehouse_edit.php?id=<?php echo $item['id']; ?>" 
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
