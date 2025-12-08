<?php
/**
 * Impostazioni Sistema
 * 
 * Pagina per gestire le impostazioni dell'applicazione
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\FileUploader;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('settings', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();

$errors = [];
$success = false;
$successMessage = '';

// Gestione salvataggio impostazioni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $app->checkPermission('settings', 'edit')) {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $formType = $_POST['form_type'] ?? '';
        
        if ($formType === 'association') {
            // Handle association data update
            try {
                $associationData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'address_street' => trim($_POST['address_street'] ?? ''),
                    'address_number' => trim($_POST['address_number'] ?? ''),
                    'address_city' => trim($_POST['address_city'] ?? ''),
                    'address_province' => trim($_POST['address_province'] ?? ''),
                    'address_cap' => trim($_POST['address_cap'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'pec' => trim($_POST['pec'] ?? ''),
                    'tax_code' => trim($_POST['tax_code'] ?? ''),
                ];
                
                // Handle logo upload
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    // Allowed MIME types for logo
                    $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/svg+xml'];
                    
                    // Determine file extension from MIME type for consistent naming
                    try {
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->file($_FILES['logo']['tmp_name']);
                    } catch (\Exception $e) {
                        $errors[] = 'Errore durante la verifica del tipo di file';
                        $mimeType = null;
                    }
                    
                    // Only allow specific MIME types, reject unknown
                    $ext = null;
                    if ($mimeType !== null) {
                        $ext = match($mimeType) {
                            'image/png' => 'png',
                            'image/jpeg' => 'jpg',
                            'image/svg+xml' => 'svg',
                            default => null
                        };
                    }
                    
                    if ($ext === null) {
                        $errors[] = 'Tipo di file non valido. Sono ammessi solo PNG, JPEG, SVG';
                    } else {
                        $newFileName = 'logo_associazione.' . $ext;
                        
                        // Use FileUploader for consistent and secure upload
                        $uploader = new FileUploader(__DIR__ . '/../uploads/logo/', $allowedMimeTypes, 5 * 1024 * 1024);
                        $uploadResult = $uploader->upload($_FILES['logo'], '', $newFileName);
                        
                        if ($uploadResult['success']) {
                            // Delete old logo files only after successful upload
                            $uploadDir = __DIR__ . '/../uploads/logo/';
                            $oldFiles = glob($uploadDir . 'logo_associazione.*');
                            $newFilePath = realpath($uploadResult['path']);
                            
                            // Only proceed if realpath succeeded
                            if ($newFilePath !== false) {
                                foreach ($oldFiles as $oldFile) {
                                    $oldFilePath = realpath($oldFile);
                                    // Don't delete the file we just uploaded
                                    if ($oldFilePath !== false && file_exists($oldFile) && $oldFilePath !== $newFilePath) {
                                        unlink($oldFile);
                                    }
                                }
                            }
                            
                            $associationData['logo'] = 'uploads/logo/' . $newFileName;
                        } else {
                            $errors[] = 'Errore upload logo: ' . $uploadResult['error'];
                        }
                    }
                } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = 'Errore durante l\'upload del file';
                }
                
                if (empty($errors)) {
                    // Whitelist of allowed columns to prevent SQL injection
                    $allowedColumns = [
                        'name', 'logo', 'address_street', 'address_number', 
                        'address_city', 'address_province', 'address_cap', 
                        'email', 'pec', 'tax_code'
                    ];
                    
                    // Filter associationData to only include whitelisted columns
                    $safeData = array_filter(
                        $associationData,
                        fn($key) => in_array($key, $allowedColumns),
                        ARRAY_FILTER_USE_KEY
                    );
                    
                    // Check if association record exists
                    $existingAssociation = $db->fetchOne("SELECT id FROM association LIMIT 1");
                    
                    if ($existingAssociation) {
                        // Update existing record
                        $setParts = [];
                        $params = [];
                        foreach ($safeData as $key => $value) {
                            $setParts[] = "$key = ?";
                            $params[] = $value;
                        }
                        $params[] = $existingAssociation['id'];
                        
                        $sql = "UPDATE association SET " . implode(', ', $setParts) . " WHERE id = ?";
                        $db->execute($sql, $params);
                    } else {
                        // Insert new record
                        $columns = implode(', ', array_keys($safeData));
                        $placeholders = implode(', ', array_fill(0, count($safeData), '?'));
                        $sql = "INSERT INTO association ($columns) VALUES ($placeholders)";
                        $db->execute($sql, array_values($safeData));
                    }
                    
                    $success = true;
                    $successMessage = 'Dati associazione salvati con successo!';
                    
                    // Reload config to show updated data
                    header('Location: settings.php?success=association');
                    exit;
                }
            } catch (\Exception $e) {
                $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
            }
        } elseif ($formType === 'email') {
            // Handle email settings update
            try {
                $fromAddress = trim($_POST['from_address'] ?? '');
                $fromName = trim($_POST['from_name'] ?? '');
                $replyTo = trim($_POST['reply_to'] ?? '');
                $returnPath = trim($_POST['return_path'] ?? '');
                
                if (empty($fromAddress) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Indirizzo email mittente non valido';
                }
                if (!empty($replyTo) && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Indirizzo per risposte non valido';
                }
                if (!empty($returnPath) && !filter_var($returnPath, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Return-Path non valido';
                }
                
                if (empty($errors)) {
                    $configPath = __DIR__ . '/../config/config.php';
                    
                    // Check if config file exists
                    if (!file_exists($configPath)) {
                        $errors[] = 'File di configurazione non trovato';
                    } else {
                        $content = file_get_contents($configPath);
                        
                        // Update email settings
                        $content = preg_replace("/'from_address'\s*=>\s*'[^']*'/", "'from_address' => '" . addslashes($fromAddress) . "'", $content);
                        $content = preg_replace("/'from_name'\s*=>\s*'[^']*'/", "'from_name' => '" . addslashes($fromName) . "'", $content);
                        $content = preg_replace("/'reply_to'\s*=>\s*'[^']*'/", "'reply_to' => '" . addslashes($replyTo) . "'", $content);
                        $content = preg_replace("/'return_path'\s*=>\s*'[^']*'/", "'return_path' => '" . addslashes($returnPath) . "'", $content);
                        
                        file_put_contents($configPath, $content);
                        
                        $success = true;
                        $successMessage = 'Impostazioni email aggiornate con successo!';
                        
                        // Reload config
                        header('Location: settings.php?success=email');
                        exit;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
            }
        }
    }
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success = true;
    if ($_GET['success'] === 'association') {
        $successMessage = 'Dati associazione salvati con successo!';
    } elseif ($_GET['success'] === 'email') {
        $successMessage = 'Impostazioni email aggiornate con successo!';
    }
}

$pageTitle = 'Impostazioni Sistema';
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
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($successMessage ?: 'Impostazioni salvate con successo!'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                            <i class="bi bi-gear"></i> Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="association-tab" data-bs-toggle="tab" data-bs-target="#association" type="button" role="tab">
                            <i class="bi bi-building"></i> Associazione
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
                            <i class="bi bi-envelope"></i> Email
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">
                            <i class="bi bi-archive"></i> Backup
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab">
                            <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- Generali -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Impostazioni Generali</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                    
                                    <div class="mb-3">
                                        <label for="app_name" class="form-label">Nome Applicazione</label>
                                        <input type="text" class="form-control" id="app_name" name="app_name" 
                                               value="<?php echo htmlspecialchars($config['app']['name'] ?? 'EasyVol'); ?>" 
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone" 
                                                <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                            <option value="Europe/Rome" <?php echo ($config['app']['timezone'] ?? '') === 'Europe/Rome' ? 'selected' : ''; ?>>Europe/Rome</option>
                                            <option value="Europe/London" <?php echo ($config['app']['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($app->checkPermission('settings', 'edit')): ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva Modifiche
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Associazione -->
                    <div class="tab-pane fade" id="association" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Dati Associazione</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Load current association data from database
                                $associationData = $db->fetchOne("SELECT * FROM association ORDER BY id ASC LIMIT 1");
                                if (!$associationData) {
                                    $associationData = [];
                                }
                                ?>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                    <input type="hidden" name="form_type" value="association">
                                    
                                    <!-- Logo Upload -->
                                    <div class="mb-3">
                                        <label for="logo" class="form-label">Logo Associazione</label>
                                        <?php 
                                        // Validate logo path for security
                                        $logoPath = $associationData['logo'] ?? '';
                                        $showLogo = false;
                                        $logoUrl = '';
                                        if (!empty($logoPath) && str_starts_with($logoPath, 'uploads/logo/')) {
                                            $fullPath = __DIR__ . '/../' . $logoPath;
                                            if (file_exists($fullPath)) {
                                                // Use realpath to resolve any path manipulation attempts
                                                $realPath = realpath($fullPath);
                                                $expectedDir = realpath(__DIR__ . '/../uploads/logo/');
                                                // Verify the file is actually in the uploads/logo directory
                                                // Add trailing slash to prevent partial directory name matches
                                                if ($realPath && $expectedDir && str_starts_with($realPath, $expectedDir . DIRECTORY_SEPARATOR)) {
                                                    $showLogo = true;
                                                    // Use relative path that works regardless of installation directory
                                                    // Since this page is in /public/, the logo in /uploads/ is one level up
                                                    $logoUrl = '../' . $logoPath;
                                                }
                                            }
                                        }
                                        if ($showLogo):
                                        ?>
                                            <div class="mb-2">
                                                <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                                                     alt="Logo Associazione" 
                                                     style="max-height: 150px; border: 1px solid #ddd; padding: 5px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="logo" name="logo" 
                                               accept="image/png,image/jpeg,image/svg+xml"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                        <small class="text-muted">Formati consentiti: PNG, JPEG, SVG. Dimensione massima: 5MB</small>
                                    </div>
                                    
                                    <!-- Association Name -->
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome Associazione</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($associationData['name'] ?? ''); ?>"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <!-- Address -->
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label for="address_street" class="form-label">Via</label>
                                            <input type="text" class="form-control" id="address_street" name="address_street" 
                                                   value="<?php echo htmlspecialchars($associationData['address_street'] ?? ''); ?>"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="address_number" class="form-label">Numero</label>
                                            <input type="text" class="form-control" id="address_number" name="address_number" 
                                                   value="<?php echo htmlspecialchars($associationData['address_number'] ?? ''); ?>"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="address_city" class="form-label">Città</label>
                                            <input type="text" class="form-control" id="address_city" name="address_city" 
                                                   value="<?php echo htmlspecialchars($associationData['address_city'] ?? ''); ?>"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="address_province" class="form-label">Provincia</label>
                                            <input type="text" class="form-control" id="address_province" name="address_province" 
                                                   value="<?php echo htmlspecialchars($associationData['address_province'] ?? ''); ?>"
                                                   maxlength="5"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="address_cap" class="form-label">CAP</label>
                                            <input type="text" class="form-control" id="address_cap" name="address_cap" 
                                                   value="<?php echo htmlspecialchars($associationData['address_cap'] ?? ''); ?>"
                                                   maxlength="10"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    
                                    <!-- Email -->
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($associationData['email'] ?? ''); ?>"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <!-- PEC -->
                                    <div class="mb-3">
                                        <label for="pec" class="form-label">PEC</label>
                                        <input type="email" class="form-control" id="pec" name="pec" 
                                               value="<?php echo htmlspecialchars($associationData['pec'] ?? ''); ?>"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <!-- Tax Code -->
                                    <div class="mb-3">
                                        <label for="tax_code" class="form-label">Codice Fiscale</label>
                                        <input type="text" class="form-control" id="tax_code" name="tax_code" 
                                               value="<?php echo htmlspecialchars($associationData['tax_code'] ?? ''); ?>"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <?php if ($app->checkPermission('settings', 'edit')): ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva Modifiche
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Configurazione Email</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Stato Email: 
                                    <?php if ($config['email']['enabled'] ?? false): ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Disattivo</span>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST">
                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                    <input type="hidden" name="form_type" value="email">
                                    
                                    <div class="mb-3">
                                        <label for="from_address" class="form-label">Indirizzo Email Mittente *</label>
                                        <input type="email" class="form-control" id="from_address" name="from_address" 
                                               value="<?php echo htmlspecialchars($config['email']['from_address'] ?? ''); ?>" 
                                               required
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="from_name" class="form-label">Nome Mittente *</label>
                                        <input type="text" class="form-control" id="from_name" name="from_name" 
                                               value="<?php echo htmlspecialchars($config['email']['from_name'] ?? ''); ?>" 
                                               required
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="reply_to" class="form-label">Indirizzo per Risposte</label>
                                        <input type="email" class="form-control" id="reply_to" name="reply_to" 
                                               value="<?php echo htmlspecialchars($config['email']['reply_to'] ?? ''); ?>"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="return_path" class="form-label">Return-Path</label>
                                        <input type="email" class="form-control" id="return_path" name="return_path" 
                                               value="<?php echo htmlspecialchars($config['email']['return_path'] ?? ''); ?>"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <?php if ($app->checkPermission('settings', 'edit')): ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva Modifiche
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Backup -->
                    <div class="tab-pane fade" id="backup" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Backup e Manutenzione</h5>
                            </div>
                            <div class="card-body">
                                <h6>Backup Automatici</h6>
                                <p>I backup automatici del database vengono eseguiti giornalmente tramite cron job.</p>
                                
                                <?php
                                $backupDir = __DIR__ . '/../backups';
                                if (is_dir($backupDir)) {
                                    $backups = glob($backupDir . '/backup_*.sql.gz');
                                    rsort($backups);
                                    $backups = array_slice($backups, 0, 10);
                                    
                                    if (!empty($backups)) {
                                        echo '<h6 class="mt-4">Ultimi Backup</h6>';
                                        echo '<div class="list-group">';
                                        foreach ($backups as $backup) {
                                            $filename = basename($backup);
                                            $size = filesize($backup);
                                            $date = filemtime($backup);
                                            echo '<div class="list-group-item">';
                                            echo '<div class="d-flex w-100 justify-content-between">';
                                            echo '<h6 class="mb-1">' . htmlspecialchars($filename) . '</h6>';
                                            echo '<small>' . round($size / 1024 / 1024, 2) . ' MB</small>';
                                            echo '</div>';
                                            echo '<small class="text-muted">' . date('d/m/Y H:i:s', $date) . '</small>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<p class="text-muted">Nessun backup disponibile</p>';
                                    }
                                } else {
                                    echo '<p class="text-muted">Directory backup non trovata</p>';
                                }
                                ?>
                                
                                <div class="mt-4">
                                    <h6>Cron Jobs</h6>
                                    <p>Per configurare i cron jobs automatici, consulta <code>cron/README.md</code></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Import CSV -->
                    <div class="tab-pane fade" id="import" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Import Dati da CSV</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Sistema di Import CSV Avanzato</strong><br>
                                    Importa dati da file CSV con conversione automatica da struttura MONOTABELLA a MULTI-TABELLA.
                                </div>
                                
                                <h6>Funzionalità</h6>
                                <ul>
                                    <li><i class="bi bi-check-circle text-success"></i> Upload CSV con encoding detection (UTF-8/ISO-8859-1)</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Supporto per Soci, Cadetti, Mezzi e Attrezzature</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Mappatura intelligente delle colonne</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Anteprima dati prima dell'import</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Split automatico in tabelle correlate</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Gestione contatti multipli (email, telefono, cellulare, PEC)</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Gestione indirizzi multipli (residenza, domicilio)</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Rilevamento duplicati via matricola/targa/codice</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Transazioni con rollback automatico su errore</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Log dettagliato di ogni import</li>
                                </ul>
                                
                                <h6 class="mt-4">Tipi di Import Supportati</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6><i class="bi bi-people"></i> Soci Adulti</h6>
                                                <small class="text-muted">
                                                    Import in: members, member_contacts, member_addresses, member_employment
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6><i class="bi bi-person"></i> Cadetti (Minorenni)</h6>
                                                <small class="text-muted">
                                                    Import in: junior_members, junior_member_contacts, junior_member_addresses, junior_member_guardians
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6><i class="bi bi-truck"></i> Mezzi e Veicoli</h6>
                                                <small class="text-muted">
                                                    Import in: vehicles, vehicle_maintenance
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6><i class="bi bi-box"></i> Attrezzature e Magazzino</h6>
                                                <small class="text-muted">
                                                    Import in: warehouse_items, warehouse_movements
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="import_data.php" class="btn btn-primary">
                                        <i class="bi bi-file-earmark-arrow-up"></i> Vai a Import CSV
                                    </a>
                                </div>
                                
                                <?php
                                // Mostra ultimi import se esiste la tabella
                                try {
                                    $stmt = $db->getConnection()->prepare(
                                        "SELECT * FROM import_logs ORDER BY started_at DESC LIMIT 5"
                                    );
                                    $stmt->execute();
                                    $recentImports = $stmt->fetchAll();
                                    
                                    if (!empty($recentImports)) {
                                        echo '<h6 class="mt-4">Ultimi Import</h6>';
                                        echo '<div class="table-responsive">';
                                        echo '<table class="table table-sm">';
                                        echo '<thead><tr><th>Data</th><th>Tipo</th><th>File</th><th>Righe</th><th>Stato</th></tr></thead>';
                                        echo '<tbody>';
                                        foreach ($recentImports as $import) {
                                            echo '<tr>';
                                            echo '<td>' . date('d/m/Y H:i', strtotime($import['started_at'])) . '</td>';
                                            echo '<td>' . htmlspecialchars(ucfirst($import['import_type'])) . '</td>';
                                            echo '<td>' . htmlspecialchars($import['file_name']) . '</td>';
                                            echo '<td>' . ($import['imported_rows'] ?? 0) . '/' . ($import['total_rows'] ?? 0) . '</td>';
                                            echo '<td>';
                                            $statusClass = [
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                'partial' => 'warning',
                                                'in_progress' => 'info'
                                            ];
                                            $class = $statusClass[$import['status']] ?? 'secondary';
                                            echo '<span class="badge bg-' . $class . '">' . htmlspecialchars($import['status']) . '</span>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                        echo '</tbody></table></div>';
                                    }
                                } catch (\Exception $e) {
                                    // Tabella non esiste ancora, ignorare
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
