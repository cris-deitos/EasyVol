<?php
/**
 * Newsletter Management - List
 * 
 * Page to view and manage newsletters
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\NewsletterController;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Check authentication
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permissions
if (!$app->checkPermission('newsletters', 'view')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new NewsletterController($db, $config);

// Handle filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'created_by' => $_GET['created_by'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Get newsletters
$newsletters = $controller->index($filters, $page, $perPage);
$totalResults = $controller->count($filters);
$totalPages = max(1, ceil($totalResults / $perPage));

// Log page access
AutoLogger::logPageAccess();
if (!empty($filters['search'])) {
    AutoLogger::logSearch('newsletters', $filters['search'], $filters);
}

$pageTitle = 'Gestione Newsletter';
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
                        <?php if ($app->checkPermission('newsletters', 'create')): ?>
                            <a href="newsletter_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Newsletter
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Oggetto...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                                    <option value="scheduled" <?php echo $filters['status'] === 'scheduled' ? 'selected' : ''; ?>>Programmata</option>
                                    <option value="sent" <?php echo $filters['status'] === 'sent' ? 'selected' : ''; ?>>Inviata</option>
                                    <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>Fallita</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Data Da</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Data A</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <a href="newsletters.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Newsletter Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Storico Newsletter</h5>
                        <span class="badge bg-secondary"><?php echo $totalResults; ?> risultati</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Oggetto</th>
                                        <th>Stato</th>
                                        <th>Data Creazione</th>
                                        <th>Data/Ora Invio</th>
                                        <th>Destinatari</th>
                                        <th>Esito</th>
                                        <th>Creata da</th>
                                        <th>Inviata da</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($newsletters)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">
                                                Nessuna newsletter trovata
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($newsletters as $newsletter): ?>
                                            <tr>
                                                <td><?php echo $newsletter['id']; ?></td>
                                                <td>
                                                    <a href="newsletter_view.php?id=<?php echo $newsletter['id']; ?>">
                                                        <?php echo htmlspecialchars(substr($newsletter['subject'], 0, 50)); ?>
                                                        <?php if (strlen($newsletter['subject']) > 50) echo '...'; ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'draft' => 'secondary',
                                                        'scheduled' => 'warning',
                                                        'sent' => 'success',
                                                        'failed' => 'danger'
                                                    ];
                                                    $statusLabels = [
                                                        'draft' => 'Bozza',
                                                        'scheduled' => 'Programmata',
                                                        'sent' => 'Inviata',
                                                        'failed' => 'Fallita'
                                                    ];
                                                    $status = $newsletter['status'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusColors[$status] ?? 'secondary'; ?>">
                                                        <?php echo $statusLabels[$status] ?? $status; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($newsletter['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($newsletter['scheduled_at']): ?>
                                                        <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($newsletter['scheduled_at'])); ?>
                                                    <?php elseif ($newsletter['sent_at']): ?>
                                                        <?php echo date('d/m/Y H:i', strtotime($newsletter['sent_at'])); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($newsletter['total_recipients'] > 0): ?>
                                                        <?php echo $newsletter['total_recipients']; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($newsletter['sent_count'] > 0 || $newsletter['failed_count'] > 0): ?>
                                                        <span class="text-success"><?php echo $newsletter['sent_count']; ?></span> /
                                                        <span class="text-danger"><?php echo $newsletter['failed_count']; ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($newsletter['created_by_name'] ?? 'N/D'); ?></td>
                                                <td><?php echo htmlspecialchars($newsletter['sent_by_name'] ?? '-'); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="newsletter_view.php?id=<?php echo $newsletter['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($newsletter['status'] === 'draft' && $app->checkPermission('newsletters', 'edit')): ?>
                                                            <a href="newsletter_edit.php?id=<?php echo $newsletter['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($app->checkPermission('newsletters', 'create')): ?>
                                                            <a href="newsletter_edit.php?clone=<?php echo $newsletter['id']; ?>" 
                                                               class="btn btn-outline-info" title="Clona">
                                                                <i class="bi bi-files"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($newsletter['status'] === 'draft' && $app->checkPermission('newsletters', 'delete')): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteNewsletter(<?php echo $newsletter['id']; ?>)" 
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
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : ''; ?>">
                                                Precedente
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : ''; ?>">
                                                Successiva
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteNewsletter(id) {
        if (!confirm('Sei sicuro di voler eliminare questa newsletter?')) {
            return;
        }
        
        fetch('newsletter_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Errore: ' + data.message);
            }
        })
        .catch(error => {
            alert('Errore durante l\'eliminazione');
            console.error(error);
        });
    }
    </script>
</body>
</html>
