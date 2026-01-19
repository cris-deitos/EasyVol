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
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

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
                        <?php if ($app->checkPermission('training', 'export')): ?>
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Esporta
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="data_export.php?entity=training&format=excel">
                                    <i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)
                                </a></li>
                                <li><a class="dropdown-item" href="data_export.php?entity=training&format=csv">
                                    <i class="bi bi-file-earmark-text"></i> CSV
                                </a></li>
                            </ul>
                        </div>
                        <?php endif; ?>
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
                                    <optgroup label="Corsi Base">
                                        <option value="A0" <?php echo $filters['type'] === 'A0' ? 'selected' : ''; ?>>A0 - Corso informativo</option>
                                        <option value="A1" <?php echo $filters['type'] === 'A1' ? 'selected' : ''; ?>>A1 - Corso base</option>
                                    </optgroup>
                                    <optgroup label="Corsi A2 - Specializzazione">
                                        <option value="A2-01" <?php echo $filters['type'] === 'A2-01' ? 'selected' : ''; ?>>A2-01 - Logistico gestionali</option>
                                        <option value="A2-02" <?php echo $filters['type'] === 'A2-02' ? 'selected' : ''; ?>>A2-02 - Segreteria</option>
                                        <option value="A2-03" <?php echo $filters['type'] === 'A2-03' ? 'selected' : ''; ?>>A2-03 - Cucina emergenza</option>
                                        <option value="A2-04" <?php echo $filters['type'] === 'A2-04' ? 'selected' : ''; ?>>A2-04 - Radiocomunicazioni</option>
                                        <option value="A2-05" <?php echo $filters['type'] === 'A2-05' ? 'selected' : ''; ?>>A2-05 - Alluvione</option>
                                        <option value="A2-06" <?php echo $filters['type'] === 'A2-06' ? 'selected' : ''; ?>>A2-06 - Frane</option>
                                        <option value="A2-07" <?php echo $filters['type'] === 'A2-07' ? 'selected' : ''; ?>>A2-07 - Alto pompaggio</option>
                                        <option value="A2-08" <?php echo $filters['type'] === 'A2-08' ? 'selected' : ''; ?>>A2-08 - Motosega</option>
                                        <option value="A2-09" <?php echo $filters['type'] === 'A2-09' ? 'selected' : ''; ?>>A2-09 - Sicurezza D.Lgs 81/08</option>
                                        <option value="A2-10" <?php echo $filters['type'] === 'A2-10' ? 'selected' : ''; ?>>A2-10 - Topografia GPS</option>
                                        <option value="A2-11" <?php echo $filters['type'] === 'A2-11' ? 'selected' : ''; ?>>A2-11 - Ricerca dispersi</option>
                                        <option value="A2-12" <?php echo $filters['type'] === 'A2-12' ? 'selected' : ''; ?>>A2-12 - Natante emergenza</option>
                                        <option value="A2-13" <?php echo $filters['type'] === 'A2-13' ? 'selected' : ''; ?>>A2-13 - Interventi zootecnici</option>
                                        <option value="A2-14" <?php echo $filters['type'] === 'A2-14' ? 'selected' : ''; ?>>A2-14 - Piano PC</option>
                                        <option value="A2-15" <?php echo $filters['type'] === 'A2-15' ? 'selected' : ''; ?>>A2-15 - Quaderni presidio</option>
                                        <option value="A2-16" <?php echo $filters['type'] === 'A2-16' ? 'selected' : ''; ?>>A2-16 - Eventi rilevanti</option>
                                        <option value="A2-17" <?php echo $filters['type'] === 'A2-17' ? 'selected' : ''; ?>>A2-17 - Scuola I° ciclo</option>
                                        <option value="A2-18" <?php echo $filters['type'] === 'A2-18' ? 'selected' : ''; ?>>A2-18 - Scuola secondaria</option>
                                    </optgroup>
                                    <optgroup label="Corsi A3 - Coordinamento">
                                        <option value="A3-01" <?php echo $filters['type'] === 'A3-01' ? 'selected' : ''; ?>>A3-01 - Capo squadra</option>
                                        <option value="A3-02" <?php echo $filters['type'] === 'A3-02' ? 'selected' : ''; ?>>A3-02 - Coordinatore territoriale</option>
                                        <option value="A3-03" <?php echo $filters['type'] === 'A3-03' ? 'selected' : ''; ?>>A3-03 - Vice coordinatore</option>
                                        <option value="A3-04" <?php echo $filters['type'] === 'A3-04' ? 'selected' : ''; ?>>A3-04 - Presidente</option>
                                        <option value="A3-05" <?php echo $filters['type'] === 'A3-05' ? 'selected' : ''; ?>>A3-05 - CCV</option>
                                        <option value="A3-06" <?php echo $filters['type'] === 'A3-06' ? 'selected' : ''; ?>>A3-06 - Pianificazione</option>
                                    </optgroup>
                                    <optgroup label="Corsi A4 - Alta Specializzazione">
                                        <option value="A4-01" <?php echo $filters['type'] === 'A4-01' ? 'selected' : ''; ?>>A4-01 - Sommozzatori 1°liv</option>
                                        <option value="A4-02" <?php echo $filters['type'] === 'A4-02' ? 'selected' : ''; ?>>A4-02 - Sommozzatori avanz</option>
                                        <option value="A4-03" <?php echo $filters['type'] === 'A4-03' ? 'selected' : ''; ?>>A4-03 - Cinofili</option>
                                        <option value="A4-04" <?php echo $filters['type'] === 'A4-04' ? 'selected' : ''; ?>>A4-04 - Equestri</option>
                                        <option value="A4-05" <?php echo $filters['type'] === 'A4-05' ? 'selected' : ''; ?>>A4-05 - Imenotteri</option>
                                        <option value="A4-06" <?php echo $filters['type'] === 'A4-06' ? 'selected' : ''; ?>>A4-06 - TSA</option>
                                        <option value="A4-07" <?php echo $filters['type'] === 'A4-07' ? 'selected' : ''; ?>>A4-07 - SRT</option>
                                        <option value="A4-08" <?php echo $filters['type'] === 'A4-08' ? 'selected' : ''; ?>>A4-08 - Radio amatoriale</option>
                                        <option value="A4-09" <?php echo $filters['type'] === 'A4-09' ? 'selected' : ''; ?>>A4-09 - Gru</option>
                                        <option value="A4-10" <?php echo $filters['type'] === 'A4-10' ? 'selected' : ''; ?>>A4-10 - Muletto</option>
                                        <option value="A4-11" <?php echo $filters['type'] === 'A4-11' ? 'selected' : ''; ?>>A4-11 - PLE</option>
                                        <option value="A4-12" <?php echo $filters['type'] === 'A4-12' ? 'selected' : ''; ?>>A4-12 - Escavatore</option>
                                        <option value="A4-13" <?php echo $filters['type'] === 'A4-13' ? 'selected' : ''; ?>>A4-13 - Trattore</option>
                                        <option value="A4-14" <?php echo $filters['type'] === 'A4-14' ? 'selected' : ''; ?>>A4-14 - Droni</option>
                                        <option value="A4-15" <?php echo $filters['type'] === 'A4-15' ? 'selected' : ''; ?>>A4-15 - HACCP</option>
                                    </optgroup>
                                    <optgroup label="Corsi A5 - AIB">
                                        <option value="A5-01" <?php echo $filters['type'] === 'A5-01' ? 'selected' : ''; ?>>A5-01 - AIB 1° livello</option>
                                        <option value="A5-02" <?php echo $filters['type'] === 'A5-02' ? 'selected' : ''; ?>>A5-02 - AIB aggiornamenti</option>
                                        <option value="A5-03" <?php echo $filters['type'] === 'A5-03' ? 'selected' : ''; ?>>A5-03 - Caposquadra AIB</option>
                                        <option value="A5-04" <?php echo $filters['type'] === 'A5-04' ? 'selected' : ''; ?>>A5-04 - DOS</option>
                                    </optgroup>
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
                                        <th>Cod. SSPC</th>
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
                                            <td colspan="9" class="text-center">Nessun corso trovato</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($course['course_type'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if (!empty($course['sspc_course_code']) || !empty($course['sspc_edition_code'])): ?>
                                                        <small>
                                                            <?php if (!empty($course['sspc_course_code'])): ?>
                                                                <strong>C:</strong> <?php echo htmlspecialchars($course['sspc_course_code']); ?><br>
                                                            <?php endif; ?>
                                                            <?php if (!empty($course['sspc_edition_code'])): ?>
                                                                <strong>E:</strong> <?php echo htmlspecialchars($course['sspc_edition_code']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
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
