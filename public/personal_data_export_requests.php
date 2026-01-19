<?php
/**
 * Gestione Richieste Export Dati Personali - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\GdprController;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('gdpr_compliance', 'export_personal_data')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

$filters = [
    'entity_type' => $_GET['entity_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$requests = $controller->indexExportRequests($filters, $page, $perPage);
$totalResults = $controller->countExportRequests($filters);
$totalPages = max(1, ceil($totalResults / $perPage));

AutoLogger::logPageAccess();

$pageTitle = 'Richieste Export Dati Personali';
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
                        <?php if ($app->checkPermission('gdpr_compliance', 'export_personal_data')): ?>
                            <a href="personal_data_export_request_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Richiesta
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome, cognome...">
                            </div>
                            <div class="col-md-3">
                                <label for="entity_type" class="form-label">Tipo Entità</label>
                                <select class="form-select" id="entity_type" name="entity_type">
                                    <option value="">Tutti</option>
                                    <option value="member" <?php echo $filters['entity_type'] === 'member' ? 'selected' : ''; ?>>Socio</option>
                                    <option value="junior_member" <?php echo $filters['entity_type'] === 'junior_member' ? 'selected' : ''; ?>>Cadetto</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>In Attesa</option>
                                    <option value="processing" <?php echo $filters['status'] === 'processing' ? 'selected' : ''; ?>>In Elaborazione</option>
                                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completata</option>
                                    <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rifiutata</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Richieste Export</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Entità</th>
                                        <th>Nome</th>
                                        <th>Data Richiesta</th>
                                        <th>Stato</th>
                                        <th>Data Completamento</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($requests)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">
                                                Nessuna richiesta trovata
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['id']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $request['entity_type'] === 'member' ? 'primary' : 'info'; ?>">
                                                        <?php echo $request['entity_type'] === 'member' ? 'Socio' : 'Cadetto'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($request['entity_name'] ?? 'N/D'); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?></td>
                                                <td>
                                                    <?php
                                                    $statusLabels = [
                                                        'pending' => ['In Attesa', 'warning'],
                                                        'processing' => ['In Elaborazione', 'info'],
                                                        'completed' => ['Completata', 'success'],
                                                        'rejected' => ['Rifiutata', 'danger']
                                                    ];
                                                    $status = $statusLabels[$request['status']] ?? ['Sconosciuto', 'secondary'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $status[1]; ?>">
                                                        <?php echo $status[0]; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($request['completed_date']): ?>
                                                        <?php echo date('d/m/Y H:i', strtotime($request['completed_date'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($app->checkPermission('gdpr_compliance', 'export_personal_data')): ?>
                                                            <a href="personal_data_export_request_edit.php?id=<?php echo $request['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="confirmDelete(<?php echo $request['id']; ?>)" 
                                                                    title="Elimina">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php
                        $showInfo = true;
                        include __DIR__ . '/../src/Views/includes/pagination.php';
                        ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(requestId) {
            if (confirm('Sei sicuro di voler eliminare questa richiesta?')) {
                window.location.href = 'personal_data_export_request_edit.php?delete=' + requestId;
            }
        }
    </script>
</body>
</html>
