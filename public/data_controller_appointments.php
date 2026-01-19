<?php
/**
 * Gestione Nomine Responsabili Trattamento Dati - Lista
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

if (!$app->checkPermission('gdpr_compliance', 'view')) {
    die('Accesso negato');
}

AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

// Gestione filtri
$filters = [
    'appointment_type' => $_GET['appointment_type'] ?? '',
    'is_active' => $_GET['is_active'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Ottieni nomine con error handling
try {
    $appointments = $controller->indexAppointments($filters, $page, $perPage);
    $totalResults = $controller->countAppointments($filters);
    $totalPages = max(1, ceil($totalResults / $perPage));
} catch (Exception $e) {
    error_log("Errore caricamento nomine: " . $e->getMessage());
    $appointments = [];
    $totalResults = 0;
    $totalPages = 1;
    $error_message = "Errore nel caricamento dei dati. Verificare la connessione al database.";
}

// Log search if performed
if (!empty($filters['search'])) {
    AutoLogger::logSearch('gdpr_appointments', $filters['search'], $filters);
}

$pageTitle = 'Nomine Responsabili Trattamento';
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
                        <?php if ($app->checkPermission('gdpr_compliance', 'manage_appointments')): ?>
                        <a href="data_controller_appointment_edit.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Nuova Nomina
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        if ($_GET['success'] === 'deleted') {
                            echo 'Nomina eliminata con successo';
                        } else {
                            echo 'Nomina salvata con successo';
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php
                        if ($_GET['error'] === 'not_found') {
                            echo 'Nomina non trovata';
                        } else {
                            echo 'Si è verificato un errore';
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filtri -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="appointment_type" class="form-label">Tipo Nomina</label>
                                <select class="form-select" id="appointment_type" name="appointment_type">
                                    <option value="">Tutti</option>
                                    <option value="data_controller" <?php echo $filters['appointment_type'] === 'data_controller' ? 'selected' : ''; ?>>Titolare</option>
                                    <option value="data_processor" <?php echo $filters['appointment_type'] === 'data_processor' ? 'selected' : ''; ?>>Responsabile</option>
                                    <option value="dpo" <?php echo $filters['appointment_type'] === 'dpo' ? 'selected' : ''; ?>>DPO</option>
                                    <option value="authorized_person" <?php echo $filters['appointment_type'] === 'authorized_person' ? 'selected' : ''; ?>>Persona Autorizzata</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="is_active" class="form-label">Stato</label>
                                <select class="form-select" id="is_active" name="is_active">
                                    <option value="">Tutte</option>
                                    <option value="1" <?php echo $filters['is_active'] === '1' ? 'selected' : ''; ?>>Attive</option>
                                    <option value="0" <?php echo $filters['is_active'] === '0' ? 'selected' : ''; ?>>Non Attive</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Nome utente..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtra
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Risultati -->
                <div class="card">
                    <div class="card-header">
                        <strong>Trovate <?php echo $totalResults; ?> nomine</strong>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($appointments)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Nessuna nomina trovata</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Utente</th>
                                            <th>Tipo Nomina</th>
                                            <th>Data Nomina</th>
                                            <th>Data Revoca</th>
                                            <th>Formazione</th>
                                            <th>Stato</th>
                                            <th width="180">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($appointment['user_full_name'] ?? $appointment['username']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($appointment['username']); ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $typeLabels = [
                                                        'data_controller' => '<span class="badge bg-primary">Titolare</span>',
                                                        'data_processor' => '<span class="badge bg-info">Responsabile</span>',
                                                        'dpo' => '<span class="badge bg-success">DPO</span>',
                                                        'authorized_person' => '<span class="badge bg-secondary">Persona Autorizzata</span>'
                                                    ];
                                                    echo $typeLabels[$appointment['appointment_type']] ?? $appointment['appointment_type'];
                                                    ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($appointment['revocation_date']) {
                                                        echo '<span class="text-danger">' . date('d/m/Y', strtotime($appointment['revocation_date'])) . '</span>';
                                                    } else {
                                                        echo '<span class="text-muted">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($appointment['training_completed']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle"></i> Completata
                                                        </span>
                                                        <?php if ($appointment['training_date']): ?>
                                                            <br><small class="text-muted"><?php echo date('d/m/Y', strtotime($appointment['training_date'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-exclamation-triangle"></i> Non completata
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($appointment['is_active']): ?>
                                                        <span class="badge bg-success">Attiva</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non Attiva</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php if ($app->checkPermission('gdpr_compliance', 'manage_appointments')): ?>
                                                            <a href="data_controller_appointment_edit.php?id=<?php echo $appointment['id']; ?>" 
                                                               class="btn btn-outline-primary" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($app->checkPermission('gdpr_compliance', 'print_appointment')): ?>
                                                            <a href="data_controller_appointment_print.php?id=<?php echo $appointment['id']; ?>" 
                                                               class="btn btn-outline-success" title="Stampa PDF" target="_blank">
                                                                <i class="bi bi-printer"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Paginazione -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Precedente</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Successiva</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
