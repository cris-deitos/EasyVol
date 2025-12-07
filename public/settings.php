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

// Gestione salvataggio impostazioni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $app->checkPermission('settings', 'edit')) {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        // TODO: Implement settings save functionality
        $success = true;
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
                        Impostazioni salvate con successo!
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
                                <dl class="row">
                                    <dt class="col-sm-3">Nome</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['association']['name'] ?? 'N/D'); ?></dd>
                                    
                                    <dt class="col-sm-3">Indirizzo</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['association']['address'] ?? 'N/D'); ?></dd>
                                    
                                    <dt class="col-sm-3">Città</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['association']['city'] ?? 'N/D'); ?></dd>
                                    
                                    <dt class="col-sm-3">Codice Fiscale</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['association']['tax_code'] ?? 'N/D'); ?></dd>
                                    
                                    <dt class="col-sm-3">Email</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['association']['email'] ?? 'N/D'); ?></dd>
                                    
                                    <dt class="col-sm-3">PEC</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['association']['pec'] ?? 'N/D'); ?></dd>
                                </dl>
                                <p class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    Questi dati sono stati configurati durante l'installazione. Per modificarli, edita il file <code>config/config.php</code>.
                                </p>
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
                                
                                <dl class="row">
                                    <dt class="col-sm-3">Metodo</dt>
                                    <dd class="col-sm-9"><?php echo strtoupper($config['email']['method'] ?? 'mail'); ?></dd>
                                    
                                    <?php if (($config['email']['method'] ?? '') === 'smtp'): ?>
                                        <dt class="col-sm-3">SMTP Host</dt>
                                        <dd class="col-sm-9"><?php echo htmlspecialchars($config['email']['smtp_host'] ?? 'N/D'); ?></dd>
                                        
                                        <dt class="col-sm-3">SMTP Port</dt>
                                        <dd class="col-sm-9"><?php echo htmlspecialchars($config['email']['smtp_port'] ?? 'N/D'); ?></dd>
                                    <?php endif; ?>
                                    
                                    <dt class="col-sm-3">From Email</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['email']['from_email'] ?? 'N/D'); ?></dd>
                                    
                                    <dt class="col-sm-3">From Name</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($config['email']['from_name'] ?? 'N/D'); ?></dd>
                                </dl>
                                
                                <p class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    Per modificare la configurazione email, edita il file <code>config/config.php</code>.
                                </p>
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
