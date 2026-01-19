<?php
/**
 * Gestione Richiesta Export Dati Personali - Modifica/Crea
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\GdprController;
use EasyVol\Utils\AutoLogger;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('gdpr_compliance', 'export_personal_data')) {
    die('Accesso negato');
}

$requestId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $requestId > 0;

// Handle delete
if (isset($_GET['delete']) && $app->checkPermission('gdpr_compliance', 'export_personal_data')) {
    $deleteId = intval($_GET['delete']);
    $db = $app->getDb();
    $controller = new GdprController($db, $app->getConfig());
    if ($controller->deleteExportRequest($deleteId, $app->getUserId())) {
        header('Location: personal_data_export_requests.php?success=deleted');
        exit;
    }
}

AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

$request = [];
$errors = [];
$success = false;

if ($isEdit) {
    $request = $controller->getExportRequest($requestId);
    if (!$request) {
        header('Location: personal_data_export_requests.php?error=not_found');
        exit;
    }
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'entity_type' => $_POST['entity_type'] ?? 'member',
            'entity_id' => intval($_POST['entity_id'] ?? 0),
            'request_reason' => trim($_POST['request_reason'] ?? ''),
            'status' => $_POST['status'] ?? 'pending',
            'completed_date' => !empty($_POST['completed_date']) ? $_POST['completed_date'] : null,
            'export_file_path' => trim($_POST['export_file_path'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        // Validation
        if ($data['entity_id'] == 0) {
            $errors[] = 'Selezionare un\'entità (socio o cadetto)';
        }
        
        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $result = $controller->updateExportRequest($requestId, $data, $app->getUserId());
                } else {
                    $result = $controller->createExportRequest($data, $app->getUserId());
                    $requestId = $result;
                }
                
                if ($result) {
                    header('Location: personal_data_export_requests.php?success=1');
                    exit;
                } else {
                    $errors[] = 'Errore durante il salvataggio';
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get members and junior members for dropdown
$members = $controller->getMembers();
$juniorMembers = $controller->getJuniorMembers();

$pageTitle = $isEdit ? 'Modifica Richiesta Export' : 'Nuova Richiesta Export';
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
                        <a href="personal_data_export_requests.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5>Errori:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php echo CsrfProtection::getHiddenField(); ?>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                <strong>Richiesta Export Dati Personali</strong><br>
                                Questa funzionalità permette di gestire le richieste di export dei dati personali in conformità con il diritto di accesso GDPR (Art. 15).
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="entity_type" class="form-label">Tipo Entità *</label>
                                    <select class="form-select" id="entity_type" name="entity_type" required>
                                        <option value="member" <?php echo ($request['entity_type'] ?? 'member') === 'member' ? 'selected' : ''; ?>>Socio</option>
                                        <option value="junior_member" <?php echo ($request['entity_type'] ?? '') === 'junior_member' ? 'selected' : ''; ?>>Cadetto</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="entity_id" class="form-label">Seleziona Persona *</label>
                                    <select class="form-select" id="entity_id" name="entity_id" required>
                                        <option value="">Seleziona...</option>
                                        <optgroup label="Soci" id="members-group">
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo $member['id']; ?>" 
                                                        data-type="member"
                                                        <?php echo ($request['entity_type'] ?? '') === 'member' && ($request['entity_id'] ?? 0) == $member['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['registration_number'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Cadetti" id="junior-members-group">
                                            <?php foreach ($juniorMembers as $junior): ?>
                                                <option value="<?php echo $junior['id']; ?>" 
                                                        data-type="junior_member"
                                                        <?php echo ($request['entity_type'] ?? '') === 'junior_member' && ($request['entity_id'] ?? 0) == $junior['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($junior['first_name'] . ' ' . $junior['last_name'] . ' (' . $junior['registration_number'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="request_reason" class="form-label">Motivazione Richiesta</label>
                                    <textarea class="form-control" id="request_reason" name="request_reason" rows="3"><?php echo htmlspecialchars($request['request_reason'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">
                                        Descrivere brevemente il motivo della richiesta di export dati
                                    </small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Stato Richiesta *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="pending" <?php echo ($request['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>In Attesa</option>
                                        <option value="processing" <?php echo ($request['status'] ?? '') === 'processing' ? 'selected' : ''; ?>>In Elaborazione</option>
                                        <option value="completed" <?php echo ($request['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completata</option>
                                        <option value="rejected" <?php echo ($request['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rifiutata</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="completed_date" class="form-label">Data Completamento</label>
                                    <input type="datetime-local" class="form-control" id="completed_date" name="completed_date" 
                                           value="<?php echo !empty($request['completed_date']) ? date('Y-m-d\TH:i', strtotime($request['completed_date'])) : ''; ?>">
                                    <small class="form-text text-muted">
                                        Compilare quando la richiesta è stata completata
                                    </small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="export_file_path" class="form-label">Percorso File Esportato</label>
                                    <input type="text" class="form-control" id="export_file_path" name="export_file_path" 
                                           value="<?php echo htmlspecialchars($request['export_file_path'] ?? ''); ?>"
                                           placeholder="/uploads/gdpr_exports/export_123.pdf">
                                    <small class="form-text text-muted">
                                        Inserire il percorso relativo del file esportato (es: /uploads/gdpr_exports/...)
                                    </small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="notes" class="form-label">Note</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($request['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="personal_data_export_requests.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva Richiesta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($isEdit && $request): ?>
                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Informazioni Richiesta</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">Data Richiesta:</dt>
                            <dd class="col-sm-9"><?php echo date('d/m/Y H:i', strtotime($request['request_date'])); ?></dd>
                            
                            <dt class="col-sm-3">Richiesta da:</dt>
                            <dd class="col-sm-9"><?php echo htmlspecialchars($request['requested_by_username'] ?? 'N/D'); ?></dd>
                            
                            <dt class="col-sm-3">Entità:</dt>
                            <dd class="col-sm-9">
                                <span class="badge bg-<?php echo $request['entity_type'] === 'member' ? 'primary' : 'info'; ?>">
                                    <?php echo $request['entity_type'] === 'member' ? 'Socio' : 'Cadetto'; ?>
                                </span>
                                <?php echo htmlspecialchars($request['entity_name'] ?? 'N/D'); ?>
                            </dd>
                        </dl>
                    </div>
                </div>
                <?php endif; ?>
                
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter entity dropdown based on type selection
        document.getElementById('entity_type').addEventListener('change', function() {
            const entityType = this.value;
            const entitySelect = document.getElementById('entity_id');
            const options = entitySelect.querySelectorAll('option[data-type]');
            
            // Reset selection
            entitySelect.value = '';
            
            // Show/hide options based on type
            options.forEach(option => {
                if (option.dataset.type === entityType) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Show/hide optgroups
            if (entityType === 'member') {
                document.getElementById('members-group').style.display = '';
                document.getElementById('junior-members-group').style.display = 'none';
            } else {
                document.getElementById('members-group').style.display = 'none';
                document.getElementById('junior-members-group').style.display = '';
            }
        });
        
        // Trigger initial filtering
        document.getElementById('entity_type').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
