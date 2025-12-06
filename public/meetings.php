<?php
/**
 * Gestione Riunioni - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\MeetingController;

$app = new App();

if (!$app->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!$app->hasPermission('meetings', 'view')) {
    die('Accesso negato');
}

$db = $app->getDatabase();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

$filters = [
    'type' => $_GET['type'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$meetings = $controller->index($filters, $page, $perPage);

$totalMeetings = $db->fetchOne("SELECT COUNT(*) as count FROM meetings")['count'] ?? 0;

$pageTitle = 'Gestione Riunioni e Assemblee';
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
                        <?php if ($app->hasPermission('meetings', 'create')): ?>
                            <a href="meeting_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Riunione
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Totale Riunioni</h5>
                                <h2><?php echo number_format($totalMeetings); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Titolo, luogo...">
                            </div>
                            <div class="col-md-5">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tutti</option>
                                    <option value="assemblea_ordinaria" <?php echo $filters['type'] === 'assemblea_ordinaria' ? 'selected' : ''; ?>>Assemblea Ordinaria</option>
                                    <option value="assemblea_straordinaria" <?php echo $filters['type'] === 'assemblea_straordinaria' ? 'selected' : ''; ?>>Assemblea Straordinaria</option>
                                    <option value="consiglio_direttivo" <?php echo $filters['type'] === 'consiglio_direttivo' ? 'selected' : ''; ?>>Consiglio Direttivo</option>
                                    <option value="riunione_operativa" <?php echo $filters['type'] === 'riunione_operativa' ? 'selected' : ''; ?>>Riunione Operativa</option>
                                </select>
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
                
                <!-- Tabella Riunioni -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Riunioni</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Titolo</th>
                                        <th>Data</th>
                                        <th>Luogo</th>
                                        <th>Convocatore</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($meetings)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                Nessuna riunione trovata
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($meetings as $meeting): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-people"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $meeting['meeting_type'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($meeting['title']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($meeting['meeting_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($meeting['location'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($meeting['convocator'] ?? '-'); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="meeting_view.php?id=<?php echo $meeting['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->hasPermission('meetings', 'edit')): ?>
                                                            <a href="meeting_edit.php?id=<?php echo $meeting['id']; ?>" 
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
