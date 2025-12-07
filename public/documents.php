<?php
/**
 * Gestione Documenti - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\DocumentController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('documents', 'view')) {

// Log page access
AutoLogger::logPageAccess();
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new DocumentController($db, $config);

$filters = [
    'category' => $_GET['category'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$documents = $controller->index($filters, $page, $perPage);
$stats = $controller->getStats();
$categories = $controller->getCategories();

// Formatta dimensione totale
$totalSizeMB = isset($stats['total_size']) ? round($stats['total_size'] / 1024 / 1024, 2) : 0;

$pageTitle = 'Archivio Documenti';
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
                        <?php if ($app->checkPermission('documents', 'create')): ?>
                            <a href="document_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Carica Documento
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Totale Documenti</h5>
                                <h2><?php echo number_format($stats['total'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Categorie</h5>
                                <h2><?php echo number_format($stats['categories'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Spazio Occupato</h5>
                                <h2><?php echo $totalSizeMB; ?> MB</h2>
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
                                       placeholder="Titolo, descrizione, tag...">
                            </div>
                            <div class="col-md-5">
                                <label for="category" class="form-label">Categoria</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Tutte le categorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                                <?php echo $filters['category'] === $cat['category'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                                        </option>
                                    <?php endforeach; ?>
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
                
                <!-- Tabella Documenti -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Documenti</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Titolo</th>
                                        <th>Categoria</th>
                                        <th>Tipo File</th>
                                        <th>Dimensione</th>
                                        <th>Caricato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Nessun documento trovato</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-file-earmark-text text-primary"></i>
                                                    <strong><?php echo htmlspecialchars($doc['title']); ?></strong>
                                                    <?php if ($doc['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($doc['description'], 0, 100)); ?><?php echo strlen($doc['description']) > 100 ? '...' : ''; ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($doc['tags']): ?>
                                                        <br>
                                                        <?php 
                                                        $tags = explode(',', $doc['tags']);
                                                        foreach ($tags as $tag): 
                                                            $tag = trim($tag);
                                                            if ($tag):
                                                        ?>
                                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($tag); ?></span>
                                                        <?php 
                                                            endif;
                                                        endforeach; 
                                                        ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($doc['category']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(strtoupper(pathinfo($doc['file_name'], PATHINFO_EXTENSION))); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($doc['file_size']) {
                                                        $size = $doc['file_size'];
                                                        if ($size < 1024) {
                                                            echo $size . ' B';
                                                        } elseif ($size < 1048576) {
                                                            echo round($size / 1024, 2) . ' KB';
                                                        } else {
                                                            echo round($size / 1048576, 2) . ' MB';
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($doc['uploaded_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="document_view.php?id=<?php echo $doc['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="document_download.php?id=<?php echo $doc['id']; ?>" 
                                                           class="btn btn-outline-success" title="Download">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('documents', 'edit')): ?>
                                                            <a href="document_edit.php?id=<?php echo $doc['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($app->checkPermission('documents', 'delete')): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteDocument(<?php echo $doc['id']; ?>)" title="Elimina">
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
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteDocument(id) {
            if (confirm('Sei sicuro di voler eliminare questo documento? Il file verrà eliminato definitivamente.')) {
                window.location.href = 'document_delete.php?id=' + id;
            }
        }
    </script>
</body>
</html>
