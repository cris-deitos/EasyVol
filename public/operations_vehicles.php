<?php
/**
 * EasyCO - Mezzi Attivi (Read-Only)
 * 
 * Pagina di visualizzazione limitata per la Centrale Operativa
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login_co.php');
    exit;
}

// Verifica che sia utente CO
$user = $app->getCurrentUser();
if (!isset($user['is_operations_center_user']) || !$user['is_operations_center_user']) {
    die('Accesso negato - Solo per utenti EasyCO');
}

$db = $app->getDb();

// Gestione ricerca e filtri
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Query per ottenere mezzi con dati limitati
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (v.name LIKE ? OR v.license_plate LIKE ? OR v.vehicle_type LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $whereClause .= " AND v.status = ?";
    $params[] = $status;
}

$sql = "SELECT 
    v.id,
    v.name,
    v.license_plate,
    v.vehicle_type,
    v.status,
    v.brand,
    v.model,
    v.year,
    v.fuel_type,
    v.seats
FROM vehicles v
$whereClause
ORDER BY v.name
LIMIT $perPage OFFSET $offset";

$vehicles = $db->fetchAll($sql, $params);

// Conta totale per paginazione
$countSql = "SELECT COUNT(*) as total FROM vehicles v $whereClause";
$totalCount = $db->fetchOne($countSql, $params)['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Statistiche
$stats = [
    'operational' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'operativo'")['count'] ?? 0,
    'maintenance' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'manutenzione'")['count'] ?? 0,
    'out_of_service' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles WHERE status = 'fuori_servizio'")['count'] ?? 0,
];

// Log page access
AutoLogger::logPageAccess();

$pageTitle = 'Mezzi Attivi';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/easyco.css">
</head>
<body>
    <?php include '../src/Views/includes/navbar_operations.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar_operations.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-truck"></i> <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Visualizzazione limitata per la Centrale Operativa. 
                    Sono mostrati solo i dati essenziali dei mezzi.
                </div>
                
                <!-- Statistiche -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card card-stat success">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Mezzi Operativi</h6>
                                <h2 class="card-title mb-0 text-success"><?php echo $stats['operational']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat warning">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">In Manutenzione</h6>
                                <h2 class="card-title mb-0 text-warning"><?php echo $stats['maintenance']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat danger">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Fuori Servizio</h6>
                                <h2 class="card-title mb-0 text-danger"><?php echo $stats['out_of_service']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri e Ricerca -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Cerca per nome, targa o tipo..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-easyco-primary" type="submit">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="">Tutti gli stati</option>
                                    <option value="operativo" <?php echo $status === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                                    <option value="manutenzione" <?php echo $status === 'manutenzione' ? 'selected' : ''; ?>>Manutenzione</option>
                                    <option value="fuori_servizio" <?php echo $status === 'fuori_servizio' ? 'selected' : ''; ?>>Fuori Servizio</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <?php if (!empty($search) || !empty($status)): ?>
                                    <a href="operations_vehicles.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x"></i> Pulisci
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Lista Mezzi -->
                <?php if (empty($vehicles)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i> Nessun mezzo trovato.
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped easyco-table">
                                    <thead>
                                        <tr>
                                            <th>Targa/Matricola</th>
                                            <th>Tipo</th>
                                            <th>Marca/Modello</th>
                                            <th>Stato</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($vehicle['license_plate'])): ?>
                                                        <strong><?php echo htmlspecialchars($vehicle['license_plate']); ?></strong>
                                                    <?php elseif (!empty($vehicle['serial_number'])): ?>
                                                        <strong><?php echo htmlspecialchars($vehicle['serial_number']); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/D</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <i class="bi bi-<?php 
                                                        echo $vehicle['vehicle_type'] === 'veicolo' ? 'truck' : 
                                                            ($vehicle['vehicle_type'] === 'natante' ? 'water' : 'box-seam'); 
                                                    ?>"></i>
                                                    <?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'N/D'); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $brandModel = array_filter([
                                                        $vehicle['brand'] ?? null, 
                                                        $vehicle['model'] ?? null
                                                    ]);
                                                    echo !empty($brandModel) 
                                                        ? htmlspecialchars(implode(' ', $brandModel)) 
                                                        : 'N/D';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusBadge = match($vehicle['status'] ?? '') {
                                                        'operativo' => 'success',
                                                        'manutenzione' => 'warning',
                                                        'fuori_servizio' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                    $statusText = match($vehicle['status'] ?? '') {
                                                        'operativo' => 'Operativo',
                                                        'manutenzione' => 'Manutenzione',
                                                        'fuori_servizio' => 'Fuori Servizio',
                                                        default => 'N/D'
                                                    };
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusBadge; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="operations_vehicle_view.php?id=<?php echo $vehicle['id']; ?>" 
                                                       class="btn btn-sm btn-outline-easyco-primary">
                                                        <i class="bi bi-eye"></i> Visualizza
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4" aria-label="Paginazione mezzi">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
