<?php
session_start();
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

// Check if already installed
$configFile = __DIR__ . '/../config/config.php';
$isInstalled = false;

if (file_exists($configFile)) {
    $config = require $configFile;
    if (isset($config['database']['name']) && !empty($config['database']['name']) && $config['database']['name'] !== 'easyvol') {
        $isInstalled = true;
    }
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Database connection test
        $dbHost = $_POST['db_host'] ?? 'localhost';
        $dbPort = $_POST['db_port'] ?? 3306;
        $dbName = $_POST['db_name'] ?? '';
        $dbUser = $_POST['db_user'] ?? '';
        $dbPass = $_POST['db_pass'] ?? '';
        
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            
            // Import schema
            $schema = file_get_contents(__DIR__ . '/../database_schema.sql');
            
            // Remove SQL comments before processing
            // Remove single-line comments (-- comment)
            $schema = preg_replace('/--[^\n\r]*/', '', $schema);
            // Remove multi-line comments (/* comment */)
            $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);
            
            // Split by semicolons and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $schema)),
                function($stmt) {
                    return !empty($stmt);
                }
            );
            
            foreach ($statements as $statement) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Log but continue - some statements might fail if tables exist
                    error_log("Schema execution warning: " . $e->getMessage());
                }
            }
            
            // Save database config
            $_SESSION['install'] = [
                'db_host' => $dbHost,
                'db_port' => $dbPort,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass,
            ];
            
            $success[] = "Database configured successfully!";
            header("Location: install.php?step=2");
            exit;
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    } elseif ($step === 2) {
        // Association and admin setup
        $assocName = $_POST['assoc_name'] ?? '';
        $assocEmail = $_POST['assoc_email'] ?? '';
        $assocPec = $_POST['assoc_pec'] ?? '';
        $assocTaxCode = $_POST['assoc_tax_code'] ?? '';
        $assocPhone = $_POST['assoc_phone'] ?? '';
        $assocStreet = $_POST['assoc_street'] ?? '';
        $assocNumber = $_POST['assoc_number'] ?? '';
        $assocCity = $_POST['assoc_city'] ?? '';
        $assocProvince = $_POST['assoc_province'] ?? '';
        $assocCap = $_POST['assoc_cap'] ?? '';
        
        $adminUsername = $_POST['admin_username'] ?? '';
        $adminEmail = $_POST['admin_email'] ?? '';
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';
        $adminFullName = $_POST['admin_full_name'] ?? '';
        
        // Validation
        if (empty($assocName) || empty($adminUsername) || empty($adminPassword)) {
            $errors[] = "Please fill in all required fields.";
        } elseif ($adminPassword !== $adminPasswordConfirm) {
            $errors[] = "Passwords do not match.";
        } elseif (strlen($adminPassword) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        } else {
            try {
                // Connect to database
                if (!isset($_SESSION['install'])) {
                    throw new Exception("Session data not found. Please start from step 1.");
                }
                $installData = $_SESSION['install'];
                $dsn = "mysql:host={$installData['db_host']};port={$installData['db_port']};dbname={$installData['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $installData['db_user'], $installData['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Insert association data
                $stmt = $pdo->prepare("INSERT INTO association (name, email, pec, tax_code, phone, address_street, address_number, address_city, address_province, address_cap) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$assocName, $assocEmail, $assocPec, $assocTaxCode, $assocPhone, $assocStreet, $assocNumber, $assocCity, $assocProvince, $assocCap]);
                
                // Create admin role
                $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES ('admin', 'Administrator with full access')");
                $stmt->execute();
                $adminRoleId = $pdo->lastInsertId();
                
                // Create admin user
                $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$adminUsername, $hashedPassword, $adminEmail, $adminFullName, $adminRoleId]);
                
                // Create all permissions
                $modules = [
                    'members', 'junior_members', 'users', 'meetings', 'vehicles', 'warehouse',
                    'training', 'events', 'documents', 'scheduler', 'operations_center',
                    'applications', 'reports', 'settings'
                ];
                
                $actions = ['view', 'create', 'edit', 'delete', 'report'];
                
                foreach ($modules as $module) {
                    foreach ($actions as $action) {
                        $stmt = $pdo->prepare("INSERT INTO permissions (module, action, description) VALUES (?, ?, ?)");
                        $stmt->execute([$module, $action, "$action permission for $module"]);
                        $permId = $pdo->lastInsertId();
                        
                        // Assign to admin role
                        $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        $stmt->execute([$adminRoleId, $permId]);
                    }
                }
                
                // Insert default email configuration into config table
                $emailConfigDefaults = [
                    'email_enabled' => '0',
                    'email_method' => 'smtp',
                    'email_from_address' => $assocEmail ?: 'noreply@example.com',
                    'email_from_name' => $assocName ?: 'EasyVol',
                    'email_reply_to' => '',
                    'email_return_path' => '',
                    'email_charset' => 'UTF-8',
                    'email_smtp_host' => '',
                    'email_smtp_port' => '587',
                    'email_smtp_username' => '',
                    'email_smtp_password' => '',
                    'email_smtp_encryption' => 'tls',
                    'email_smtp_auth' => '1',
                    'email_smtp_debug' => '0',
                ];
                
                $stmt = $pdo->prepare("INSERT IGNORE INTO config (config_key, config_value) VALUES (?, ?)");
                foreach ($emailConfigDefaults as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                
                // Save configuration file
                $sampleConfig = require __DIR__ . '/../config/config.sample.php';
                $sampleConfig['database'] = [
                    'host' => $installData['db_host'],
                    'port' => (int)$installData['db_port'],
                    'name' => $installData['db_name'],
                    'username' => $installData['db_user'],
                    'password' => $installData['db_pass'],
                    'charset' => 'utf8mb4',
                ];
                
                $configContent = "<?php\nreturn " . var_export($sampleConfig, true) . ";\n";
                file_put_contents($configFile, $configContent);
                
                // Store association email for step 3
                $_SESSION['install']['assoc_email'] = $assocEmail;
                $_SESSION['install']['assoc_name'] = $assocName;
                
                header("Location: install.php?step=3");
                exit;
            } catch (Exception $e) {
                $errors[] = "Setup error: " . $e->getMessage();
            }
        }
    } elseif ($step === 3) {
        // Optional email configuration
        $configureEmail = isset($_POST['configure_email']) ? true : false;
        
        if (!$configureEmail) {
            // Skip email configuration
            unset($_SESSION['install']);
            header("Location: install.php?step=4");
            exit;
        }
        
        // Email configuration
        $emailEnabled = isset($_POST['email_enabled']) ? '1' : '0';
        $emailMethod = trim($_POST['email_method'] ?? 'smtp');
        $fromAddress = trim($_POST['from_address'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = intval($_POST['smtp_port'] ?? 587);
        $smtpUsername = trim($_POST['smtp_username'] ?? '');
        $smtpPassword = $_POST['smtp_password'] ?? '';
        $smtpEncryption = trim($_POST['smtp_encryption'] ?? 'tls');
        $smtpAuth = isset($_POST['smtp_auth']) ? '1' : '0';
        
        // Validation
        if ($emailEnabled === '1') {
            if (empty($fromAddress) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Indirizzo email del mittente non valido";
            }
            if (!in_array($emailMethod, ['smtp', 'sendmail'])) {
                $errors[] = "Metodo di invio email non valido";
            }
            if (!in_array($smtpEncryption, ['tls', 'ssl', ''])) {
                $errors[] = "Tipo di crittografia SMTP non valido";
            }
            if ($smtpPort < 1 || $smtpPort > 65535) {
                $errors[] = "Porta SMTP non valida (1-65535)";
            }
        }
        
        if (empty($errors)) {
            try {
                // Connect to database
                if (!isset($_SESSION['install'])) {
                    throw new Exception("Session data not found. Please start from step 1.");
                }
                $installData = $_SESSION['install'];
                $dsn = "mysql:host={$installData['db_host']};port={$installData['db_port']};dbname={$installData['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $installData['db_user'], $installData['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Update email settings in database
                $emailSettings = [
                    'email_enabled' => $emailEnabled,
                    'email_method' => $emailMethod,
                    'email_from_address' => $fromAddress,
                    'email_from_name' => $fromName,
                    'email_smtp_host' => $smtpHost,
                    'email_smtp_port' => (string)$smtpPort,
                    'email_smtp_username' => $smtpUsername,
                    'email_smtp_password' => $smtpPassword,
                    'email_smtp_encryption' => $smtpEncryption,
                    'email_smtp_auth' => $smtpAuth,
                ];
                
                $stmt = $pdo->prepare("INSERT INTO config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                foreach ($emailSettings as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                
                unset($_SESSION['install']);
                
                header("Location: install.php?step=4");
                exit;
            } catch (Exception $e) {
                error_log("Email configuration error: " . $e->getMessage());
                $errors[] = "Errore durante il salvataggio delle impostazioni email";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasyVol - Installazione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .install-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: none;
            border-radius: 15px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 0 5px;
            position: relative;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="card">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h1 class="display-4 mb-2"><i class="bi bi-heart-pulse text-danger"></i> EasyVol</h1>
                    <p class="text-muted">Sistema Gestionale per Associazioni di Volontariato</p>
                </div>

                <?php if ($isInstalled && $step < 4): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> L'applicazione risulta già installata. 
                        <a href="login.php" class="alert-link">Vai al login</a>
                    </div>
                <?php else: ?>
                
                <div class="step-indicator">
                    <div class="step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">
                        <i class="bi bi-database"></i><br>
                        <small>Database</small>
                    </div>
                    <div class="step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">
                        <i class="bi bi-building"></i><br>
                        <small>Associazione</small>
                    </div>
                    <div class="step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">
                        <i class="bi bi-envelope"></i><br>
                        <small>Email</small>
                    </div>
                    <div class="step <?= $step >= 4 ? 'active' : '' ?>">
                        <i class="bi bi-check-circle"></i><br>
                        <small>Completato</small>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><i class="bi bi-x-circle"></i> <?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php foreach ($success as $msg): ?>
                            <div><i class="bi bi-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <h3 class="mb-4">Passo 1: Configurazione Database</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Host Database</label>
                            <input type="text" class="form-control" name="db_host" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Porta</label>
                            <input type="number" class="form-control" name="db_port" value="3306" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome Database</label>
                            <input type="text" class="form-control" name="db_name" value="easyvol" required>
                            <div class="form-text">Il database verrà creato se non esiste</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="db_user" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="db_pass">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-arrow-right"></i> Avanti
                        </button>
                    </form>
                    
                <?php elseif ($step === 2): ?>
                    <h3 class="mb-4">Passo 2: Dati Associazione e Amministratore</h3>
                    <form method="POST">
                        <h5 class="mt-4 mb-3">Dati Associazione</h5>
                        <div class="mb-3">
                            <label class="form-label">Ragione Sociale *</label>
                            <input type="text" class="form-control" name="assoc_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="assoc_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PEC</label>
                                <input type="email" class="form-control" name="assoc_pec">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Codice Fiscale</label>
                            <input type="text" class="form-control" name="assoc_tax_code">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefono</label>
                            <input type="tel" class="form-control" name="assoc_phone" placeholder="es. +39 030 1234567">
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Via</label>
                                <input type="text" class="form-control" name="assoc_street">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Civico</label>
                                <input type="text" class="form-control" name="assoc_number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Comune</label>
                                <input type="text" class="form-control" name="assoc_city">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Provincia</label>
                                <input type="text" class="form-control" name="assoc_province" maxlength="2">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">CAP</label>
                                <input type="text" class="form-control" name="assoc_cap">
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3">Dati Amministratore</h5>
                        <div class="mb-3">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" name="admin_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="admin_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="admin_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="admin_password" required>
                            <div class="form-text">Minimo 8 caratteri</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Conferma Password *</label>
                            <input type="password" class="form-control" name="admin_password_confirm" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-arrow-right"></i> Avanti
                        </button>
                    </form>
                    
                <?php elseif ($step === 3): ?>
                    <h3 class="mb-4">Passo 3: Configurazione Email (Facoltativa)</h3>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Puoi configurare le impostazioni email ora o saltare questo passaggio e configurarle successivamente nelle impostazioni del sistema.
                    </div>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="configure_email" name="configure_email" value="1" onchange="toggleEmailConfig()">
                                <label class="form-check-label" for="configure_email">
                                    <strong>Configura le impostazioni email ora</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div id="email_config_section" style="display: none;">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" value="1" checked>
                                    <label class="form-check-label" for="email_enabled">
                                        <strong>Abilita Invio Email</strong>
                                    </label>
                                </div>
                                <small class="text-muted">Attiva/disattiva l'invio di email dal sistema</small>
                            </div>
                            
                            <hr class="my-4">
                            <h6><i class="bi bi-person-lines-fill me-2"></i>Informazioni Mittente</h6>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="from_address" class="form-label">Indirizzo Email Mittente *</label>
                                    <input type="email" class="form-control" id="from_address" name="from_address" 
                                           value="<?php echo htmlspecialchars($_SESSION['install']['assoc_email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="from_name" class="form-label">Nome Mittente *</label>
                                    <input type="text" class="form-control" id="from_name" name="from_name" 
                                           value="<?php echo htmlspecialchars($_SESSION['install']['assoc_name'] ?? 'EasyVol'); ?>">
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            <h6><i class="bi bi-gear-fill me-2"></i>Metodo di Invio</h6>
                            
                            <div class="mb-3">
                                <label for="email_method" class="form-label">Metodo di Invio Email</label>
                                <select class="form-select" id="email_method" name="email_method">
                                    <option value="smtp" selected>SMTP (Consigliato)</option>
                                    <option value="sendmail">Sendmail (mail() PHP)</option>
                                </select>
                                <small class="text-muted">SMTP è raccomandato per maggiore affidabilità</small>
                            </div>
                            
                            <hr class="my-4">
                            <h6><i class="bi bi-hdd-network-fill me-2"></i>Configurazione Server SMTP</h6>
                            
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="smtp_host" class="form-label">Host SMTP</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                           placeholder="es. smtp.gmail.com">
                                    <small class="text-muted">Indirizzo del server SMTP</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="smtp_port" class="form-label">Porta SMTP</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                           value="587" min="1" max="65535">
                                    <small class="text-muted">587 (TLS) o 465 (SSL)</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_username" class="form-label">Username SMTP</label>
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                           placeholder="es. tuoemail@gmail.com">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_password" class="form-label">Password SMTP</label>
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                           placeholder="Password o App Password">
                                    <small class="text-muted">Per Gmail usa "App Password"</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_encryption" class="form-label">Crittografia</label>
                                    <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" selected>TLS (Raccomandato - Porta 587)</option>
                                        <option value="ssl">SSL (Porta 465)</option>
                                        <option value="">Nessuna (non sicuro)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="smtp_auth" name="smtp_auth" value="1" checked>
                                        <label class="form-check-label" for="smtp_auth">
                                            Richiedi Autenticazione SMTP
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-lightbulb"></i>
                                <strong>Suggerimento:</strong> Se non sei sicuro delle impostazioni, puoi saltare questo passaggio e configurare l'email successivamente nelle impostazioni del sistema.
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg flex-grow-1">
                                <i class="bi bi-arrow-right"></i> <span id="btn_text">Salta e Completa</span>
                            </button>
                        </div>
                    </form>
                    
                    <script>
                    function toggleEmailConfig() {
                        const configureCheckbox = document.getElementById('configure_email');
                        const emailSection = document.getElementById('email_config_section');
                        const btnText = document.getElementById('btn_text');
                        
                        if (configureCheckbox.checked) {
                            emailSection.style.display = 'block';
                            btnText.textContent = 'Salva e Completa';
                        } else {
                            emailSection.style.display = 'none';
                            btnText.textContent = 'Salta e Completa';
                        }
                    }
                    </script>
                    
                <?php elseif ($step === 4): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 5rem;"></i>
                        <h3 class="mt-4 mb-3">Installazione Completata!</h3>
                        <p class="text-muted mb-4">EasyVol è stato configurato con successo.</p>
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Vai al Login
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4 text-white">
            <small>EasyVol v1.0 - Sistema Gestionale per Protezione Civile</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
