<?php
/**
 * Gestione Riunioni - Modifica/Crea
 * 
 * Pagina per creare o modificare una riunione/assemblea
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\MeetingController;
use EasyVol\Middleware\CsrfProtection;

$app = new App();

// Verifica autenticazione
if (!$app->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$meetingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $meetingId > 0;

// Verifica permessi
if ($isEdit && !$app->hasPermission('meetings', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->hasPermission('meetings', 'create')) {
    die('Accesso negato');
}

$db = $app->getDatabase();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

$meeting = [];
$errors = [];
$success = false;

// Se in modifica, carica dati esistenti
if ($isEdit) {
    $meeting = $controller->get($meetingId);
    if (!$meeting) {
        header('Location: meetings.php?error=not_found');
        exit;
    }
}

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'meeting_type' => $_POST['meeting_type'] ?? 'consiglio_direttivo',
            'title' => trim($_POST['title'] ?? ''),
            'meeting_date' => $_POST['meeting_date'] ?? '',
            'location' => trim($_POST['location'] ?? ''),
            'convened_by' => trim($_POST['convened_by'] ?? ''),
            'president' => trim($_POST['president'] ?? ''),
            'secretary' => trim($_POST['secretary'] ?? ''),
            'minutes' => trim($_POST['minutes'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->update($meetingId, $data, $app->getUserId());
            } else {
                $result = $controller->create($data, $app->getUserId());
                $meetingId = $result;
            }
            
            if ($result) {
                $success = true;
                header('Location: meeting_view.php?id=' . $meetingId . '&success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Riunione' : 'Nuova Riunione';
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
                        <a href="meetings.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar3"></i> Dati Riunione</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Titolo Riunione <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($meeting['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="meeting_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                                    <select class="form-select" id="meeting_type" name="meeting_type" required>
                                        <option value="assemblea_ordinaria" <?php echo ($meeting['meeting_type'] ?? '') === 'assemblea_ordinaria' ? 'selected' : ''; ?>>Assemblea Ordinaria</option>
                                        <option value="assemblea_straordinaria" <?php echo ($meeting['meeting_type'] ?? '') === 'assemblea_straordinaria' ? 'selected' : ''; ?>>Assemblea Straordinaria</option>
                                        <option value="consiglio_direttivo" <?php echo ($meeting['meeting_type'] ?? 'consiglio_direttivo') === 'consiglio_direttivo' ? 'selected' : ''; ?>>Consiglio Direttivo</option>
                                        <option value="altro" <?php echo ($meeting['meeting_type'] ?? '') === 'altro' ? 'selected' : ''; ?>>Altro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="meeting_date" class="form-label">Data e Ora <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="meeting_date" name="meeting_date" 
                                           value="<?php echo !empty($meeting['meeting_date']) ? date('Y-m-d\TH:i', strtotime($meeting['meeting_date'])) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Luogo</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($meeting['location'] ?? ''); ?>"
                                           placeholder="es. Sede Associazione">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-people"></i> Cariche</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="convened_by" class="form-label">Convocata da</label>
                                    <input type="text" class="form-control" id="convened_by" name="convened_by" 
                                           value="<?php echo htmlspecialchars($meeting['convened_by'] ?? ''); ?>"
                                           placeholder="es. Il Presidente">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="president" class="form-label">Presidente</label>
                                    <input type="text" class="form-control" id="president" name="president" 
                                           value="<?php echo htmlspecialchars($meeting['president'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="secretary" class="form-label">Segretario</label>
                                    <input type="text" class="form-control" id="secretary" name="secretary" 
                                           value="<?php echo htmlspecialchars($meeting['secretary'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="bi bi-file-text"></i> Verbale</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="minutes" class="form-label">Contenuto Verbale</label>
                                <textarea class="form-control" id="minutes" name="minutes" rows="10"><?php echo htmlspecialchars($meeting['minutes'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Inserire qui il testo completo del verbale</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($meeting['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="meetings.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annulla
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salva
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
