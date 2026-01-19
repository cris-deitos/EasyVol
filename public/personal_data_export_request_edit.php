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
                                    <select class="form-select" id="entity_type" name="entity_type" required onchange="clearEntitySelection()">
                                        <option value="member" <?php echo ($request['entity_type'] ?? 'member') === 'member' ? 'selected' : ''; ?>>Socio</option>
                                        <option value="junior_member" <?php echo ($request['entity_type'] ?? '') === 'junior_member' ? 'selected' : ''; ?>>Cadetto</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <?php
                                    $currentEntityName = '';
                                    if (!empty($request['entity_id'])) {
                                        if ($request['entity_type'] === 'member') {
                                            $entity = $db->fetchOne("SELECT first_name, last_name, registration_number FROM members WHERE id = ?", [$request['entity_id']]);
                                        } else {
                                            $entity = $db->fetchOne("SELECT first_name, last_name, registration_number FROM junior_members WHERE id = ?", [$request['entity_id']]);
                                        }
                                        if ($entity) {
                                            $currentEntityName = $entity['last_name'] . ' ' . $entity['first_name'] . ' (Mat. ' . $entity['registration_number'] . ')';
                                        }
                                    }
                                    ?>
                                    <label for="entity_search" class="form-label">Seleziona Persona *</label>
                                    <input type="text" class="form-control" id="entity_search" 
                                           placeholder="Cerca per nome, cognome, matricola o codice fiscale..." 
                                           value="<?php echo htmlspecialchars($currentEntityName); ?>" 
                                           autocomplete="off" required>
                                    <input type="hidden" id="entity_id" name="entity_id" value="<?php echo htmlspecialchars($request['entity_id'] ?? ''); ?>" required>
                                    <input type="hidden" id="entity_type_hidden" name="entity_type" value="<?php echo htmlspecialchars($request['entity_type'] ?? 'member'); ?>">
                                    <div id="entity_search_results" class="list-group position-absolute" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"></div>
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
        // Entity search autocomplete
        let entitySearchTimeout = null;
        const entitySearchInput = document.getElementById('entity_search');
        const entityIdInput = document.getElementById('entity_id');
        const entitySearchResults = document.getElementById('entity_search_results');
        const entityTypeSelect = document.getElementById('entity_type');
        const entityTypeHidden = document.getElementById('entity_type_hidden');
        
        function clearEntitySelection() {
            // Clear the search when switching entity type
            entitySearchInput.value = '';
            entityIdInput.value = '';
            entitySearchResults.style.display = 'none';
            entitySearchResults.innerHTML = '';
            entityTypeHidden.value = entityTypeSelect.value;
        }
        
        if (entitySearchInput) {
            entitySearchInput.addEventListener('input', function() {
                clearTimeout(entitySearchTimeout);
                const search = this.value.trim();
                
                if (search.length < 2) {
                    entitySearchResults.style.display = 'none';
                    entitySearchResults.innerHTML = '';
                    if (search.length === 0) {
                        entityIdInput.value = '';
                    }
                    return;
                }
                
                const entityType = entityTypeSelect.value;
                
                entitySearchTimeout = setTimeout(function() {
                    fetch('entity_search_ajax.php?q=' + encodeURIComponent(search) + '&type=' + entityType)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                entitySearchResults.innerHTML = '<div class="list-group-item text-muted">Nessuna persona trovata</div>';
                                entitySearchResults.style.display = 'block';
                                return;
                            }
                            
                            entitySearchResults.innerHTML = data.map(function(entity) {
                                return '<button type="button" class="list-group-item list-group-item-action" data-entity-id="' + entity.id + '" data-entity-label="' + escapeHtml(entity.label) + '">' +
                                    escapeHtml(entity.label) +
                                    '</button>';
                            }).join('');
                            entitySearchResults.style.display = 'block';
                            
                            // Add click handlers
                            entitySearchResults.querySelectorAll('button').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    entityIdInput.value = this.dataset.entityId;
                                    entitySearchInput.value = this.dataset.entityLabel;
                                    entitySearchResults.style.display = 'none';
                                    entityTypeHidden.value = entityTypeSelect.value;
                                });
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            entitySearchResults.innerHTML = '<div class="list-group-item text-danger">Errore nella ricerca</div>';
                            entitySearchResults.style.display = 'block';
                        });
                }, 300);
            });
            
            // Close results when clicking outside
            document.addEventListener('click', function(e) {
                if (!entitySearchInput.contains(e.target) && !entitySearchResults.contains(e.target)) {
                    entitySearchResults.style.display = 'none';
                }
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
