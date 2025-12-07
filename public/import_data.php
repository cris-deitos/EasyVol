<?php
/**
 * Import CSV Data
 * 
 * Pagina per import dati da CSV con conversione da MONOTABELLA a MULTI-TABELLA
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Controllers\ImportController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato. Necessari permessi di amministratore.');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$importController = new ImportController($db, $config);

$errors = [];
$success = false;
$step = 'upload'; // upload, preview, import, complete
$importData = [];

// Gestione upload e processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        try {
            // Step 1: Upload e Preview
            if (isset($_POST['action']) && $_POST['action'] === 'upload') {
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new \Exception('Errore nel caricamento del file');
                }
                
                $importType = $_POST['import_type'] ?? '';
                if (!in_array($importType, ['soci', 'cadetti', 'mezzi', 'attrezzature'])) {
                    throw new \Exception('Tipo import non valido');
                }
                
                $delimiter = $_POST['delimiter'] ?? ',';
                
                // Salva file temporaneo
                $uploadDir = __DIR__ . '/../uploads/imports';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0750, true);
                }
                
                // Sanitizza nome file (rimuovi caratteri pericolosi e dots per prevenire directory traversal)
                $originalName = $_FILES['csv_file']['name'];
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $baseName = pathinfo($originalName, PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
                // Limita lunghezza e forza estensione .csv
                $safeName = substr($safeName, 0, 50);
                $fileName = 'import_' . time() . '_' . $safeName . '.csv';
                $filePath = $uploadDir . '/' . $fileName;
                
                if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $filePath)) {
                    throw new \Exception('Impossibile salvare il file caricato');
                }
                
                // Parse e preview
                $parseResult = $importController->parseAndPreview($filePath, $delimiter);
                $suggestedMapping = $importController->suggestMapping($parseResult['headers'], $importType);
                
                $importData = [
                    'file_path' => $filePath,
                    'file_name' => $_FILES['csv_file']['name'],
                    'import_type' => $importType,
                    'delimiter' => $delimiter,
                    'headers' => $parseResult['headers'],
                    'preview' => $parseResult['preview'],
                    'total_rows' => $parseResult['total_rows'],
                    'encoding' => $parseResult['encoding'],
                    'suggested_mapping' => $suggestedMapping
                ];
                
                $_SESSION['import_data'] = $importData;
                $step = 'preview';
            }
            
            // Step 2: Conferma mapping e esegui import
            elseif (isset($_POST['action']) && $_POST['action'] === 'import') {
                if (!isset($_SESSION['import_data'])) {
                    throw new \Exception('Dati import non trovati. Ricarica il file.');
                }
                
                $importData = $_SESSION['import_data'];
                
                // Recupera mapping personalizzato
                $columnMapping = [];
                foreach ($importData['headers'] as $header) {
                    $mappingKey = 'mapping_' . md5($header);
                    if (isset($_POST[$mappingKey]) && !empty($_POST[$mappingKey])) {
                        $columnMapping[$header] = $_POST[$mappingKey];
                    }
                }
                
                // Esegui import
                $userId = $app->getUserId();
                $result = $importController->import(
                    $importData['file_path'],
                    $importData['import_type'],
                    $columnMapping,
                    $importData['delimiter'],
                    $userId
                );
                
                $importData['result'] = $result;
                $_SESSION['import_data'] = $importData;
                
                // Rimuovi file temporaneo
                if (file_exists($importData['file_path'])) {
                    unlink($importData['file_path']);
                }
                
                $step = 'complete';
                $success = true;
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
            if (isset($importData['file_path']) && file_exists($importData['file_path'])) {
                unlink($importData['file_path']);
            }
        }
    }
} elseif (isset($_SESSION['import_data']) && isset($_GET['step'])) {
    $importData = $_SESSION['import_data'];
    $step = $_GET['step'];
}

// Reset import
if (isset($_GET['reset'])) {
    unset($_SESSION['import_data']);
    header('Location: import_data.php');
    exit;
}

$pageTitle = 'Import Dati CSV';
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
                        <i class="bi bi-file-earmark-arrow-up"></i>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($step !== 'upload'): ?>
                            <a href="import_data.php?reset=1" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Nuovo Import
                            </a>
                        <?php endif; ?>
                        <a href="settings.php" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Torna a Impostazioni
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Errore!</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success && $step === 'complete'): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <strong>Import completato con successo!</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Progress Steps -->
                <div class="mb-4">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <span class="nav-link <?php echo $step === 'upload' ? 'active' : ($step !== 'upload' ? 'disabled' : ''); ?>">
                                1. Upload CSV
                            </span>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link <?php echo $step === 'preview' ? 'active' : ($step === 'complete' || $step === 'import' ? 'disabled' : ''); ?>">
                                2. Anteprima e Mappatura
                            </span>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link <?php echo $step === 'complete' ? 'active' : ''; ?>">
                                3. Completamento
                            </span>
                        </li>
                    </ul>
                </div>
                
                <?php if ($step === 'upload'): ?>
                    <!-- Step 1: Upload -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Step 1: Carica File CSV</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Sistema di Import CSV</strong><br>
                                Converte dati da struttura MONOTABELLA a struttura MULTI-TABELLA.
                                <ul class="mb-0 mt-2">
                                    <li>Supporta encoding UTF-8 e ISO-8859-1</li>
                                    <li>Rileva automaticamente il formato del CSV</li>
                                    <li>Split automatico dei dati in tabelle correlate</li>
                                    <li>Gestione duplicati e rollback su errore</li>
                                </ul>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <?php echo CsrfProtection::getHiddenField(); ?>
                                <input type="hidden" name="action" value="upload">
                                
                                <div class="mb-3">
                                    <label for="import_type" class="form-label">Tipo Import *</label>
                                    <select class="form-select" id="import_type" name="import_type" required>
                                        <option value="">Seleziona tipo...</option>
                                        <option value="soci">Soci Adulti</option>
                                        <option value="cadetti">Cadetti (Minorenni)</option>
                                        <option value="mezzi">Mezzi e Veicoli</option>
                                        <option value="attrezzature">Attrezzature e Magazzino</option>
                                    </select>
                                    <div class="form-text">Scegli il tipo di dati che stai importando</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">File CSV *</label>
                                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                    <div class="form-text">File CSV con encoding UTF-8 o ISO-8859-1</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="delimiter" class="form-label">Delimitatore</label>
                                    <select class="form-select" id="delimiter" name="delimiter">
                                        <option value=",">Virgola (,)</option>
                                        <option value=";">Punto e virgola (;)</option>
                                        <option value="\t">Tab</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Carica e Analizza
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Info strutture dati -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Struttura Soci Adulti</h6>
                                </div>
                                <div class="card-body">
                                    <small>
                                        <strong>Tabella principale:</strong> members<br>
                                        <strong>Tabelle correlate:</strong>
                                        <ul class="mb-0">
                                            <li>member_contacts (email, telefono, cellulare, pec)</li>
                                            <li>member_addresses (residenza, domicilio)</li>
                                            <li>member_employment (dati lavorativi)</li>
                                        </ul>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Struttura Cadetti</h6>
                                </div>
                                <div class="card-body">
                                    <small>
                                        <strong>Tabella principale:</strong> junior_members<br>
                                        <strong>Tabelle correlate:</strong>
                                        <ul class="mb-0">
                                            <li>junior_member_contacts</li>
                                            <li>junior_member_addresses</li>
                                            <li>junior_member_guardians (genitori/tutori)</li>
                                        </ul>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($step === 'preview'): ?>
                    <!-- Step 2: Preview e Mapping -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Step 2: Verifica e Mappa Colonne</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>File:</strong> <?php echo htmlspecialchars($importData['file_name']); ?><br>
                                <strong>Tipo:</strong> <?php echo htmlspecialchars(ucfirst($importData['import_type'])); ?><br>
                                <strong>Encoding:</strong> <?php echo htmlspecialchars($importData['encoding']); ?><br>
                                <strong>Totale righe:</strong> <?php echo number_format($importData['total_rows']); ?>
                            </div>
                            
                            <h6 class="mt-4">Anteprima Dati (prime 10 righe)</h6>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <?php foreach ($importData['headers'] as $header): ?>
                                                <th><?php echo htmlspecialchars($header); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($importData['preview'] as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $cell): ?>
                                                    <td><?php echo htmlspecialchars($cell); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <h6 class="mt-4">Mappatura Colonne</h6>
                            <p class="text-muted">Mappa le colonne del CSV ai campi del database. La mappatura automatica è già suggerita.</p>
                            
                            <form method="POST">
                                <?php echo CsrfProtection::getHiddenField(); ?>
                                <input type="hidden" name="action" value="import">
                                
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Colonna CSV</th>
                                                <th>Campo Database</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($importData['headers'] as $header): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($header); ?></strong></td>
                                                    <td>
                                                        <input type="text" 
                                                               class="form-control form-control-sm" 
                                                               name="mapping_<?php echo md5($header); ?>" 
                                                               value="<?php echo htmlspecialchars($importData['suggested_mapping'][$header] ?? ''); ?>"
                                                               placeholder="Lascia vuoto per ignorare">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Conferma e Importa
                                    </button>
                                    <a href="import_data.php?reset=1" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Annulla
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php elseif ($step === 'complete'): ?>
                    <!-- Step 3: Risultati -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Step 3: Import Completato</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($importData['result'])): 
                                $result = $importData['result'];
                            ?>
                                <div class="row text-center mb-4">
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body">
                                                <h3><?php echo $result['imported']; ?></h3>
                                                <p class="mb-0">Importati</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-warning text-white">
                                            <div class="card-body">
                                                <h3><?php echo $result['skipped']; ?></h3>
                                                <p class="mb-0">Saltati</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-danger text-white">
                                            <div class="card-body">
                                                <h3><?php echo $result['errors']; ?></h3>
                                                <p class="mb-0">Errori</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body">
                                                <h3><?php echo $importData['total_rows']; ?></h3>
                                                <p class="mb-0">Totale Righe</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($result['details'])): ?>
                                    <h6>Dettagli Import</h6>
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-sm table-striped">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>Riga</th>
                                                    <th>Stato</th>
                                                    <th>Dettaglio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($result['details'] as $detail): ?>
                                                    <tr>
                                                        <td><?php echo $detail['row']; ?></td>
                                                        <td>
                                                            <?php if ($detail['status'] === 'success'): ?>
                                                                <span class="badge bg-success">Importato</span>
                                                            <?php elseif ($detail['status'] === 'skipped'): ?>
                                                                <span class="badge bg-warning">Saltato</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Errore</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if (isset($detail['id'])) {
                                                                echo "ID: " . $detail['id'];
                                                            } elseif (isset($detail['reason'])) {
                                                                echo htmlspecialchars($detail['reason']);
                                                            } elseif (isset($detail['error'])) {
                                                                echo htmlspecialchars($detail['error']);
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <a href="import_data.php?reset=1" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise"></i> Nuovo Import
                                    </a>
                                    <?php
                                    $viewUrl = '';
                                    switch ($importData['import_type']) {
                                        case 'soci':
                                            $viewUrl = 'members.php';
                                            break;
                                        case 'cadetti':
                                            $viewUrl = 'junior_members.php';
                                            break;
                                        case 'mezzi':
                                            $viewUrl = 'vehicles.php';
                                            break;
                                        case 'attrezzature':
                                            $viewUrl = 'warehouse.php';
                                            break;
                                    }
                                    if ($viewUrl):
                                    ?>
                                        <a href="<?php echo $viewUrl; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i> Visualizza Dati Importati
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
