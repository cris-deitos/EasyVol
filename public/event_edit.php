<?php
/**
 * Gestione Eventi - Modifica/Crea
 * 
 * Pagina per creare o modificare un evento/intervento
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\EventController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $eventId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('events', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('events', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EventController($db, $config);

$event = [];
$errors = [];
$success = false;

// Se in modifica, carica dati esistenti
if ($isEdit) {
    $event = $controller->get($eventId);
    if (!$event) {
        header('Location: events.php?error=not_found');
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
            'event_type' => $_POST['event_type'] ?? 'attivita',
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'status' => $_POST['status'] ?? 'aperto'
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->update($eventId, $data, $app->getUserId());
            } else {
                $result = $controller->create($data, $app->getUserId());
                $eventId = $result;
            }
            
            if ($result) {
                $success = true;
                header('Location: event_view.php?id=' . $eventId . '&success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Evento' : 'Nuovo Evento';
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
                        <a href="events.php" class="text-decoration-none text-muted">
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
                            <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Dati Evento</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Titolo Evento <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="event_type" class="form-label">Tipo Evento <span class="text-danger">*</span></label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="emergenza" <?php echo ($event['event_type'] ?? '') === 'emergenza' ? 'selected' : ''; ?>>Emergenza</option>
                                        <option value="esercitazione" <?php echo ($event['event_type'] ?? '') === 'esercitazione' ? 'selected' : ''; ?>>Esercitazione</option>
                                        <option value="attivita" <?php echo ($event['event_type'] ?? 'attivita') === 'attivita' ? 'selected' : ''; ?>>Attività</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="description" class="form-label">Descrizione</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="location" class="form-label">Località</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
                                           placeholder="es. Via Roma 123, Milano">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-clock"></i> Date e Orari</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Data e Ora Inizio <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo !empty($event['start_date']) ? date('Y-m-d\TH:i', strtotime($event['start_date'])) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">Data e Ora Fine</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo !empty($event['end_date']) ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ''; ?>">
                                    <small class="form-text text-muted">Lasciare vuoto se l'evento è ancora in corso</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-flag"></i> Stato</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="status" class="form-label">Stato Evento <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="aperto" <?php echo ($event['status'] ?? 'aperto') === 'aperto' ? 'selected' : ''; ?>>Aperto</option>
                                        <option value="in_corso" <?php echo ($event['status'] ?? '') === 'in_corso' ? 'selected' : ''; ?>>In Corso</option>
                                        <option value="concluso" <?php echo ($event['status'] ?? '') === 'concluso' ? 'selected' : ''; ?>>Concluso</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="events.php" class="btn btn-secondary">
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
