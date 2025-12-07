<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\OperationsCenterController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$isEdit = isset($_GET['id']);
$requiredPermission = $isEdit ? 'edit' : 'create';

if (!$app->checkPermission('operations_center', $requiredPermission)) {
    die('Accesso negato');
}

$controller = new OperationsCenterController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

$radio = null;
$errors = [];
$success = false;

// Load radio if editing
if ($isEdit) {
    $radio = $controller->getRadio($_GET['id']);
    if (!$radio) {
        die('Radio non trovata');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!$csrf->validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'identifier' => trim($_POST['identifier'] ?? ''),
            'device_type' => trim($_POST['device_type'] ?? ''),
            'brand' => trim($_POST['brand'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'serial_number' => trim($_POST['serial_number'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => $_POST['status'] ?? 'disponibile'
        ];
        
        // Validation
        if (empty($data['name'])) {
            $errors[] = 'Il nome è obbligatorio';
        }
        
        if (empty($errors)) {
            if ($isEdit) {
                $result = $controller->updateRadio($_GET['id'], $data, $app->getUserId());
            } else {
                $result = $controller->createRadio($data, $app->getUserId());
            }
            
            if ($result) {
                $success = true;
                header('Location: radio_directory.php?success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Radio' : 'Nuova Radio';
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
    <?php include '../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-broadcast"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="radio_directory.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna alla rubrica
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong>Errore:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Radio salvata con successo!
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                            
                            <h5 class="mb-3">Informazioni Principali</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Nome Radio *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($radio['name'] ?? $_POST['name'] ?? ''); ?>" 
                                           required maxlength="255"
                                           placeholder="Es: Radio 01, Radio Base">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="identifier" class="form-label">Identificativo</label>
                                    <input type="text" class="form-control" id="identifier" name="identifier" 
                                           value="<?php echo htmlspecialchars($radio['identifier'] ?? $_POST['identifier'] ?? ''); ?>" 
                                           maxlength="100"
                                           placeholder="Es: PC-01, Codice chiamata">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="device_type" class="form-label">Tipo Dispositivo</label>
                                    <select class="form-select" id="device_type" name="device_type">
                                        <?php
                                        $currentType = $radio['device_type'] ?? $_POST['device_type'] ?? '';
                                        $types = ['Radio Portatile', 'Radio Veicolare', 'Stazione Base', 'Ripetitore', 'Altro'];
                                        ?>
                                        <option value="">Seleziona...</option>
                                        <?php foreach ($types as $type): ?>
                                            <option value="<?php echo $type; ?>" <?php echo $currentType === $type ? 'selected' : ''; ?>>
                                                <?php echo $type; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="brand" class="form-label">Marca</label>
                                    <input type="text" class="form-control" id="brand" name="brand" 
                                           value="<?php echo htmlspecialchars($radio['brand'] ?? $_POST['brand'] ?? ''); ?>" 
                                           maxlength="100"
                                           placeholder="Es: Motorola, Kenwood">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="model" class="form-label">Modello</label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo htmlspecialchars($radio['model'] ?? $_POST['model'] ?? ''); ?>" 
                                           maxlength="100"
                                           placeholder="Es: DP4800, TK-3701D">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="serial_number" class="form-label">Numero Seriale</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                           value="<?php echo htmlspecialchars($radio['serial_number'] ?? $_POST['serial_number'] ?? ''); ?>" 
                                           maxlength="100"
                                           placeholder="Numero seriale del dispositivo">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Stato</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <?php
                                        $currentStatus = $radio['status'] ?? $_POST['status'] ?? 'disponibile';
                                        $statuses = [
                                            'disponibile' => 'Disponibile',
                                            'assegnata' => 'Assegnata',
                                            'manutenzione' => 'In Manutenzione',
                                            'fuori_servizio' => 'Fuori Servizio'
                                        ];
                                        foreach ($statuses as $value => $label):
                                        ?>
                                            <option value="<?php echo $value; ?>" <?php echo $currentStatus === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Note</label>
                                <textarea class="form-control" id="notes" name="notes" 
                                          rows="4"
                                          placeholder="Note aggiuntive, configurazioni, ecc."><?php echo htmlspecialchars($radio['notes'] ?? $_POST['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="radio_directory.php" class="btn btn-secondary">
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
</body>
</html>
