<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\DispatchController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'edit')) {
    die('Accesso negato');
}

$controller = new DispatchController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        try {
            $controller->updateRaspberryConfig('api_enabled', $_POST['api_enabled'] ?? '0');
            $controller->updateRaspberryConfig('api_key', $_POST['api_key'] ?? '');
            $controller->updateRaspberryConfig('audio_storage_path', $_POST['audio_storage_path'] ?? 'uploads/dispatch/audio/');
            $controller->updateRaspberryConfig('max_audio_file_size', $_POST['max_audio_file_size'] ?? '10485760');
            $controller->updateRaspberryConfig('position_update_interval', $_POST['position_update_interval'] ?? '60');
            $controller->updateRaspberryConfig('position_inactive_threshold', $_POST['position_inactive_threshold'] ?? '1800');
            
            $success = true;
        } catch (Exception $e) {
            $errors[] = 'Errore: ' . $e->getMessage();
        }
    }
}

$config = $controller->getRaspberryConfig();
$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];
$pageTitle = 'Configurazione Raspberry Pi';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo $isCoUser ? 'EasyCO' : 'EasyVol'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <?php if ($isCoUser): ?>
        <link rel="stylesheet" href="../assets/css/easyco.css">
    <?php endif; ?>
</head>
<body>
    <?php 
    if ($isCoUser) {
        include '../src/Views/includes/navbar_operations.php';
    } else {
        include '../src/Views/includes/navbar.php';
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            if ($isCoUser) {
                include '../src/Views/includes/sidebar_operations.php';
            } else {
                include '../src/Views/includes/sidebar.php';
            }
            ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-gear-fill"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dispatch.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna al Dispatch
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Configurazione salvata con successo!
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Guida Integrazione</h5>
                    </div>
                    <div class="card-body">
                        <p>Per integrare il sistema di dispatch con un Raspberry Pi, consulta la 
                        <a href="../DISPATCH_RASPBERRY_PI_GUIDE.md" target="_blank">documentazione completa</a>.</p>
                        <p><strong>Endpoint API Base:</strong> <code><?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']); ?>/public/api/dispatch/</code></p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Configurazione API</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Stato API</label>
                                <select class="form-select" name="api_enabled">
                                    <option value="0" <?php echo ($config['api_enabled'] ?? '0') === '0' ? 'selected' : ''; ?>>
                                        Disabilitata
                                    </option>
                                    <option value="1" <?php echo ($config['api_enabled'] ?? '0') === '1' ? 'selected' : ''; ?>>
                                        Abilitata
                                    </option>
                                </select>
                                <small class="text-muted">Abilita l'accesso alle API per il Raspberry Pi</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="api_key" id="api_key"
                                           value="<?php echo htmlspecialchars($config['api_key'] ?? ''); ?>"
                                           placeholder="Lascia vuoto per nessuna autenticazione (non raccomandato)">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateApiKey()">
                                        <i class="bi bi-arrow-repeat"></i> Genera
                                    </button>
                                </div>
                                <small class="text-muted">Chiave per autenticare le richieste API. Usa una chiave sicura (minimo 32 caratteri).</small>
                            </div>
                            
                            <hr>
                            
                            <h6>Impostazioni Audio</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Percorso Archiviazione Audio</label>
                                <input type="text" class="form-control" name="audio_storage_path"
                                       value="<?php echo htmlspecialchars($config['audio_storage_path'] ?? 'uploads/dispatch/audio/'); ?>">
                                <small class="text-muted">Percorso relativo per salvare i file audio</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Dimensione Massima File Audio (bytes)</label>
                                <input type="number" class="form-control" name="max_audio_file_size"
                                       value="<?php echo htmlspecialchars($config['max_audio_file_size'] ?? '10485760'); ?>">
                                <small class="text-muted">Default: 10485760 (10MB)</small>
                            </div>
                            
                            <hr>
                            
                            <h6>Impostazioni Posizione GPS</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Intervallo Aggiornamento Posizione (secondi)</label>
                                <input type="number" class="form-control" name="position_update_interval"
                                       value="<?php echo htmlspecialchars($config['position_update_interval'] ?? '60'); ?>">
                                <small class="text-muted">Frequenza attesa degli aggiornamenti di posizione</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Soglia Inattività Radio (secondi)</label>
                                <input type="number" class="form-control" name="position_inactive_threshold"
                                       value="<?php echo htmlspecialchars($config['position_inactive_threshold'] ?? '1800'); ?>">
                                <small class="text-muted">Dopo questo tempo senza aggiornamenti, la radio viene nascosta dalla mappa (default: 1800 = 30 minuti)</small>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dispatch.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva Configurazione
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
        function generateApiKey() {
            // Generate a secure random API key
            const array = new Uint8Array(32);
            crypto.getRandomValues(array);
            const apiKey = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
            document.getElementById('api_key').value = apiKey;
        }
    </script>
</body>
</html>
