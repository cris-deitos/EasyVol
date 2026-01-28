<?php
/**
 * Gestione Domande di Iscrizione
 * 
 * Pagina per visualizzare e gestire le domande di iscrizione
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ApplicationController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('applications', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ApplicationController($db, $config);

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $applicationId = intval($_POST['application_id'] ?? 0);
    
    if ($_POST['action'] === 'approve' && $app->checkPermission('applications', 'edit')) {
        if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
            header('Location: applications.php?error=invalid_token');
            exit;
        }
        $result = $controller->approve($applicationId, $app->getUserId());
        if ($result) {
            header('Location: applications.php?success=approved');
        } else {
            header('Location: applications.php?error=approve_failed');
        }
        exit;
    } elseif ($_POST['action'] === 'reject' && $app->checkPermission('applications', 'edit')) {
        if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
            header('Location: applications.php?error=invalid_token');
            exit;
        }
        $reason = $_POST['rejection_reason'] ?? '';
        $result = $controller->reject($applicationId, $app->getUserId(), $reason);
        if ($result) {
            header('Location: applications.php?success=rejected');
        } else {
            header('Location: applications.php?error=reject_failed');
        }
        exit;
    } elseif ($_POST['action'] === 'regenerate_pdf' && $app->checkPermission('applications', 'edit')) {
        // Handle PDF regeneration
        if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token di sicurezza non valido';
        } else {
            try {
                if ($applicationId <= 0) {
                    throw new \Exception("ID applicazione non valido");
                }
                
                require_once __DIR__ . '/../src/Utils/ApplicationPdfGenerator.php';
                $pdfGenerator = new \EasyVol\Utils\ApplicationPdfGenerator($db, $config);
                $pdfPath = $pdfGenerator->generateApplicationPdf($applicationId);
                
                $_SESSION['success'] = 'PDF rigenerato con successo';
                
                // Optionally resend email
                if (isset($_POST['resend_email']) && $_POST['resend_email'] === '1') {
                    $application = $db->fetchOne("SELECT * FROM member_applications WHERE id = ?", [$applicationId]);
                    
                    require_once __DIR__ . '/../src/Utils/EmailSender.php';
                    $emailSender = new \EasyVol\Utils\EmailSender($config, $db);
                    
                    if ($emailSender->sendApplicationEmail($application, $pdfPath)) {
                        $_SESSION['success'] .= ' ed email inviata';
                    } else {
                        $_SESSION['warning'] = 'PDF rigenerato ma invio email fallito';
                    }
                }
                
            } catch (\Exception $e) {
                $_SESSION['error'] = 'Errore: ' . $e->getMessage();
            }
        }
        
        header('Location: applications.php');
        exit;
    } elseif ($_POST['action'] === 'delete' && $app->checkPermission('applications', 'delete')) {
        // Handle application deletion
        if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token di sicurezza non valido';
        } else {
            $result = $controller->delete($applicationId, $app->getUserId());
            if ($result['success']) {
                $_SESSION['success'] = 'Domanda eliminata con successo';
            } else {
                $_SESSION['error'] = $result['message'] ?? 'Errore durante l\'eliminazione';
            }
        }
        
        header('Location: applications.php');
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
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['warning']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>
                
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
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php if ($_GET['error'] === 'reject_failed'): ?>
                            Errore durante il rifiuto della domanda. Riprova.
                        <?php elseif ($_GET['error'] === 'approve_failed'): ?>
                            Errore durante l'approvazione della domanda. Riprova.
                        <?php elseif ($_GET['error'] === 'invalid_token'): ?>
                            Token di sicurezza non valido. Riprova.
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
                                                <td><?php 
                                                    $dateStr = $application['submitted_at'] ?? $application['created_at'] ?? '';
                                                    $timestamp = strtotime($dateStr);
                                                    echo ($timestamp !== false) ? date('d/m/Y', $timestamp) : htmlspecialchars($dateStr);
                                                ?></td>
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
                                                        <?php if ($application['status'] === 'pending' && $app->checkPermission('applications', 'edit')): ?>
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
                                                        <?php if (!empty($application['pdf_file'])): ?>
                                                            <a href="download.php?type=application_pdf&id=<?php echo $application['id']; ?>" 
                                                               class="btn btn-sm btn-secondary" 
                                                               target="_blank"
                                                               title="PDF">
                                                                <i class="bi bi-file-pdf"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($application['status'] !== 'approved' && $app->checkPermission('applications', 'delete')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deleteModal<?php echo $application['id']; ?>"
                                                                    title="Elimina">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Modal Visualizza -->
                                            <div class="modal fade" id="viewModal<?php echo $application['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-xl">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Dettaglio Domanda - <?php echo htmlspecialchars($application['application_code']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                                            <?php
                                                            // Decode JSON data for full details
                                                            $appData = json_decode($application['application_data'], true);
                                                            if ($appData):
                                                            ?>
                                                            
                                                            <h6 class="bg-light p-2 border-start border-primary border-4">Dati Anagrafici</h6>
                                                            <dl class="row mb-3">
                                                                <dt class="col-sm-3">Cognome</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['last_name'] ?? ''); ?></dd>
                                                                <dt class="col-sm-3">Nome</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['first_name'] ?? ''); ?></dd>
                                                                
                                                                <dt class="col-sm-3">Codice Fiscale</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['tax_code'] ?? 'N/D'); ?></dd>
                                                                <dt class="col-sm-3">Data di Nascita</dt>
                                                                <dd class="col-sm-3"><?php echo !empty($appData['birth_date']) ? date('d/m/Y', strtotime($appData['birth_date'])) : 'N/D'; ?></dd>
                                                                
                                                                <dt class="col-sm-3">Luogo di Nascita</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['birth_place'] ?? 'N/D'); ?></dd>
                                                                <dt class="col-sm-3">Provincia</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['birth_province'] ?? 'N/D'); ?></dd>
                                                            </dl>
                                                            
                                                            <h6 class="bg-light p-2 border-start border-primary border-4">Residenza</h6>
                                                            <dl class="row mb-3">
                                                                <dt class="col-sm-3">Indirizzo</dt>
                                                                <dd class="col-sm-9">
                                                                    <?php 
                                                                    echo htmlspecialchars($appData['residence_street'] ?? '') . ' ' . 
                                                                         htmlspecialchars($appData['residence_number'] ?? '') . ', ' .
                                                                         htmlspecialchars($appData['residence_city'] ?? '') . ' (' .
                                                                         htmlspecialchars($appData['residence_province'] ?? '') . ') - ' .
                                                                         htmlspecialchars($appData['residence_cap'] ?? '');
                                                                    ?>
                                                                </dd>
                                                            </dl>
                                                            
                                                            <?php if (!empty($appData['domicile_street'])): ?>
                                                            <h6 class="bg-light p-2 border-start border-primary border-4">Domicilio</h6>
                                                            <dl class="row mb-3">
                                                                <dt class="col-sm-3">Indirizzo</dt>
                                                                <dd class="col-sm-9">
                                                                    <?php 
                                                                    echo htmlspecialchars($appData['domicile_street'] ?? '') . ' ' . 
                                                                         htmlspecialchars($appData['domicile_number'] ?? '') . ', ' .
                                                                         htmlspecialchars($appData['domicile_city'] ?? '') . ' (' .
                                                                         htmlspecialchars($appData['domicile_province'] ?? '') . ') - ' .
                                                                         htmlspecialchars($appData['domicile_cap'] ?? '');
                                                                    ?>
                                                                </dd>
                                                            </dl>
                                                            <?php endif; ?>
                                                            
                                                            <h6 class="bg-light p-2 border-start border-primary border-4">Recapiti</h6>
                                                            <dl class="row mb-3">
                                                                <?php if (!empty($appData['phone'])): ?>
                                                                <dt class="col-sm-3">Telefono</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['phone']); ?></dd>
                                                                <?php endif; ?>
                                                                <?php if (!empty($appData['mobile'])): ?>
                                                                <dt class="col-sm-3">Cellulare</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['mobile']); ?></dd>
                                                                <?php endif; ?>
                                                                <?php if (!empty($appData['email'])): ?>
                                                                <dt class="col-sm-3">Email</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['email']); ?></dd>
                                                                <?php endif; ?>
                                                                <?php if (!empty($appData['pec'])): ?>
                                                                <dt class="col-sm-3">PEC</dt>
                                                                <dd class="col-sm-3"><?php echo htmlspecialchars($appData['pec']); ?></dd>
                                                                <?php endif; ?>
                                                            </dl>
                                                            
                                                            <?php if ($application['application_type'] === 'adult'): ?>
                                                                
                                                                <?php if (!empty($appData['licenses'])): ?>
                                                                <h6 class="bg-light p-2 border-start border-primary border-4">Patenti e Abilitazioni</h6>
                                                                <ul class="mb-3">
                                                                    <?php foreach ($appData['licenses'] as $license): ?>
                                                                        <li>
                                                                            <strong><?php echo htmlspecialchars($license['type']); ?></strong>
                                                                            <?php if (!empty($license['description'])): ?>
                                                                                - <?php echo htmlspecialchars($license['description']); ?>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($license['number'])): ?>
                                                                                - N. <?php echo htmlspecialchars($license['number']); ?>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($license['expiry_date'])): ?>
                                                                                - Scad: <?php echo date('d/m/Y', strtotime($license['expiry_date'])); ?>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($appData['corso_base_pc'])): ?>
                                                                <h6 class="bg-light p-2 border-start border-primary border-4">Corso Base Protezione Civile</h6>
                                                                <dl class="row mb-3">
                                                                    <dt class="col-sm-6">Corso Base di Protezione Civile riconosciuto da Regione Lombardia</dt>
                                                                    <dd class="col-sm-6">
                                                                        ✓ Completato
                                                                        <?php if (!empty($appData['corso_base_pc_anno'])): ?>
                                                                         - Anno: <?php echo htmlspecialchars($appData['corso_base_pc_anno']); ?>
                                                                        <?php endif; ?>
                                                                    </dd>
                                                                </dl>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($appData['courses'])): ?>
                                                                <h6 class="bg-light p-2 border-start border-primary border-4">Corsi e Specializzazioni</h6>
                                                                <ul class="mb-3">
                                                                    <?php foreach ($appData['courses'] as $course): ?>
                                                                        <li>
                                                                            <strong><?php echo htmlspecialchars($course['name']); ?></strong>
                                                                            <?php if (!empty($course['completion_date'])): ?>
                                                                                - Completato: <?php echo date('d/m/Y', strtotime($course['completion_date'])); ?>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($course['expiry_date'])): ?>
                                                                                - Scad: <?php echo date('d/m/Y', strtotime($course['expiry_date'])); ?>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($appData['employer_name'])): ?>
                                                                <h6 class="bg-light p-2 border-start border-primary border-4">Datore di Lavoro</h6>
                                                                <dl class="row mb-3">
                                                                    <dt class="col-sm-3">Ragione Sociale</dt>
                                                                    <dd class="col-sm-9"><?php echo htmlspecialchars($appData['employer_name']); ?></dd>
                                                                    <?php if (!empty($appData['employer_address'])): ?>
                                                                    <dt class="col-sm-3">Indirizzo</dt>
                                                                    <dd class="col-sm-9"><?php echo htmlspecialchars($appData['employer_address']); ?></dd>
                                                                    <?php endif; ?>
                                                                </dl>
                                                                <?php endif; ?>
                                                                
                                                            <?php else: // Junior application ?>
                                                                
                                                                <?php if (!empty($appData['guardians'])): ?>
                                                                <h6 class="bg-light p-2 border-start border-primary border-4">Genitori/Tutori</h6>
                                                                <?php foreach ($appData['guardians'] as $guardian): ?>
                                                                    <div class="card mb-2">
                                                                        <div class="card-body">
                                                                            <h6 class="card-title"><?php echo strtoupper($guardian['type']); ?></h6>
                                                                            <dl class="row mb-0">
                                                                                <dt class="col-sm-3">Nome Cognome</dt>
                                                                                <dd class="col-sm-9"><?php echo htmlspecialchars($guardian['first_name'] . ' ' . $guardian['last_name']); ?></dd>
                                                                                <?php if (!empty($guardian['tax_code'])): ?>
                                                                                <dt class="col-sm-3">Codice Fiscale</dt>
                                                                                <dd class="col-sm-9"><?php echo htmlspecialchars($guardian['tax_code']); ?></dd>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($guardian['phone'])): ?>
                                                                                <dt class="col-sm-3">Telefono</dt>
                                                                                <dd class="col-sm-9"><?php echo htmlspecialchars($guardian['phone']); ?></dd>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($guardian['email'])): ?>
                                                                                <dt class="col-sm-3">Email</dt>
                                                                                <dd class="col-sm-9"><?php echo htmlspecialchars($guardian['email']); ?></dd>
                                                                                <?php endif; ?>
                                                                            </dl>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <?php endif; ?>
                                                                
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($appData['health_allergies']) || !empty($appData['health_intolerances']) || !empty($appData['health_vegetarian']) || !empty($appData['health_vegan'])): ?>
                                                            <h6 class="bg-light p-2 border-start border-primary border-4">Informazioni Alimentari</h6>
                                                            <dl class="row mb-3">
                                                                <?php if (!empty($appData['health_vegetarian'])): ?>
                                                                <dd class="col-sm-12">✓ Vegetariano</dd>
                                                                <?php endif; ?>
                                                                <?php if (!empty($appData['health_vegan'])): ?>
                                                                <dd class="col-sm-12">✓ Vegano</dd>
                                                                <?php endif; ?>
                                                                <?php if (!empty($appData['health_allergies'])): ?>
                                                                <dt class="col-sm-3">Allergie Alimentari</dt>
                                                                <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($appData['health_allergies'])); ?></dd>
                                                                <?php endif; ?>
                                                                <?php if (!empty($appData['health_intolerances'])): ?>
                                                                <dt class="col-sm-3">Intolleranze Alimentari</dt>
                                                                <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($appData['health_intolerances'])); ?></dd>
                                                                <?php endif; ?>
                                                            </dl>
                                                            <?php endif; ?>
                                                            
                                                            <?php else: ?>
                                                                <p class="text-muted">Dati non disponibili</p>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($application['pdf_file'])): ?>
                                                                <div class="alert alert-info mt-3">
                                                                    <i class="bi bi-file-pdf"></i>
                                                                    <a href="download.php?type=application_pdf&id=<?php echo $application['id']; ?>" target="_blank" class="alert-link">
                                                                        Visualizza PDF completo
                                                                    </a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <?php if ($app->checkPermission('applications', 'edit')): ?>
                                                                <!-- Regenerate PDF Section -->
                                                                <form method="POST" class="me-auto">
                                                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                                                    <input type="hidden" name="action" value="regenerate_pdf">
                                                                    <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                                    
                                                                    <button type="submit" class="btn btn-warning btn-sm" title="Rigenera il PDF dell'applicazione">
                                                                        <i class="bi bi-arrow-clockwise"></i> Rigenera PDF
                                                                    </button>
                                                                    
                                                                    <div class="form-check form-check-inline ms-2">
                                                                        <input class="form-check-input" type="checkbox" name="resend_email" value="1" id="resendEmail<?php echo $application['id']; ?>">
                                                                        <label class="form-check-label" for="resendEmail<?php echo $application['id']; ?>">
                                                                            Invia anche email
                                                                        </label>
                                                                    </div>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Modal Rifiuta -->
                                            <div class="modal fade" id="rejectModal<?php echo $application['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="">
                                                            <?php echo CsrfProtection::getHiddenField(); ?>
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
                                            
                                            <!-- Modal Elimina -->
                                            <div class="modal fade" id="deleteModal<?php echo $application['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="">
                                                            <?php echo CsrfProtection::getHiddenField(); ?>
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Elimina Domanda</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                                <p>Sei sicuro di voler eliminare la domanda di <strong><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></strong>?</p>
                                                                <p class="text-danger"><small>Questa azione è irreversibile.</small></p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                <button type="submit" class="btn btn-danger">Elimina</button>
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
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo htmlspecialchars(CsrfProtection::generateToken()); ?>';
                form.appendChild(csrfInput);
                
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
