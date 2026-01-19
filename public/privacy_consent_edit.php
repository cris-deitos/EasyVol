<?php
/**
 * Gestione Consensi Privacy - Modifica/Crea
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

$consentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $consentId > 0;

if (!$app->checkPermission('gdpr_compliance', 'manage_consents')) {
    die('Accesso negato');
}

// Handle delete
if (isset($_GET['delete']) && $app->checkPermission('gdpr_compliance', 'manage_consents')) {
    $deleteId = intval($_GET['delete']);
    $db = $app->getDb();
    $controller = new GdprController($db, $app->getConfig());
    if ($controller->deleteConsent($deleteId, $app->getUserId())) {
        header('Location: privacy_consents.php?success=deleted');
        exit;
    }
}

AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

$consent = [];
$errors = [];
$success = false;

if ($isEdit) {
    $consent = $controller->getConsent($consentId);
    if (!$consent) {
        header('Location: privacy_consents.php?error=not_found');
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
            'consent_type' => $_POST['consent_type'] ?? 'privacy_policy',
            'consent_given' => !empty($_POST['consent_given']) ? 1 : 0,
            'consent_date' => $_POST['consent_date'] ?? date('Y-m-d'),
            'consent_expiry_date' => !empty($_POST['consent_expiry_date']) ? $_POST['consent_expiry_date'] : null,
            'consent_version' => trim($_POST['consent_version'] ?? ''),
            'consent_method' => $_POST['consent_method'] ?? 'paper',
            'consent_document_path' => trim($_POST['consent_document_path'] ?? ''),
            'revoked' => !empty($_POST['revoked']) ? 1 : 0,
            'revoked_date' => !empty($_POST['revoked_date']) ? $_POST['revoked_date'] : null,
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->updateConsent($consentId, $data, $app->getUserId());
            } else {
                $result = $controller->createConsent($data, $app->getUserId());
                $consentId = $result;
            }
            
            if ($result) {
                header('Location: privacy_consents.php?success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Consenso Privacy' : 'Nuovo Consenso Privacy';
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
                        <a href="privacy_consents.php" class="text-decoration-none text-muted">
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
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="entity_type" class="form-label">Tipo Entità *</label>
                                    <select class="form-select" id="entity_type" name="entity_type" required onchange="toggleEntitySearch()">
                                        <option value="member" <?php echo ($consent['entity_type'] ?? 'member') === 'member' ? 'selected' : ''; ?>>Socio</option>
                                        <option value="junior_member" <?php echo ($consent['entity_type'] ?? '') === 'junior_member' ? 'selected' : ''; ?>>Cadetto</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="entity_search" class="form-label">Seleziona Persona *</label>
                                    <?php
                                    $currentEntityName = '';
                                    if (!empty($consent['entity_id'])) {
                                        if ($consent['entity_type'] === 'member') {
                                            $entity = $db->fetchOne("SELECT first_name, last_name, registration_number FROM members WHERE id = ?", [$consent['entity_id']]);
                                        } else {
                                            $entity = $db->fetchOne("SELECT first_name, last_name, registration_number FROM junior_members WHERE id = ?", [$consent['entity_id']]);
                                        }
                                        if ($entity) {
                                            $currentEntityName = $entity['registration_number'] . ' - ' . $entity['last_name'] . ' ' . $entity['first_name'];
                                        }
                                    }
                                    ?>
                                    <input type="text" class="form-control" id="entity_search" 
                                           placeholder="Cerca per nome, cognome, matricola o codice fiscale..." 
                                           value="<?php echo htmlspecialchars($currentEntityName); ?>" 
                                           autocomplete="off" required>
                                    <input type="hidden" id="entity_id" name="entity_id" value="<?php echo htmlspecialchars($consent['entity_id'] ?? ''); ?>" required>
                                    <div id="entity_search_results" class="list-group position-absolute" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"></div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="consent_type" class="form-label">Tipo Consenso *</label>
                                    <select class="form-select" id="consent_type" name="consent_type" required>
                                        <option value="privacy_policy" <?php echo ($consent['consent_type'] ?? '') === 'privacy_policy' ? 'selected' : ''; ?>>Privacy Policy</option>
                                        <option value="data_processing" <?php echo ($consent['consent_type'] ?? '') === 'data_processing' ? 'selected' : ''; ?>>Trattamento Dati</option>
                                        <option value="sensitive_data" <?php echo ($consent['consent_type'] ?? '') === 'sensitive_data' ? 'selected' : ''; ?>>Dati Sensibili</option>
                                        <option value="marketing" <?php echo ($consent['consent_type'] ?? '') === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                        <option value="third_party_communication" <?php echo ($consent['consent_type'] ?? '') === 'third_party_communication' ? 'selected' : ''; ?>>Comunicazione Terzi</option>
                                        <option value="image_rights" <?php echo ($consent['consent_type'] ?? '') === 'image_rights' ? 'selected' : ''; ?>>Diritti Immagine</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="consent_method" class="form-label">Modalità Acquisizione *</label>
                                    <select class="form-select" id="consent_method" name="consent_method" required>
                                        <option value="paper" <?php echo ($consent['consent_method'] ?? 'paper') === 'paper' ? 'selected' : ''; ?>>Cartaceo</option>
                                        <option value="digital" <?php echo ($consent['consent_method'] ?? '') === 'digital' ? 'selected' : ''; ?>>Digitale</option>
                                        <option value="verbal" <?php echo ($consent['consent_method'] ?? '') === 'verbal' ? 'selected' : ''; ?>>Verbale</option>
                                        <option value="implicit" <?php echo ($consent['consent_method'] ?? '') === 'implicit' ? 'selected' : ''; ?>>Implicito</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="consent_date" class="form-label">Data Consenso *</label>
                                    <input type="date" class="form-control" id="consent_date" name="consent_date" 
                                           value="<?php echo htmlspecialchars($consent['consent_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="consent_expiry_date" class="form-label">Data Scadenza</label>
                                    <input type="date" class="form-control" id="consent_expiry_date" name="consent_expiry_date" 
                                           value="<?php echo htmlspecialchars($consent['consent_expiry_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="consent_version" class="form-label">Versione Informativa</label>
                                    <input type="text" class="form-control" id="consent_version" name="consent_version" 
                                           value="<?php echo htmlspecialchars($consent['consent_version'] ?? ''); ?>" placeholder="Es: 1.0">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="consent_given" name="consent_given" 
                                               <?php echo !empty($consent['consent_given']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="consent_given">
                                            Consenso Dato
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="revoked" name="revoked" 
                                               <?php echo !empty($consent['revoked']) ? 'checked' : ''; ?> onchange="toggleRevokedDate()">
                                        <label class="form-check-label" for="revoked">
                                            Consenso Revocato
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-4" id="revoked_date_row" style="display: <?php echo !empty($consent['revoked']) ? 'block' : 'none'; ?>;">
                                <div class="col-md-6">
                                    <label for="revoked_date" class="form-label">Data Revoca</label>
                                    <input type="date" class="form-control" id="revoked_date" name="revoked_date" 
                                           value="<?php echo htmlspecialchars($consent['revoked_date'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="consent_document_path" class="form-label">Percorso Documento</label>
                                    <input type="text" class="form-control" id="consent_document_path" name="consent_document_path" 
                                           value="<?php echo htmlspecialchars($consent['consent_document_path'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="notes" class="form-label">Note</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($consent['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="privacy_consents.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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
        
        function toggleEntitySearch() {
            // Clear the search when switching entity type
            entitySearchInput.value = '';
            entityIdInput.value = '';
            entitySearchResults.style.display = 'none';
            entitySearchResults.innerHTML = '';
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
        
        function toggleRevokedDate() {
            const revoked = document.getElementById('revoked').checked;
            document.getElementById('revoked_date_row').style.display = revoked ? 'block' : 'none';
        }
    </script>
</body>
</html>
