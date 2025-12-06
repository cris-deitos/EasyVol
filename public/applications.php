<?php
/**
 * Gestione Domande di Iscrizione
 * 
 * Pagina per visualizzare e gestire le domande di iscrizione
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\ApplicationController;

$app = new App();

// Verifica autenticazione
if (!$app->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->hasPermission('applications', 'view')) {
    die('Accesso negato');
}

$db = $app->getDatabase();
$config = $app->getConfig();
$controller = new ApplicationController($db, $config);

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $applicationId = intval($_POST['application_id'] ?? 0);
    
    if ($_POST['action'] === 'approve' && $app->hasPermission('applications', 'edit')) {
        $controller->approve($applicationId, $app->getUserId());
        header('Location: applications.php?success=approved');
        exit;
    } elseif ($_POST['action'] === 'reject' && $app->hasPermission('applications', 'edit')) {
        $reason = $_POST['rejection_reason'] ?? '';
        $controller->reject($applicationId, $app->getUserId(), $reason);
        header('Location: applications.php?success=rejected');
        exit;
    }
}

// Gestione filtri
$filters = [
    'status' => $_GET['status'] ?? 'pending',
    'type' => $_GET['type'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Ottieni domande
$applications = $controller->getAll($filters, $page, $perPage);

// Conteggi per status
$statusCounts = [
    'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'pending'")['count'] ?? 0,
    'approved' => $db->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'approved'")['count'] ?? 0,
    'rejected' => $db->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'rejected'")['count'] ?? 0,
];

$pageTitle = 'Gestione Domande di Iscrizione';
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
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php if ($_GET['success'] === 'approved'): ?>
                            Domanda approvata con successo!
                        <?php elseif ($_GET['success'] === 'rejected'): ?>
                            Domanda rifiutata.
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">In Attesa</h5>
                                <h2><?php echo number_format($statusCounts['pending']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Approvate</h5>
                                <h2><?php echo number_format($statusCounts['approved']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Rifiutate</h5>
                                <h2><?php echo number_format($statusCounts['rejected']); ?></h2>
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
                                       placeholder="Nome, cognome, codice...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>In Attesa</option>
                                    <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approvate</option>
                                    <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rifiutate</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tutti</option>
                                    <option value="adult" <?php echo $filters['type'] === 'adult' ? 'selected' : ''; ?>>Maggiorenni</option>
                                    <option value="junior" <?php echo $filters['type'] === 'junior' ? 'selected' : ''; ?>>Minorenni</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Domande -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Domande</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Codice</th>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Cognome</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($applications)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nessuna domanda trovata
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($applications as $application): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($application['application_code']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($application['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($application['application_type'] === 'junior'): ?>
                                                        <span class="badge bg-info">Minorenne</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Maggiorenne</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($application['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($application['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($application['email']); ?></td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'pending' => 'warning',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    $statusLabels = [
                                                        'pending' => 'In Attesa',
                                                        'approved' => 'Approvata',
                                                        'rejected' => 'Rifiutata'
                                                    ];
                                                    $color = $statusColors[$application['status']] ?? 'secondary';
                                                    $label = $statusLabels[$application['status']] ?? $application['status'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo $label; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewModal<?php echo $application['id']; ?>"
                                                                title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($application['status'] === 'pending' && $app->hasPermission('applications', 'edit')): ?>
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    onclick="approveApplication(<?php echo $application['id']; ?>)"
                                                                    title="Approva">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#rejectModal<?php echo $application['id']; ?>"
                                                                    title="Rifiuta">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (!empty($application['pdf_path'])): ?>
                                                            <a href="<?php echo htmlspecialchars($application['pdf_path']); ?>" 
                                                               class="btn btn-sm btn-secondary" 
                                                               target="_blank"
                                                               title="PDF">
                                                                <i class="bi bi-file-pdf"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Modal Visualizza -->
                                            <div class="modal fade" id="viewModal<?php echo $application['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Dettaglio Domanda</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <dl class="row">
                                                                <dt class="col-sm-4">Codice</dt>
                                                                <dd class="col-sm-8"><?php echo htmlspecialchars($application['application_code']); ?></dd>
                                                                
                                                                <dt class="col-sm-4">Cognome</dt>
                                                                <dd class="col-sm-8"><?php echo htmlspecialchars($application['last_name']); ?></dd>
                                                                
                                                                <dt class="col-sm-4">Nome</dt>
                                                                <dd class="col-sm-8"><?php echo htmlspecialchars($application['first_name']); ?></dd>
                                                                
                                                                <dt class="col-sm-4">Codice Fiscale</dt>
                                                                <dd class="col-sm-8"><?php echo htmlspecialchars($application['tax_code'] ?? 'N/D'); ?></dd>
                                                                
                                                                <dt class="col-sm-4">Data di Nascita</dt>
                                                                <dd class="col-sm-8"><?php echo date('d/m/Y', strtotime($application['birth_date'])); ?></dd>
                                                                
                                                                <dt class="col-sm-4">Email</dt>
                                                                <dd class="col-sm-8"><?php echo htmlspecialchars($application['email']); ?></dd>
                                                                
                                                                <dt class="col-sm-4">Telefono</dt>
                                                                <dd class="col-sm-8"><?php echo htmlspecialchars($application['phone'] ?? 'N/D'); ?></dd>
                                                            </dl>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Modal Rifiuta -->
                                            <div class="modal fade" id="rejectModal<?php echo $application['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Rifiuta Domanda</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label for="rejection_reason" class="form-label">Motivazione (opzionale)</label>
                                                                    <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                <button type="submit" class="btn btn-danger">Rifiuta</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
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
        function approveApplication(id) {
            if (confirm('Sei sicuro di voler approvare questa domanda? Verrà creato un nuovo socio.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'approve';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'application_id';
                idInput.value = id;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
