<?php
/**
 * Gestione Formazione - Visualizzazione Dettaglio Corso
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\TrainingController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('training', 'view')) {
    die('Accesso negato');
}

$courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($courseId <= 0) {
    header('Location: training.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new TrainingController($db, $config);

$course = $controller->get($courseId);

if (!$course) {
    header('Location: training.php?error=not_found');
    exit;
}

$pageTitle = 'Dettaglio Corso: ' . $course['course_name'];
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
                    <h1 class="h2">
                        <a href="training.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($course['course_name']); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('training', 'edit')): ?>
                                <a href="training_edit.php?id=<?php echo $course['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="bi bi-printer"></i> Stampa
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="courseTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Informazioni Corso
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button">
                            <i class="bi bi-people"></i> Partecipanti (<?php echo count($course['participants']); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button">
                            <i class="bi bi-calendar-check"></i> Presenze
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="courseTabContent">
                    <!-- Tab Informazioni -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Dati Generali</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Nome Corso:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['course_name']); ?></dd>
                                            
                                            <dt class="col-sm-4">Tipo:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['course_type'] ?? '-'); ?></dd>
                                            
                                            <dt class="col-sm-4">Stato:</dt>
                                            <dd class="col-sm-8">
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
                                            </dd>
                                            
                                            <dt class="col-sm-4">Data Inizio:</dt>
                                            <dd class="col-sm-8"><?php echo $course['start_date'] ? date('d/m/Y', strtotime($course['start_date'])) : '-'; ?></dd>
                                            
                                            <dt class="col-sm-4">Data Fine:</dt>
                                            <dd class="col-sm-8"><?php echo $course['end_date'] ? date('d/m/Y', strtotime($course['end_date'])) : '-'; ?></dd>
                                            
                                            <dt class="col-sm-4">Istruttore:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['instructor'] ?? '-'); ?></dd>
                                            
                                            <dt class="col-sm-4">Luogo:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($course['location'] ?? '-'); ?></dd>
                                            
                                            <dt class="col-sm-4">Max Partecipanti:</dt>
                                            <dd class="col-sm-8"><?php echo $course['max_participants'] ? htmlspecialchars($course['max_participants']) : 'Illimitato'; ?></dd>
                                        </dl>
                                    </div>
                                </div>
                                
                                <?php if ($course['description']): ?>
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Descrizione</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Statistiche</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-primary"><?php echo $course['stats']['total_partecipanti'] ?? 0; ?></h3>
                                                    <small class="text-muted">Iscritti</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-success"><?php echo $course['stats']['presenti'] ?? 0; ?></h3>
                                                    <small class="text-muted">Presenti</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-danger"><?php echo $course['stats']['assenti'] ?? 0; ?></h3>
                                                    <small class="text-muted">Assenti</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-3">
                                                    <h3 class="text-info"><?php echo $course['stats']['certificati_rilasciati'] ?? 0; ?></h3>
                                                    <small class="text-muted">Certificati</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Partecipanti -->
                    <div class="tab-pane fade" id="participants" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Elenco Partecipanti</h5>
                                <?php if ($app->checkPermission('training', 'edit')): ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                                        <i class="bi bi-plus-circle"></i> Aggiungi Partecipante
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Matricola</th>
                                                <th>Nome</th>
                                                <th>Data Iscrizione</th>
                                                <th>Stato</th>
                                                <th>Voto</th>
                                                <th>Certificato</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($course['participants'])): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Nessun partecipante registrato</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($course['participants'] as $participant): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($participant['registration_number']); ?></td>
                                                        <td>
                                                            <a href="member_view.php?id=<?php echo $participant['member_id']; ?>">
                                                                <?php echo htmlspecialchars($participant['last_name'] . ' ' . $participant['first_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo $participant['registration_date'] ? date('d/m/Y', strtotime($participant['registration_date'])) : '-'; ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = [
                                                                'iscritto' => 'info',
                                                                'presente' => 'success',
                                                                'assente' => 'danger',
                                                                'ritirato' => 'secondary'
                                                            ];
                                                            $class = $statusClass[$participant['attendance_status']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?php echo $class; ?>">
                                                                <?php echo ucfirst(htmlspecialchars($participant['attendance_status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($participant['final_grade'] ?? '-'); ?></td>
                                                        <td>
                                                            <?php if ($participant['certificate_issued']): ?>
                                                                <i class="bi bi-check-circle-fill text-success"></i>
                                                            <?php else: ?>
                                                                <i class="bi bi-x-circle text-muted"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($app->checkPermission('training', 'edit')): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                        onclick="editParticipant(<?php echo $participant['id']; ?>)">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Presenze -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Registro Presenze</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    La funzionalità di registro presenze permette di tracciare la partecipazione giornaliera dei corsisti.
                                </p>
                                <?php if ($app->checkPermission('training', 'edit')): ?>
                                    <a href="training_attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                        <i class="bi bi-calendar-check"></i> Gestisci Presenze
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
