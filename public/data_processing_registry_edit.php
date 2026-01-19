<?php
/**
 * Gestione Registro Trattamenti - Modifica/Crea
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

$registryId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $registryId > 0;

if (!$app->checkPermission('gdpr_compliance', 'manage_processing_registry')) {
    die('Accesso negato');
}

// Handle delete
if (isset($_GET['delete']) && $app->checkPermission('gdpr_compliance', 'manage_processing_registry')) {
    $deleteId = intval($_GET['delete']);
    $db = $app->getDb();
    $controller = new GdprController($db, $app->getConfig());
    if ($controller->deleteProcessingRegistry($deleteId, $app->getUserId())) {
        header('Location: data_processing_registry.php?success=deleted');
        exit;
    }
}

AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

$registry = [];
$errors = [];
$success = false;

if ($isEdit) {
    $registry = $controller->getProcessingRegistry($registryId);
    if (!$registry) {
        header('Location: data_processing_registry.php?error=not_found');
        exit;
    }
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'processing_name' => trim($_POST['processing_name'] ?? ''),
            'processing_purpose' => trim($_POST['processing_purpose'] ?? ''),
            'data_categories' => trim($_POST['data_categories'] ?? ''),
            'data_subjects' => trim($_POST['data_subjects'] ?? ''),
            'recipients' => trim($_POST['recipients'] ?? ''),
            'third_country_transfer' => !empty($_POST['third_country_transfer']) ? 1 : 0,
            'third_country_details' => trim($_POST['third_country_details'] ?? ''),
            'retention_period' => trim($_POST['retention_period'] ?? ''),
            'security_measures' => trim($_POST['security_measures'] ?? ''),
            'legal_basis' => $_POST['legal_basis'] ?? 'consent',
            'legal_basis_details' => trim($_POST['legal_basis_details'] ?? ''),
            'data_controller' => trim($_POST['data_controller'] ?? ''),
            'data_processor' => trim($_POST['data_processor'] ?? ''),
            'dpo_contact' => trim($_POST['dpo_contact'] ?? ''),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        // Validation
        if (empty($data['processing_name'])) {
            $errors[] = 'Il nome del trattamento è obbligatorio';
        }
        if (empty($data['processing_purpose'])) {
            $errors[] = 'La finalità del trattamento è obbligatoria';
        }
        if (empty($data['data_categories'])) {
            $errors[] = 'Le categorie di dati sono obbligatorie';
        }
        if (empty($data['data_subjects'])) {
            $errors[] = 'Le categorie di interessati sono obbligatorie';
        }
        
        if (empty($errors)) {
            try {
                if ($isEdit) {
                    $result = $controller->updateProcessingRegistry($registryId, $data, $app->getUserId());
                } else {
                    $result = $controller->createProcessingRegistry($data, $app->getUserId());
                    $registryId = $result;
                }
                
                if ($result) {
                    header('Location: data_processing_registry.php?success=1');
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

$pageTitle = $isEdit ? 'Modifica Registro Trattamento' : 'Nuovo Registro Trattamento';
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
                        <a href="data_processing_registry.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna all'elenco
                        </a>
                        <?php if ($isEdit && $app->checkPermission('gdpr_compliance', 'manage_processing_registry')): ?>
                            <a href="?delete=<?php echo $registryId; ?>" 
                               class="btn btn-outline-danger ms-2" 
                               onclick="return confirm('Sei sicuro di voler eliminare questo registro?');">
                                <i class="bi bi-trash"></i> Elimina
                            </a>
                        <?php endif; ?>
                    </div>
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
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php echo CsrfProtection::getHiddenField(); ?>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="processing_name" class="form-label">Nome Trattamento <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="processing_name" name="processing_name" 
                                               value="<?php echo htmlspecialchars($registry['processing_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="legal_basis" class="form-label">Base Giuridica <span class="text-danger">*</span></label>
                                        <select class="form-select" id="legal_basis" name="legal_basis" required>
                                            <option value="consent" <?php echo ($registry['legal_basis'] ?? '') === 'consent' ? 'selected' : ''; ?>>Consenso</option>
                                            <option value="contract" <?php echo ($registry['legal_basis'] ?? '') === 'contract' ? 'selected' : ''; ?>>Contratto</option>
                                            <option value="legal_obligation" <?php echo ($registry['legal_basis'] ?? '') === 'legal_obligation' ? 'selected' : ''; ?>>Obbligo Legale</option>
                                            <option value="vital_interests" <?php echo ($registry['legal_basis'] ?? '') === 'vital_interests' ? 'selected' : ''; ?>>Interessi Vitali</option>
                                            <option value="public_interest" <?php echo ($registry['legal_basis'] ?? '') === 'public_interest' ? 'selected' : ''; ?>>Interesse Pubblico</option>
                                            <option value="legitimate_interest" <?php echo ($registry['legal_basis'] ?? '') === 'legitimate_interest' ? 'selected' : ''; ?>>Interesse Legittimo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="processing_purpose" class="form-label">Finalità del Trattamento <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="processing_purpose" name="processing_purpose" rows="3" required><?php echo htmlspecialchars($registry['processing_purpose'] ?? ''); ?></textarea>
                                <div class="form-text">Descrizione dettagliata della finalità per cui i dati vengono trattati</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="legal_basis_details" class="form-label">Dettagli Base Giuridica</label>
                                <textarea class="form-control" id="legal_basis_details" name="legal_basis_details" rows="2"><?php echo htmlspecialchars($registry['legal_basis_details'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="data_categories" class="form-label">Categorie di Dati Trattati <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="data_categories" name="data_categories" rows="3" required><?php echo htmlspecialchars($registry['data_categories'] ?? ''); ?></textarea>
                                        <div class="form-text">Es: Dati anagrafici, dati di contatto, dati sanitari, etc.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="data_subjects" class="form-label">Categorie di Interessati <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="data_subjects" name="data_subjects" rows="3" required><?php echo htmlspecialchars($registry['data_subjects'] ?? ''); ?></textarea>
                                        <div class="form-text">Es: Soci, cadetti, volontari, dipendenti, etc.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="recipients" class="form-label">Destinatari o Categorie di Destinatari</label>
                                <textarea class="form-control" id="recipients" name="recipients" rows="2"><?php echo htmlspecialchars($registry['recipients'] ?? ''); ?></textarea>
                                <div class="form-text">Soggetti o categorie di soggetti a cui i dati possono essere comunicati</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="third_country_transfer" name="third_country_transfer" 
                                                   <?php echo !empty($registry['third_country_transfer']) ? 'checked' : ''; ?>
                                                   onchange="toggleThirdCountryDetails()">
                                            <label class="form-check-label" for="third_country_transfer">
                                                Trasferimento verso Paesi Terzi
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3" id="third_country_details_container" style="display: <?php echo !empty($registry['third_country_transfer']) ? 'block' : 'none'; ?>;">
                                        <label for="third_country_details" class="form-label">Dettagli Trasferimento Paesi Terzi</label>
                                        <textarea class="form-control" id="third_country_details" name="third_country_details" rows="2"><?php echo htmlspecialchars($registry['third_country_details'] ?? ''); ?></textarea>
                                        <div class="form-text">Indicare paesi, garanzie e strumenti di protezione applicati</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="retention_period" class="form-label">Periodo di Conservazione</label>
                                        <textarea class="form-control" id="retention_period" name="retention_period" rows="2"><?php echo htmlspecialchars($registry['retention_period'] ?? ''); ?></textarea>
                                        <div class="form-text">Es: 10 anni dalla cessazione del rapporto associativo</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="security_measures" class="form-label">Misure di Sicurezza Tecniche e Organizzative</label>
                                <textarea class="form-control" id="security_measures" name="security_measures" rows="3"><?php echo htmlspecialchars($registry['security_measures'] ?? ''); ?></textarea>
                                <div class="form-text">Descrivere le misure adottate per proteggere i dati personali</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="data_controller" class="form-label">Titolare del Trattamento</label>
                                        <input type="text" class="form-control" id="data_controller" name="data_controller" 
                                               value="<?php echo htmlspecialchars($registry['data_controller'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="data_processor" class="form-label">Responsabile del Trattamento</label>
                                        <input type="text" class="form-control" id="data_processor" name="data_processor" 
                                               value="<?php echo htmlspecialchars($registry['data_processor'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="dpo_contact" class="form-label">Contatto DPO</label>
                                        <input type="text" class="form-control" id="dpo_contact" name="dpo_contact" 
                                               value="<?php echo htmlspecialchars($registry['dpo_contact'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Note Aggiuntive</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($registry['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?php echo ($registry['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Trattamento Attivo
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="data_processing_registry.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Salva
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
        function toggleThirdCountryDetails() {
            const checkbox = document.getElementById('third_country_transfer');
            const container = document.getElementById('third_country_details_container');
            container.style.display = checkbox.checked ? 'block' : 'none';
        }
    </script>
</body>
</html>
