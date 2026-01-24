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
use EasyVol\Controllers\PrintTemplateController;

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
                    'phone' => trim($_POST['phone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'pec' => trim($_POST['pec'] ?? ''),
                    'tax_code' => trim($_POST['tax_code'] ?? ''),
                    'provincial_civil_protection_email' => trim($_POST['provincial_civil_protection_email'] ?? ''),
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
                        'phone', 'email', 'pec', 'tax_code', 'provincial_civil_protection_email'
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
            // Handle email settings update - save to database instead of config file
            try {
                // Basic email settings
                $emailEnabled = isset($_POST['email_enabled']) ? '1' : '0';
                $emailMethod = trim($_POST['email_method'] ?? 'smtp');
                $fromAddress = trim($_POST['from_address'] ?? '');
                $fromName = trim($_POST['from_name'] ?? '');
                $replyTo = trim($_POST['reply_to'] ?? '');
                $returnPath = trim($_POST['return_path'] ?? '');
                $charset = trim($_POST['charset'] ?? 'UTF-8');
                $baseUrl = trim($_POST['base_url'] ?? '');
                
                // SMTP settings
                $smtpHost = trim($_POST['smtp_host'] ?? '');
                $smtpPort = intval($_POST['smtp_port'] ?? 587);
                $smtpUsername = trim($_POST['smtp_username'] ?? '');
                $smtpPassword = $_POST['smtp_password'] ?? ''; // Don't trim password
                $smtpEncryption = trim($_POST['smtp_encryption'] ?? 'tls');
                $smtpAuth = isset($_POST['smtp_auth']) ? '1' : '0';
                $smtpDebug = isset($_POST['smtp_debug']) ? '1' : '0';
                
                // Vehicle movement alert emails
                $vehicleMovementAlertEmails = trim($_POST['vehicle_movement_alert_emails'] ?? '');
                
                // Validate required fields
                if (empty($fromAddress) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Indirizzo email mittente non valido';
                }
                if (!empty($replyTo) && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Indirizzo per risposte non valido';
                }
                if (!empty($returnPath) && !filter_var($returnPath, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Return-Path non valido';
                }
                
                // Validate email method
                if (!in_array($emailMethod, ['smtp', 'sendmail'])) {
                    $errors[] = 'Metodo di invio email non valido';
                }
                
                // Validate SMTP encryption
                if (!in_array($smtpEncryption, ['tls', 'ssl', ''])) {
                    $errors[] = 'Tipo di crittografia SMTP non valido';
                }
                
                // Validate SMTP port
                if ($smtpPort < 1 || $smtpPort > 65535) {
                    $errors[] = 'Porta SMTP non valida (1-65535)';
                }
                
                // Validate charset
                if (!in_array($charset, ['UTF-8', 'ISO-8859-1', 'ISO-8859-15'])) {
                    $errors[] = 'Charset non valido';
                }
                
                // Validate base URL
                if (!empty($baseUrl) && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                    $errors[] = 'URL di base non valido';
                }
                
                // Validate vehicle movement alert emails
                if (!empty($vehicleMovementAlertEmails)) {
                    $emails = array_map('trim', explode(',', $vehicleMovementAlertEmails));
                    foreach ($emails as $email) {
                        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = 'Indirizzo email non valido per alert movimentazione: ' . $email;
                        }
                    }
                }
                
                if (empty($errors)) {
                    // Save email configuration to database in a transaction
                    $emailSettings = [
                        'email_enabled' => $emailEnabled,
                        'email_method' => $emailMethod,
                        'email_from_address' => $fromAddress,
                        'email_from_name' => $fromName,
                        'email_reply_to' => $replyTo,
                        'email_return_path' => $returnPath,
                        'email_charset' => $charset,
                        'email_base_url' => $baseUrl,
                        'email_smtp_host' => $smtpHost,
                        'email_smtp_port' => (string)$smtpPort,
                        'email_smtp_username' => $smtpUsername,
                        'email_smtp_password' => $smtpPassword,
                        'email_smtp_encryption' => $smtpEncryption,
                        'email_smtp_auth' => $smtpAuth,
                        'email_smtp_debug' => $smtpDebug,
                        'vehicle_movement_alert_emails' => $vehicleMovementAlertEmails,
                    ];
                    
                    // Use transaction for atomicity and better performance
                    $db->getConnection()->beginTransaction();
                    try {
                        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert
                        foreach ($emailSettings as $key => $value) {
                            $sql = "INSERT INTO config (config_key, config_value) 
                                    VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)";
                            $db->execute($sql, [$key, $value]);
                        }
                        $db->getConnection()->commit();
                        
                        $success = true;
                        $successMessage = 'Impostazioni email aggiornate con successo!';
                        
                        // Reload page to show updated config
                        header('Location: settings.php?success=email');
                        exit;
                    } catch (\Exception $e) {
                        $db->getConnection()->rollBack();
                        throw $e;
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
                        <button class="nav-link" id="mail-tab" data-bs-toggle="tab" data-bs-target="#mail" type="button" role="tab">
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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="print-templates-tab" data-bs-toggle="tab" data-bs-target="#print-templates" type="button" role="tab">
                            <i class="bi bi-printer"></i> Modelli di Stampa
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="telegram-tab" data-bs-toggle="tab" data-bs-target="#telegram" type="button" role="tab">
                            <i class="bi bi-telegram"></i> Telegram
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="qualifications-tab" data-bs-toggle="tab" data-bs-target="#qualifications" type="button" role="tab">
                            <i class="bi bi-award"></i> Mansioni Soci
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="course-types-tab" data-bs-toggle="tab" data-bs-target="#course-types" type="button" role="tab">
                            <i class="bi bi-book"></i> Tipi Corsi
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
                                    
                                    <!-- Phone -->
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Telefono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($associationData['phone'] ?? ''); ?>"
                                               placeholder="es. +39 030 1234567"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
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
                                    
                                    <!-- Provincial Civil Protection Email -->
                                    <div class="mb-3">
                                        <label for="provincial_civil_protection_email" class="form-label">
                                            <i class="bi bi-shield-check"></i> Email Ufficio di Protezione Civile della Provincia
                                        </label>
                                        <input type="email" class="form-control" id="provincial_civil_protection_email" name="provincial_civil_protection_email" 
                                               value="<?php echo htmlspecialchars($associationData['provincial_civil_protection_email'] ?? ''); ?>"
                                               placeholder="es. provincia@protezionecivile.it"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        <small class="text-muted">Email per le notifiche degli eventi all'Ufficio di Protezione Civile della Provincia</small>
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
                    <div class="tab-pane fade" id="mail" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-envelope-fill me-2"></i>Configurazione Email (PHPMailer/SMTP)</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Stato Email: 
                                    <?php if ($config['email']['enabled'] ?? false): ?>
                                        <span class="badge bg-success">Attivo</span>
                                        <?php 
                                        $method = $config['email']['method'] ?? 'smtp';
                                        echo ' - Metodo: <span class="badge bg-primary">' . htmlspecialchars(strtoupper($method)) . '</span>';
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Disattivo</span>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST">
                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                    <input type="hidden" name="form_type" value="email">
                                    
                                    <!-- Email Enable/Disable -->
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" value="1"
                                                   <?php echo ($config['email']['enabled'] ?? false) ? 'checked' : ''; ?>
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                            <label class="form-check-label" for="email_enabled">
                                                <strong>Abilita Invio Email</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Attiva/disattiva l'invio di email dal sistema (scadenze, iscrizioni, quote, notifiche utenti)</small>
                                    </div>
                                    
                                    <hr class="my-4">
                                    <h6><i class="bi bi-person-lines-fill me-2"></i>Informazioni Mittente</h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="from_address" class="form-label">Indirizzo Email Mittente *</label>
                                            <input type="email" class="form-control" id="from_address" name="from_address" 
                                                   value="<?php echo htmlspecialchars($config['email']['from_address'] ?? ''); ?>" 
                                                   required
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="from_name" class="form-label">Nome Mittente *</label>
                                            <input type="text" class="form-control" id="from_name" name="from_name" 
                                                   value="<?php echo htmlspecialchars($config['email']['from_name'] ?? 'EasyVol'); ?>" 
                                                   required
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="reply_to" class="form-label">Indirizzo per Risposte (Reply-To)</label>
                                            <input type="email" class="form-control" id="reply_to" name="reply_to" 
                                                   value="<?php echo htmlspecialchars($config['email']['reply_to'] ?? ''); ?>"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                            <small class="text-muted">Indirizzo email a cui verranno inviate le risposte</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="return_path" class="form-label">Return-Path (Bounce)</label>
                                            <input type="email" class="form-control" id="return_path" name="return_path" 
                                                   value="<?php echo htmlspecialchars($config['email']['return_path'] ?? ''); ?>"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                            <small class="text-muted">Indirizzo per email non consegnate</small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email_base_url" class="form-label">URL di Base per Link nelle Email</label>
                                        <input type="url" class="form-control" id="email_base_url" name="base_url" 
                                               value="<?php echo htmlspecialchars($config['email']['base_url'] ?? ''); ?>"
                                               placeholder="es: https://sdi.protezionecivilebassogarda.it/EasyVol"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        <small class="text-muted">URL di partenza del gestionale per comporre i link nelle email (es: moduli PDF, login). Il sistema aggiungerà automaticamente /public/...</small>
                                    </div>
                                    
                                    <hr class="my-4">
                                    <h6><i class="bi bi-gear-fill me-2"></i>Metodo di Invio</h6>
                                    
                                    <div class="mb-3">
                                        <label for="email_method" class="form-label">Metodo di Invio Email</label>
                                        <select class="form-select" id="email_method" name="email_method"
                                                <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                            <option value="smtp" <?php echo ($config['email']['method'] ?? 'smtp') === 'smtp' ? 'selected' : ''; ?>>SMTP (Consigliato)</option>
                                            <option value="sendmail" <?php echo ($config['email']['method'] ?? '') === 'sendmail' ? 'selected' : ''; ?>>Sendmail (mail() PHP)</option>
                                        </select>
                                        <small class="text-muted">SMTP è raccomandato per maggiore affidabilità e compatibilità</small>
                                    </div>
                                    
                                    <hr class="my-4">
                                    <h6><i class="bi bi-hdd-network-fill me-2"></i>Configurazione Server SMTP</h6>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <strong>Importante:</strong> Configura questi parametri per inviare email tramite il tuo server SMTP (Gmail, Outlook, server personale, ecc.)
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label for="smtp_host" class="form-label">Host SMTP *</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($config['email']['smtp_host'] ?? ''); ?>"
                                                   placeholder="es: smtp.gmail.com, smtp.office365.com"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                            <small class="text-muted">Indirizzo del server SMTP</small>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="smtp_port" class="form-label">Porta SMTP *</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars($config['email']['smtp_port'] ?? '587'); ?>"
                                                   min="1" max="65535"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                            <small class="text-muted">587 (TLS) o 465 (SSL)</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_username" class="form-label">Username SMTP</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($config['email']['smtp_username'] ?? ''); ?>"
                                                   placeholder="es: tuoemail@gmail.com"
                                                   autocomplete="off"
                                                   <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                            <small class="text-muted">Solitamente il tuo indirizzo email</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_password" class="form-label">Password SMTP</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                       value="<?php echo htmlspecialchars($config['email']['smtp_password'] ?? ''); ?>"
                                                       placeholder="Password o App Password"
                                                       autocomplete="new-password"
                                                       <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" onclick="togglePasswordVisibility()">
                                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">Per Gmail usa "App Password" (non la password normale)</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_encryption" class="form-label">Crittografia</label>
                                            <select class="form-select" id="smtp_encryption" name="smtp_encryption"
                                                    <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                                <option value="tls" <?php echo ($config['email']['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Raccomandato - Porta 587)</option>
                                                <option value="ssl" <?php echo ($config['email']['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL (Porta 465)</option>
                                                <option value="" <?php echo ($config['email']['smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>Nessuna (non sicuro)</option>
                                            </select>
                                            <small class="text-muted">Tipo di crittografia per la connessione SMTP</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" id="smtp_auth" name="smtp_auth" value="1"
                                                       <?php echo ($config['email']['smtp_auth'] ?? true) ? 'checked' : ''; ?>
                                                       <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="smtp_auth">
                                                    Richiedi Autenticazione SMTP
                                                </label>
                                            </div>
                                            <small class="text-muted">La maggior parte dei server SMTP richiede autenticazione</small>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    <h6><i class="bi bi-sliders me-2"></i>Opzioni Avanzate</h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="charset" class="form-label">Charset</label>
                                            <select class="form-select" id="charset" name="charset"
                                                    <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                                <option value="UTF-8" <?php echo ($config['email']['charset'] ?? 'UTF-8') === 'UTF-8' ? 'selected' : ''; ?>>UTF-8 (Raccomandato)</option>
                                                <option value="ISO-8859-1" <?php echo ($config['email']['charset'] ?? '') === 'ISO-8859-1' ? 'selected' : ''; ?>>ISO-8859-1</option>
                                                <option value="ISO-8859-15" <?php echo ($config['email']['charset'] ?? '') === 'ISO-8859-15' ? 'selected' : ''; ?>>ISO-8859-15</option>
                                            </select>
                                            <small class="text-muted">Codifica caratteri delle email</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" id="smtp_debug" name="smtp_debug" value="1"
                                                       <?php echo ($config['email']['smtp_debug'] ?? false) ? 'checked' : ''; ?>
                                                       <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="smtp_debug">
                                                    Abilita Debug SMTP (solo per diagnostica)
                                                </label>
                                            </div>
                                            <small class="text-muted">Abilita log dettagliati per risolvere problemi</small>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    <h6><i class="bi bi-truck me-2"></i>Notifiche Movimentazione Mezzi</h6>
                                    
                                    <div class="mb-3">
                                        <label for="vehicle_movement_alert_emails" class="form-label">Email per Alert Movimentazione Mezzi</label>
                                        <input type="text" class="form-control" id="vehicle_movement_alert_emails" name="vehicle_movement_alert_emails" 
                                               value="<?php 
                                               $vehicleEmails = $db->fetchOne("SELECT config_value FROM config WHERE config_key = 'vehicle_movement_alert_emails'");
                                               echo htmlspecialchars($vehicleEmails['config_value'] ?? ''); 
                                               ?>"
                                               placeholder="email1@example.com, email2@example.com"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        <small class="text-muted">Indirizzi email separati da virgola che riceveranno le notifiche di anomalie durante la movimentazione dei mezzi</small>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <?php if ($app->checkPermission('settings', 'edit')): ?>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save"></i> Salva Configurazione Email
                                            </button>
                                            <a href="test_sendmail.php" class="btn btn-outline-secondary" target="_blank">
                                                <i class="bi bi-send"></i> Invia Email di Test
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </form>
                                
                                <hr class="my-4">
                                <h6><i class="bi bi-lightbulb me-2"></i>Configurazioni SMTP Comuni</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Provider</th>
                                                <th>Host SMTP</th>
                                                <th>Porta</th>
                                                <th>Crittografia</th>
                                                <th>Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>Gmail</strong></td>
                                                <td>smtp.gmail.com</td>
                                                <td>587</td>
                                                <td>TLS</td>
                                                <td>Richiede "App Password" (2FA abilitato)</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Outlook/Office365</strong></td>
                                                <td>smtp.office365.com</td>
                                                <td>587</td>
                                                <td>TLS</td>
                                                <td>Usa email e password account</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Yahoo</strong></td>
                                                <td>smtp.mail.yahoo.com</td>
                                                <td>465</td>
                                                <td>SSL</td>
                                                <td>Richiede "App Password"</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Aruba PEC</strong></td>
                                                <td>smtps.pec.aruba.it</td>
                                                <td>465</td>
                                                <td>SSL</td>
                                                <td>Per email PEC certificate</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    function togglePasswordVisibility() {
                        const passwordInput = document.getElementById('smtp_password');
                        const toggleIcon = document.getElementById('toggleIcon');
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            toggleIcon.classList.remove('bi-eye');
                            toggleIcon.classList.add('bi-eye-slash');
                        } else {
                            passwordInput.type = 'password';
                            toggleIcon.classList.remove('bi-eye-slash');
                            toggleIcon.classList.add('bi-eye');
                        }
                    }
                    </script>
                    
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
                    
                    <!-- Modelli di Stampa -->
                    <div class="tab-pane fade" id="print-templates" role="tabpanel">
                        <?php
                        // Initialize Print Template Controller
                        $printTemplateController = new PrintTemplateController($db, $config);
                        
                        // Handle print template actions
                        $printTemplateMessage = '';
                        $printTemplateError = '';
                        
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'print_template') {
                            // Validate CSRF token for print template actions
                            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                                $printTemplateError = 'Token di sicurezza non valido';
                            } elseif ($app->checkPermission('settings', 'edit')) {
                                $action = $_POST['template_action'] ?? '';
                                $userId = $_SESSION['user_id'];
                                
                                if ($action === 'delete' && isset($_POST['template_id'])) {
                                    try {
                                        $printTemplateController->delete((int)$_POST['template_id']);
                                        $printTemplateMessage = 'Template eliminato con successo';
                                    } catch (\Exception $e) {
                                        $printTemplateError = 'Errore durante l\'eliminazione: ' . $e->getMessage();
                                    }
                                }
                                
                                if ($action === 'toggle_active' && isset($_POST['template_id'])) {
                                    try {
                                        $template = $printTemplateController->getById((int)$_POST['template_id']);
                                        if ($template) {
                                            // Update only the is_active field, preserving all other fields
                                            $template['is_active'] = $template['is_active'] ? 0 : 1;
                                            $printTemplateController->update((int)$_POST['template_id'], $template, $userId);
                                            $printTemplateMessage = 'Stato template aggiornato';
                                        }
                                    } catch (\Exception $e) {
                                        $printTemplateError = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
                                    }
                                }
                                
                                if ($action === 'export' && isset($_POST['template_id'])) {
                                    try {
                                        $templateData = $printTemplateController->exportTemplate((int)$_POST['template_id']);
                                        // Sanitize filename to prevent header injection
                                        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $templateData['name']);
                                        $safeName = substr($safeName, 0, 100); // Limit filename length
                                        // Fallback if sanitized name is empty
                                        if (empty(trim($safeName)) || $safeName === '_') {
                                            $safeName = 'template_export_' . $templateData['entity_type'] ?? 'unknown';
                                        }
                                        header('Content-Type: application/json');
                                        header('Content-Disposition: attachment; filename="template_' . $safeName . '.json"');
                                        echo json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                        exit;
                                    } catch (\Exception $e) {
                                        $printTemplateError = 'Errore durante l\'esportazione: ' . $e->getMessage();
                                    }
                                }
                                
                                if ($action === 'import' && isset($_FILES['template_file'])) {
                                    try {
                                        // Validate file upload
                                        if ($_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
                                            throw new \Exception('Errore durante l\'upload del file');
                                        }
                                        
                                        // Validate file size (max 1MB for JSON template)
                                        if ($_FILES['template_file']['size'] > 1048576) {
                                            throw new \Exception('File troppo grande (max 1MB)');
                                        }
                                        
                                        // Validate file extension
                                        $fileName = $_FILES['template_file']['name'];
                                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                        if ($fileExt !== 'json') {
                                            throw new \Exception('Estensione file non valida. Sono ammessi solo file .json');
                                        }
                                        
                                        // Validate MIME type
                                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                        $mimeType = $finfo->file($_FILES['template_file']['tmp_name']);
                                        if ($mimeType !== 'application/json' && $mimeType !== 'text/plain') {
                                            throw new \Exception('Tipo di file non valido. Sono ammessi solo file JSON.');
                                        }
                                        
                                        $jsonContent = file_get_contents($_FILES['template_file']['tmp_name']);
                                        $templateData = json_decode($jsonContent, true);
                                        
                                        if (json_last_error() !== JSON_ERROR_NONE) {
                                            throw new \Exception('File JSON non valido: ' . json_last_error_msg());
                                        }
                                        
                                        // Validate required template structure
                                        if (!isset($templateData['name']) || !isset($templateData['entity_type'])) {
                                            throw new \Exception('Struttura template non valida. Campi richiesti: name, entity_type');
                                        }
                                        
                                        $printTemplateController->importTemplate($templateData, $userId);
                                        $printTemplateMessage = 'Template importato con successo';
                                    } catch (\Exception $e) {
                                        $printTemplateError = 'Errore durante l\'importazione: ' . $e->getMessage();
                                    }
                                }
                            }
                        }
                        
                        // Get filters
                        $printFilters = [];
                        if (!empty($_GET['pt_entity_type'])) {
                            $printFilters['entity_type'] = $_GET['pt_entity_type'];
                        }
                        if (!empty($_GET['pt_template_type'])) {
                            $printFilters['template_type'] = $_GET['pt_template_type'];
                        }
                        
                        $printTemplates = $printTemplateController->getAll($printFilters);
                        ?>
                        
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-printer me-2"></i>Gestione Modelli di Stampa</h5>
                                <div class="btn-group">
                                    <?php if ($app->checkPermission('settings', 'edit')): ?>
                                    <a href="print_template_editor.php" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-circle"></i> Nuovo Template
                                    </a>
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importTemplateModal">
                                        <i class="bi bi-upload"></i> Importa
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($printTemplateMessage): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($printTemplateMessage); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($printTemplateError): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($printTemplateError); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Sistema di Modelli di Stampa</strong><br>
                                    Crea, modifica e gestisci modelli di stampa per le varie sezioni del gestionale: Soci, Cadetti, Mezzi, Riunioni.
                                </div>
                                
                                <!-- Filters -->
                                <div class="card mb-3">
                                    <div class="card-body bg-light">
                                        <form method="GET" class="row g-3">
                                            <input type="hidden" name="tab" value="print-templates">
                                            <div class="col-md-4">
                                                <label class="form-label">Tipo Entità</label>
                                                <select name="pt_entity_type" class="form-select">
                                                    <option value="">Tutti</option>
                                                    <option value="members" <?php echo ($_GET['pt_entity_type'] ?? '') === 'members' ? 'selected' : ''; ?>>Soci</option>
                                                    <option value="junior_members" <?php echo ($_GET['pt_entity_type'] ?? '') === 'junior_members' ? 'selected' : ''; ?>>Cadetti (Minorenni)</option>
                                                    <option value="vehicles" <?php echo ($_GET['pt_entity_type'] ?? '') === 'vehicles' ? 'selected' : ''; ?>>Mezzi</option>
                                                    <option value="meetings" <?php echo ($_GET['pt_entity_type'] ?? '') === 'meetings' ? 'selected' : ''; ?>>Riunioni</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Tipo Template</label>
                                                <select name="pt_template_type" class="form-select">
                                                    <option value="">Tutti</option>
                                                    <option value="single" <?php echo ($_GET['pt_template_type'] ?? '') === 'single' ? 'selected' : ''; ?>>Singolo</option>
                                                    <option value="list" <?php echo ($_GET['pt_template_type'] ?? '') === 'list' ? 'selected' : ''; ?>>Lista</option>
                                                    <option value="multi_page" <?php echo ($_GET['pt_template_type'] ?? '') === 'multi_page' ? 'selected' : ''; ?>>Multi-pagina</option>
                                                    <option value="relational" <?php echo ($_GET['pt_template_type'] ?? '') === 'relational' ? 'selected' : ''; ?>>Relazionale</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary me-2">
                                                    <i class="bi bi-funnel"></i> Filtra
                                                </button>
                                                <a href="settings.php?tab=print-templates" class="btn btn-secondary">
                                                    <i class="bi bi-x-circle"></i> Reset
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Templates List -->
                                <?php if (empty($printTemplates)): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Nessun modello di stampa trovato. 
                                        <?php if ($app->checkPermission('settings', 'edit')): ?>
                                        <div class="mt-3">
                                            <a href="restore_print_templates.php" class="btn btn-primary btn-sm me-2">
                                                <i class="bi bi-arrow-clockwise"></i> Ripristina Template Predefiniti
                                            </a>
                                            <a href="print_template_editor.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-plus-circle"></i> Crea Template Personalizzato
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nome</th>
                                                    <th>Sezione</th>
                                                    <th>Tipo</th>
                                                    <th>Formato</th>
                                                    <th>Stato</th>
                                                    <th>Default</th>
                                                    <th>Azioni</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($printTemplates as $template): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($template['name']); ?></strong>
                                                            <?php if ($template['description']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($template['description']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $entityLabels = [
                                                                'members' => '<span class="badge bg-primary">Soci</span>',
                                                                'junior_members' => '<span class="badge bg-info">Cadetti</span>',
                                                                'vehicles' => '<span class="badge bg-warning">Mezzi</span>',
                                                                'meetings' => '<span class="badge bg-secondary">Riunioni</span>',
                                                            ];
                                                            echo $entityLabels[$template['entity_type']] ?? $template['entity_type'];
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $typeLabels = [
                                                                'single' => 'Singolo',
                                                                'list' => 'Lista',
                                                                'multi_page' => 'Multi-pagina',
                                                                'relational' => 'Relazionale',
                                                            ];
                                                            echo $typeLabels[$template['template_type']] ?? $template['template_type'];
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php echo strtoupper($template['page_format']); ?>
                                                            <?php echo $template['page_orientation'] === 'landscape' ? '(Orizzontale)' : ''; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($app->checkPermission('settings', 'edit')): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <?php echo CsrfProtection::getHiddenField(); ?>
                                                                <input type="hidden" name="form_type" value="print_template">
                                                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                                <input type="hidden" name="template_action" value="toggle_active">
                                                                <button type="submit" class="btn btn-sm <?php echo $template['is_active'] ? 'btn-success' : 'btn-secondary'; ?>" 
                                                                        title="<?php echo $template['is_active'] ? 'Disattiva' : 'Attiva'; ?>">
                                                                    <?php if ($template['is_active']): ?>
                                                                        <i class="bi bi-check-circle"></i> Attivo
                                                                    <?php else: ?>
                                                                        <i class="bi bi-x-circle"></i> Disattivo
                                                                    <?php endif; ?>
                                                                </button>
                                                            </form>
                                                            <?php else: ?>
                                                                <?php if ($template['is_active']): ?>
                                                                    <span class="badge bg-success">Attivo</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Disattivo</span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($template['is_default']): ?>
                                                                <i class="bi bi-star-fill text-warning" title="Template predefinito"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <?php if ($app->checkPermission('settings', 'edit')): ?>
                                                                <a href="print_template_editor.php?id=<?php echo $template['id']; ?>" 
                                                                   class="btn btn-outline-primary" title="Modifica">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn btn-outline-info" 
                                                                        onclick="window.open('print_preview.php?template_id=<?php echo $template['id']; ?>', '_blank')" title="Anteprima">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                <?php if ($app->checkPermission('settings', 'edit')): ?>
                                                                <form method="POST" style="display: inline;">
                                                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                                                    <input type="hidden" name="form_type" value="print_template">
                                                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                                    <input type="hidden" name="template_action" value="export">
                                                                    <button type="submit" class="btn btn-outline-secondary" title="Esporta">
                                                                        <i class="bi bi-download"></i>
                                                                    </button>
                                                                </form>
                                                                <form method="POST" style="display: inline;" 
                                                                      onsubmit="return confirm('Sei sicuro di voler eliminare questo template?');">
                                                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                                                    <input type="hidden" name="form_type" value="print_template">
                                                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                                    <input type="hidden" name="template_action" value="delete">
                                                                    <button type="submit" class="btn btn-outline-danger" title="Elimina">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                
                                <hr class="my-4">
                                
                                <h6><i class="bi bi-lightbulb me-2"></i>Tipi di Modelli Disponibili</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-2">
                                            <div class="card-body py-2">
                                                <strong><i class="bi bi-file-earmark"></i> Singolo</strong>
                                                <br><small class="text-muted">Stampa dettaglio di un singolo record (es. scheda socio)</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-2">
                                            <div class="card-body py-2">
                                                <strong><i class="bi bi-list"></i> Lista</strong>
                                                <br><small class="text-muted">Elenco tabellare di più record (es. elenco soci)</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-2">
                                            <div class="card-body py-2">
                                                <strong><i class="bi bi-files"></i> Multi-pagina</strong>
                                                <br><small class="text-muted">Una pagina per ogni record selezionato</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-2">
                                            <div class="card-body py-2">
                                                <strong><i class="bi bi-diagram-3"></i> Relazionale</strong>
                                                <br><small class="text-muted">Include dati da tabelle correlate (contatti, indirizzi, ecc.)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Import Template Modal -->
                    <div class="modal fade" id="importTemplateModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" enctype="multipart/form-data">
                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                    <input type="hidden" name="form_type" value="print_template">
                                    <input type="hidden" name="template_action" value="import">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Importa Template</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">File JSON Template</label>
                                            <input type="file" name="template_file" class="form-control" accept=".json" required>
                                            <div class="form-text">Seleziona un file JSON esportato da un template esistente</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-upload"></i> Importa
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Telegram Bot Configuration -->
                    <div class="tab-pane fade" id="telegram" role="tabpanel">
                        <?php
                        // Handle Telegram settings save
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'telegram') {
                            if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
                                $errors[] = 'Token di sicurezza non valido';
                            } elseif ($app->checkPermission('settings', 'edit')) {
                                try {
                                    $botToken = trim($_POST['telegram_bot_token'] ?? '');
                                    $botEnabled = isset($_POST['telegram_bot_enabled']) ? '1' : '0';
                                    
                                    // Save to config
                                    $db->getConnection()->beginTransaction();
                                    
                                    $sql = "INSERT INTO config (config_key, config_value) 
                                            VALUES (?, ?) 
                                            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)";
                                    $db->execute($sql, ['telegram_bot_token', $botToken]);
                                    $db->execute($sql, ['telegram_bot_enabled', $botEnabled]);
                                    
                                    $db->getConnection()->commit();
                                    
                                    header('Location: settings.php?success=telegram&tab=telegram');
                                    exit;
                                } catch (\Exception $e) {
                                    $db->getConnection()->rollBack();
                                    $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
                                }
                            }
                        }
                        
                        // Load current Telegram configuration
                        $telegramConfig = [
                            'telegram_bot_token' => '',
                            'telegram_bot_enabled' => '0'
                        ];
                        
                        $sql = "SELECT config_key, config_value FROM config WHERE config_key IN ('telegram_bot_token', 'telegram_bot_enabled')";
                        $configs = $db->fetchAll($sql);
                        foreach ($configs as $cfg) {
                            $telegramConfig[$cfg['config_key']] = $cfg['config_value'];
                        }
                        ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Configurazione Bot Telegram</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_GET['success']) && $_GET['success'] === 'telegram'): ?>
                                    <div class="alert alert-success alert-dismissible fade show">
                                        Configurazione Telegram salvata con successo!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> <strong>Come configurare il bot Telegram:</strong>
                                    <ol class="mb-0 mt-2">
                                        <li>Apri Telegram e cerca <strong>@BotFather</strong></li>
                                        <li>Invia il comando <code>/newbot</code> e segui le istruzioni</li>
                                        <li>Copia il token API fornito e incollalo qui sotto</li>
                                        <li>Per ottenere l'ID di un gruppo, aggiungi il bot al gruppo e usa <strong>@userinfobot</strong></li>
                                    </ol>
                                </div>
                                
                                <form method="POST">
                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                    <input type="hidden" name="form_type" value="telegram">
                                    
                                    <div class="mb-3">
                                        <label for="telegram_bot_token" class="form-label">Token Bot Telegram</label>
                                        <input type="text" class="form-control" id="telegram_bot_token" name="telegram_bot_token" 
                                               value="<?php echo htmlspecialchars($telegramConfig['telegram_bot_token']); ?>"
                                               placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'readonly' : ''; ?>>
                                        <div class="form-text">Inserisci il token fornito da BotFather</div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="telegram_bot_enabled" name="telegram_bot_enabled" 
                                               value="1" <?php echo $telegramConfig['telegram_bot_enabled'] == '1' ? 'checked' : ''; ?>
                                               <?php echo !$app->checkPermission('settings', 'edit') ? 'disabled' : ''; ?>>
                                        <label class="form-check-label" for="telegram_bot_enabled">
                                            Abilita notifiche Telegram
                                        </label>
                                    </div>
                                    
                                    <?php if ($app->checkPermission('settings', 'edit')): ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva Configurazione
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="testTelegramBtn">
                                            <i class="bi bi-wifi"></i> Testa Connessione
                                        </button>
                                    <?php endif; ?>
                                </form>
                                
                                <div id="testTelegramResult" class="mt-3" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <!-- Notification Recipients Configuration -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Configurazione Destinatari Notifiche</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Configura i destinatari per ogni tipo di notifica. Puoi selezionare soci che hanno un ID Telegram 
                                    nei loro contatti, oppure specificare gruppi Telegram tramite ID gruppo.
                                </p>
                                
                                <a href="telegram_recipients.php" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Gestisci Destinatari
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Qualifiche Soci -->
                    <div class="tab-pane fade" id="qualifications" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Gestione Mansioni Soci</h5>
                                <?php if ($app->checkPermission('settings', 'edit')): ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addQualificationModal">
                                        <i class="bi bi-plus-circle"></i> Aggiungi Mansione
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Gestisci le mansioni utilizzabili per i soci. Le mansioni possono essere riordinate, modificate o disattivate.
                                </p>
                                
                                <div id="qualificationsListContainer">
                                    <div class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Caricamento...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tipi Corsi -->
                    <div class="tab-pane fade" id="course-types" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Gestione Tipi Corsi</h5>
                                <?php if ($app->checkPermission('settings', 'edit')): ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseTypeModal">
                                        <i class="bi bi-plus-circle"></i> Aggiungi Tipo Corso
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    Gestisci i tipi di corsi utilizzabili nella formazione. I tipi di corso possono essere riordinati, modificati o disattivati.
                                </p>
                                
                                <div id="courseTypesListContainer">
                                    <div class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Caricamento...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal Add/Edit Qualification -->
    <div class="modal fade" id="addQualificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qualificationModalTitle">Aggiungi Mansione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="qualificationForm">
                    <div class="modal-body">
                        <input type="hidden" id="qualification_id" name="id">
                        <div class="mb-3">
                            <label for="qualification_name" class="form-label">Nome Mansione *</label>
                            <input type="text" class="form-control" id="qualification_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="qualification_description" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="qualification_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="qualification_is_active" name="is_active" checked>
                            <label class="form-check-label" for="qualification_is_active">Attiva</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Add/Edit Course Type -->
    <div class="modal fade" id="addCourseTypeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="courseTypeModalTitle">Aggiungi Tipo Corso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="courseTypeForm">
                    <div class="modal-body">
                        <input type="hidden" id="course_type_id" name="id">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="course_type_code" class="form-label">Codice *</label>
                                <input type="text" class="form-control" id="course_type_code" name="code" required 
                                       placeholder="es. A1, A2-01">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="course_type_name" class="form-label">Nome Corso *</label>
                                <input type="text" class="form-control" id="course_type_name" name="name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="course_type_category" class="form-label">Categoria</label>
                            <input type="text" class="form-control" id="course_type_category" name="category" 
                                   placeholder="es. Corsi Base, Corsi A2 - Specializzazione">
                        </div>
                        <div class="mb-3">
                            <label for="course_type_description" class="form-label">Descrizione</label>
                            <textarea class="form-control" id="course_type_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="course_type_is_active" name="is_active" checked>
                            <label class="form-check-label" for="course_type_is_active">Attivo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const VALID_TABS = ['general', 'association', 'mail', 'backup', 'import', 'print-templates', 'telegram', 'qualifications', 'course-types'];
    
    // Determina quale tab aprire
    let activeTab = null;
    
    // 1. Priorità URL success parameter
    // Note: 'email' key maps to 'mail' tab for backward compatibility with existing URLs
    const successMap = {
        'email': 'mail',
        'association': 'association'
    };
    const successParam = urlParams.get('success');
    if (successParam && successMap[successParam]) {
        activeTab = successMap[successParam];
    }
    
    // 2. Priorità URL tab parameter
    if (!activeTab) {
        const tabParam = urlParams.get('tab');
        if (tabParam && VALID_TABS.includes(tabParam)) {
            activeTab = tabParam;
        }
    }
    
    // 3. Parametri speciali (print-templates)
    if (!activeTab && (urlParams.has('pt_entity_type') || urlParams.has('pt_template_type'))) {
        activeTab = 'print-templates';
    }
    
    // 4. Hash URL
    if (!activeTab && window.location.hash) {
        const hash = window.location.hash.substring(1);
        if (VALID_TABS.includes(hash)) {
            activeTab = hash;
        }
    }
    
    // 5. localStorage
    if (!activeTab) {
        const saved = localStorage.getItem('easyvol_settings_active_tab');
        if (saved && VALID_TABS.includes(saved)) {
            activeTab = saved;
        }
    }
    
    // Attiva il tab usando Bootstrap API
    if (activeTab) {
        const tabButton = document.getElementById(activeTab + '-tab');
        if (tabButton) {
            const bsTab = new bootstrap.Tab(tabButton);
            bsTab.show();
        }
    }
    
    // Salva tab attivo in localStorage quando cambia
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('data-bs-target');
            if (target) {
                const tabId = target.substring(1); // Rimuovi #
                if (VALID_TABS.includes(tabId)) {
                    localStorage.setItem('easyvol_settings_active_tab', tabId);
                }
            }
        });
    });
    
    // Test Telegram connection
    const testTelegramBtn = document.getElementById('testTelegramBtn');
    if (testTelegramBtn) {
        testTelegramBtn.addEventListener('click', function() {
            const resultDiv = document.getElementById('testTelegramResult');
            const token = document.getElementById('telegram_bot_token').value;
            
            if (!token) {
                resultDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Inserisci un token prima di testare la connessione.</div>';
                resultDiv.style.display = 'block';
                return;
            }
            
            // Show loading
            testTelegramBtn.disabled = true;
            testTelegramBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Test in corso...';
            resultDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Test della connessione in corso...</div>';
            resultDiv.style.display = 'block';
            
            // Make test request
            fetch('telegram_test.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ token: token })
            })
            .then(response => response.json())
            .then(data => {
                testTelegramBtn.disabled = false;
                testTelegramBtn.innerHTML = '<i class="bi bi-wifi"></i> Testa Connessione';
                
                if (data.success) {
                    const botInfo = data.bot_info;
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <strong>Connessione riuscita!</strong>
                            <div class="mt-2">
                                <strong>Bot:</strong> @${botInfo.username}<br>
                                <strong>Nome:</strong> ${botInfo.first_name}<br>
                                ${botInfo.can_join_groups ? '<span class="badge bg-success">Può unirsi ai gruppi</span>' : ''}
                                ${botInfo.can_read_all_group_messages ? '<span class="badge bg-info">Può leggere messaggi di gruppo</span>' : ''}
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle"></i> <strong>Connessione fallita</strong>
                            <div class="mt-2">${data.message}</div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                testTelegramBtn.disabled = false;
                testTelegramBtn.innerHTML = '<i class="bi bi-wifi"></i> Testa Connessione';
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle"></i> <strong>Errore durante il test</strong>
                        <div class="mt-2">${error.message}</div>
                    </div>
                `;
            });
        });
    }
    
    // ==========================================
    // Qualifications Management
    // ==========================================
    
    // Load qualifications list
    function loadQualifications() {
        fetch('api/settings_manage.php?action=list&type=qualifications')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderQualificationsList(data.data);
                } else {
                    document.getElementById('qualificationsListContainer').innerHTML = 
                        '<div class="alert alert-danger">Errore caricamento mansioni: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('qualificationsListContainer').innerHTML = 
                    '<div class="alert alert-danger">Errore caricamento mansioni: ' + error.message + '</div>';
            });
    }
    
    function renderQualificationsList(qualifications) {
        let html = '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>' +
            '<th style="width: 40px;"><i class="bi bi-arrows-move"></i></th>' +
            '<th>Nome</th>' +
            '<th>Descrizione</th>' +
            '<th style="width: 100px;">Stato</th>' +
            '<th style="width: 120px;">Azioni</th></tr></thead><tbody id="qualificationsSortable">';
        
        qualifications.forEach(item => {
            const statusBadge = item.is_active == 1 
                ? '<span class="badge bg-success">Attiva</span>' 
                : '<span class="badge bg-secondary">Disattivata</span>';
            const description = item.description ? item.description.substring(0, 50) + (item.description.length > 50 ? '...' : '') : '-';
            
            html += `<tr data-id="${item.id}">
                <td class="drag-handle" style="cursor: move;"><i class="bi bi-grip-vertical"></i></td>
                <td><strong>${escapeHtml(item.name)}</strong></td>
                <td class="text-muted small">${escapeHtml(description)}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editQualification(${item.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteQualification(${item.id}, '${escapeHtml(item.name)}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
        document.getElementById('qualificationsListContainer').innerHTML = html;
        
        // Initialize drag and drop
        initSortable('qualificationsSortable', 'qualifications');
    }
    
    // Edit qualification
    window.editQualification = function(id) {
        fetch('api/settings_manage.php?action=get&type=qualifications&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('qualificationModalTitle').textContent = 'Modifica Mansione';
                    document.getElementById('qualification_id').value = data.data.id;
                    document.getElementById('qualification_name').value = data.data.name;
                    document.getElementById('qualification_description').value = data.data.description || '';
                    document.getElementById('qualification_is_active').checked = data.data.is_active == 1;
                    
                    const modal = new bootstrap.Modal(document.getElementById('addQualificationModal'));
                    modal.show();
                } else {
                    alert('Errore: ' + data.message);
                }
            });
    };
    
    // Delete qualification
    window.deleteQualification = function(id, name) {
        if (!confirm('Sei sicuro di voler eliminare la mansione "' + name + '"?')) {
            return;
        }
        
        fetch('api/settings_manage.php?action=delete&type=qualifications', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadQualifications();
                showAlert('success', data.message);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => showAlert('danger', 'Errore: ' + error.message));
    };
    
    // Handle qualification form submit
    document.getElementById('qualificationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            id: document.getElementById('qualification_id').value,
            name: document.getElementById('qualification_name').value,
            description: document.getElementById('qualification_description').value,
            is_active: document.getElementById('qualification_is_active').checked ? 1 : 0
        };
        
        fetch('api/settings_manage.php?action=save&type=qualifications', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addQualificationModal')).hide();
                loadQualifications();
                showAlert('success', data.message);
                this.reset();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => showAlert('danger', 'Errore: ' + error.message));
    });
    
    // Reset qualification form on modal close
    document.getElementById('addQualificationModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('qualificationModalTitle').textContent = 'Aggiungi Qualifica';
        document.getElementById('qualificationForm').reset();
        document.getElementById('qualification_id').value = '';
    });
    
    // ==========================================
    // Course Types Management
    // ==========================================
    
    // Load course types list
    function loadCourseTypes() {
        fetch('api/settings_manage.php?action=list&type=course-types')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderCourseTypesList(data.data);
                } else {
                    document.getElementById('courseTypesListContainer').innerHTML = 
                        '<div class="alert alert-danger">Errore caricamento tipi corso: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('courseTypesListContainer').innerHTML = 
                    '<div class="alert alert-danger">Errore caricamento tipi corso: ' + error.message + '</div>';
            });
    }
    
    function renderCourseTypesList(courseTypes) {
        let html = '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>' +
            '<th style="width: 40px;"><i class="bi bi-arrows-move"></i></th>' +
            '<th style="width: 100px;">Codice</th>' +
            '<th>Nome</th>' +
            '<th style="width: 150px;">Categoria</th>' +
            '<th style="width: 100px;">Stato</th>' +
            '<th style="width: 120px;">Azioni</th></tr></thead><tbody id="courseTypesSortable">';
        
        courseTypes.forEach(item => {
            const statusBadge = item.is_active == 1 
                ? '<span class="badge bg-success">Attivo</span>' 
                : '<span class="badge bg-secondary">Disattivato</span>';
            const category = item.category || '-';
            
            html += `<tr data-id="${item.id}">
                <td class="drag-handle" style="cursor: move;"><i class="bi bi-grip-vertical"></i></td>
                <td><code>${escapeHtml(item.code)}</code></td>
                <td><strong>${escapeHtml(item.name)}</strong></td>
                <td class="text-muted small">${escapeHtml(category)}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editCourseType(${item.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCourseType(${item.id}, '${escapeHtml(item.code)}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
        document.getElementById('courseTypesListContainer').innerHTML = html;
        
        // Initialize drag and drop
        initSortable('courseTypesSortable', 'course-types');
    }
    
    // Edit course type
    window.editCourseType = function(id) {
        fetch('api/settings_manage.php?action=get&type=course-types&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('courseTypeModalTitle').textContent = 'Modifica Tipo Corso';
                    document.getElementById('course_type_id').value = data.data.id;
                    document.getElementById('course_type_code').value = data.data.code;
                    document.getElementById('course_type_name').value = data.data.name;
                    document.getElementById('course_type_category').value = data.data.category || '';
                    document.getElementById('course_type_description').value = data.data.description || '';
                    document.getElementById('course_type_is_active').checked = data.data.is_active == 1;
                    
                    const modal = new bootstrap.Modal(document.getElementById('addCourseTypeModal'));
                    modal.show();
                } else {
                    alert('Errore: ' + data.message);
                }
            });
    };
    
    // Delete course type
    window.deleteCourseType = function(id, code) {
        if (!confirm('Sei sicuro di voler eliminare il tipo corso "' + code + '"?')) {
            return;
        }
        
        fetch('api/settings_manage.php?action=delete&type=course-types', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadCourseTypes();
                showAlert('success', data.message);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => showAlert('danger', 'Errore: ' + error.message));
    };
    
    // Handle course type form submit
    document.getElementById('courseTypeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            id: document.getElementById('course_type_id').value,
            code: document.getElementById('course_type_code').value,
            name: document.getElementById('course_type_name').value,
            category: document.getElementById('course_type_category').value,
            description: document.getElementById('course_type_description').value,
            is_active: document.getElementById('course_type_is_active').checked ? 1 : 0
        };
        
        fetch('api/settings_manage.php?action=save&type=course-types', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addCourseTypeModal')).hide();
                loadCourseTypes();
                showAlert('success', data.message);
                this.reset();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => showAlert('danger', 'Errore: ' + error.message));
    });
    
    // Reset course type form on modal close
    document.getElementById('addCourseTypeModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('courseTypeModalTitle').textContent = 'Aggiungi Tipo Corso';
        document.getElementById('courseTypeForm').reset();
        document.getElementById('course_type_id').value = '';
    });
    
    // ==========================================
    // Common Utilities
    // ==========================================
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showAlert(type, message) {
        // Create alert dynamically
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    function initSortable(tbodyId, type) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        
        let draggedElement = null;
        
        tbody.querySelectorAll('tr').forEach(row => {
            row.setAttribute('draggable', 'true');
            
            row.addEventListener('dragstart', function(e) {
                draggedElement = this;
                this.style.opacity = '0.5';
            });
            
            row.addEventListener('dragend', function(e) {
                this.style.opacity = '';
                
                // Save new order - validate all rows have data-id
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const ids = rows.map(r => r.dataset.id).filter(id => id); // Filter out undefined/null
                
                if (ids.length !== rows.length) {
                    console.error('Some table rows are missing data-id attributes');
                    showAlert('danger', 'Errore: impossibile salvare l\'ordine');
                    return;
                }
                
                fetch('api/settings_manage.php?action=reorder&type=' + type, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({items: ids})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                    } else {
                        showAlert('danger', data.message || 'Errore durante il salvataggio');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Errore: ' + error.message);
                });
            });
            
            row.addEventListener('dragover', function(e) {
                e.preventDefault();
            });
            
            row.addEventListener('drop', function(e) {
                e.preventDefault();
                if (this !== draggedElement) {
                    const allRows = Array.from(tbody.querySelectorAll('tr'));
                    const draggedIndex = allRows.indexOf(draggedElement);
                    const targetIndex = allRows.indexOf(this);
                    
                    if (draggedIndex < targetIndex) {
                        this.after(draggedElement);
                    } else {
                        this.before(draggedElement);
                    }
                }
            });
        });
    }
    
    // Load data when tabs are shown
    document.getElementById('qualifications-tab').addEventListener('shown.bs.tab', function() {
        loadQualifications();
    });
    
    document.getElementById('course-types-tab').addEventListener('shown.bs.tab', function() {
        loadCourseTypes();
    });
});
    </script>
</body>
</html>
