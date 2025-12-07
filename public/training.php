<?php
/**
 * Gestione Formazione - Lista Corsi
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\TrainingController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('training', 'view')) {

// Log page access
AutoLogger::logPageAccess();
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new TrainingController($db, $config);

$filters = [
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$courses = $controller->index($filters, $page, $perPage);
$stats = $controller->getStats();

$pageTitle = 'Gestione Formazione';
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
                        <?php if ($app->checkPermission('training', 'create')): ?>
                            <a href="training_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Corso
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Totale Corsi</h5>
                                <h2><?php echo number_format($stats['total'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Pianificati</h5>
                                <h2><?php echo number_format($stats['pianificati'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">In Corso</h5>
                                <h2><?php echo number_format($stats['in_corso'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Completati</h5>
                                <h2><?php echo number_format($stats['completati'] ?? 0); ?></h2>
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
                                       placeholder="Nome corso, istruttore...">
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Tipo Corso</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tutti</option>
                                    <option value="BLSD" <?php echo $filters['type'] === 'BLSD' ? 'selected' : ''; ?>>BLSD</option>
                                    <option value="AIB" <?php echo $filters['type'] === 'AIB' ? 'selected' : ''; ?>>AIB</option>
                                    <option value="Radio" <?php echo $filters['type'] === 'Radio' ? 'selected' : ''; ?>>Radio</option>
                                    <option value="Primo Soccorso" <?php echo $filters['type'] === 'Primo Soccorso' ? 'selected' : ''; ?>>Primo Soccorso</option>
                                    <option value="DLgs 81/08" <?php echo $filters['type'] === 'DLgs 81/08' ? 'selected' : ''; ?>>D.Lgs 81/08</option>
                                    <option value="Base PC" <?php echo $filters['type'] === 'Base PC' ? 'selected' : ''; ?>>Base Protezione Civile</option>
                                    <option value="Altro" <?php echo $filters['type'] === 'Altro' ? 'selected' : ''; ?>>Altro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="pianificato" <?php echo $filters['status'] === 'pianificato' ? 'selected' : ''; ?>>Pianificato</option>
                                    <option value="in_corso" <?php echo $filters['status'] === 'in_corso' ? 'selected' : ''; ?>>In Corso</option>
                                    <option value="completato" <?php echo $filters['status'] === 'completato' ? 'selected' : ''; ?>>Completato</option>
                                    <option value="annullato" <?php echo $filters['status'] === 'annullato' ? 'selected' : ''; ?>>Annullato</option>
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
                
                <!-- Tabella Corsi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Corsi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome Corso</th>
                                        <th>Tipo</th>
                                        <th>Data Inizio</th>
                                        <th>Data Fine</th>
                                        <th>Istruttore</th>
                                        <th>Partecipanti</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($courses)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Nessun corso trovato</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($course['course_type'] ?? '-'); ?></td>
                                                <td><?php echo $course['start_date'] ? date('d/m/Y', strtotime($course['start_date'])) : '-'; ?></td>
                                                <td><?php echo $course['end_date'] ? date('d/m/Y', strtotime($course['end_date'])) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($course['instructor'] ?? '-'); ?></td>
                                                <td>
                                                    <?php 
                                                    echo $course['participant_count'] ?? 0;
                                                    if ($course['max_participants']) {
                                                        echo ' / ' . $course['max_participants'];
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'pianificato' => 'info',
                                                        'in_corso' => 'warning',
                                                        'completato' => 'success',
                                                        'annullato' => 'danger'
                                                    ];
                                                    $class = $statusClass[$course['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($course['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="training_view.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('training', 'edit')): ?>
                                                            <a href="training_edit.php?id=<?php echo $course['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($app->checkPermission('training', 'delete')): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteCourse(<?php echo $course['id']; ?>)" title="Elimina">
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
        function deleteCourse(id) {
            if (confirm('Sei sicuro di voler eliminare questo corso?')) {
                window.location.href = 'training_delete.php?id=' + id;
            }
        }
    </script>
</body>
</html>
