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
            $pdo->exec($schema);
            
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
                $stmt = $pdo->prepare("INSERT INTO association (name, email, pec, tax_code, address_street, address_number, address_city, address_province, address_cap) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$assocName, $assocEmail, $assocPec, $assocTaxCode, $assocStreet, $assocNumber, $assocCity, $assocProvince, $assocCap]);
                
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
                
                unset($_SESSION['install']);
                
                header("Location: install.php?step=3");
                exit;
            } catch (Exception $e) {
                $errors[] = "Setup error: " . $e->getMessage();
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

                <?php if ($isInstalled && $step < 3): ?>
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
                    <div class="step <?= $step >= 3 ? 'active' : '' ?>">
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
                        
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="bi bi-check-circle"></i> Completa Installazione
                        </button>
                    </form>
                    
                <?php elseif ($step === 3): ?>
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
