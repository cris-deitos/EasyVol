<?php
/**
 * Gestione Magazzino - Modifica/Crea
 * 
 * Pagina per creare o modificare un articolo di magazzino
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\WarehouseController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $itemId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('warehouse', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('warehouse', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new WarehouseController($db, $config);

$item = [];
$errors = [];
$success = false;

// Se in modifica, carica dati esistenti
if ($isEdit) {
    $item = $controller->get($itemId);
    if (!$item) {
        header('Location: warehouse.php?error=not_found');
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
            'code' => trim($_POST['code'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category' => $_POST['category'] ?? 'altro',
            'unit' => trim($_POST['unit'] ?? ''),
            'quantity' => intval($_POST['quantity'] ?? 0),
            'minimum_quantity' => intval($_POST['minimum_quantity'] ?? 0),
            'location' => trim($_POST['location'] ?? ''),
            'status' => $_POST['status'] ?? 'disponibile',
            'notes' => trim($_POST['notes'] ?? ''),
            'generate_qr' => isset($_POST['generate_qr']) ? 1 : 0,
            'generate_barcode' => isset($_POST['generate_barcode']) ? 1 : 0
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->update($itemId, $data, $app->getUserId());
            } else {
                $result = $controller->create($data, $app->getUserId());
                $itemId = $result;
            }
            
            if ($result) {
                $success = true;
                header('Location: warehouse_view.php?id=' . $itemId . '&success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Articolo' : 'Nuovo Articolo';
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
                        <a href="warehouse.php" class="text-decoration-none text-muted">
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
                            <h5 class="mb-0"><i class="bi bi-box-seam"></i> Dati Articolo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="code" class="form-label">Codice</label>
                                    <input type="text" class="form-control" id="code" name="code" 
                                           value="<?php echo htmlspecialchars($item['code'] ?? ''); ?>">
                                    <small class="form-text text-muted">Codice univoco per identificare l'articolo</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Nome Articolo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="description" class="form-label">Descrizione</label>
                                    <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Categoria <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="dpi" <?php echo ($item['category'] ?? '') === 'dpi' ? 'selected' : ''; ?>>DPI (Dispositivi Protezione Individuale)</option>
                                        <option value="attrezzatura" <?php echo ($item['category'] ?? '') === 'attrezzatura' ? 'selected' : ''; ?>>Attrezzatura</option>
                                        <option value="materiale_sanitario" <?php echo ($item['category'] ?? '') === 'materiale_sanitario' ? 'selected' : ''; ?>>Materiale Sanitario</option>
                                        <option value="cancelleria" <?php echo ($item['category'] ?? '') === 'cancelleria' ? 'selected' : ''; ?>>Cancelleria</option>
                                        <option value="consumabili" <?php echo ($item['category'] ?? '') === 'consumabili' ? 'selected' : ''; ?>>Consumabili</option>
                                        <option value="altro" <?php echo ($item['category'] ?? 'altro') === 'altro' ? 'selected' : ''; ?>>Altro</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="unit" class="form-label">Unità di Misura</label>
                                    <input type="text" class="form-control" id="unit" name="unit" 
                                           value="<?php echo htmlspecialchars($item['unit'] ?? ''); ?>"
                                           placeholder="es. pz, kg, lt">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="location" class="form-label">Ubicazione</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>"
                                           placeholder="es. Scaffale A3, Armadio 2">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Giacenza</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="quantity" class="form-label">Quantità Disponibile <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           value="<?php echo htmlspecialchars($item['quantity'] ?? 0); ?>" 
                                           min="0" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="minimum_quantity" class="form-label">Scorta Minima</label>
                                    <input type="number" class="form-control" id="minimum_quantity" name="minimum_quantity" 
                                           value="<?php echo htmlspecialchars($item['minimum_quantity'] ?? 0); ?>" 
                                           min="0">
                                    <small class="form-text text-muted">Alert quando la quantità scende sotto questo valore</small>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label">Stato <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="disponibile" <?php echo ($item['status'] ?? 'disponibile') === 'disponibile' ? 'selected' : ''; ?>>Disponibile</option>
                                        <option value="esaurito" <?php echo ($item['status'] ?? '') === 'esaurito' ? 'selected' : ''; ?>>Esaurito</option>
                                        <option value="in_ordine" <?php echo ($item['status'] ?? '') === 'in_ordine' ? 'selected' : ''; ?>>In Ordine</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note e Opzioni</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="notes" class="form-label">Note</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($item['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="warehouse.php" class="btn btn-secondary">
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
