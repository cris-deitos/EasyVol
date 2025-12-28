<?php
/**
 * Restore Default Print Templates
 * 
 * This script restores the default print templates to the database.
 * It can be run when templates are missing or corrupted.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi (solo admin o chi ha permesso edit su settings)
if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato: necessari permessi di amministratore');
}

$errors = [];
$success = false;
$templatesRestored = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        try {
            $db = $app->getDb();
            
            // Check if seed file exists
            $seedFile = __DIR__ . '/../seed_print_templates.sql';
            if (!file_exists($seedFile)) {
                throw new Exception("File seed_print_templates.sql non trovato nella directory principale del progetto");
            }
            
            // Read seed file
            $sql = file_get_contents($seedFile);
            
            if (empty($sql)) {
                throw new Exception("Il file seed_print_templates.sql è vuoto");
            }
            
            // Remove SQL comments
            $sql = preg_replace('/--[^\n\r]*/', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            // Split by semicolons and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && 
                           stripos($stmt, 'INSERT INTO') !== false;
                }
            );
            
            $connection = $db->getConnection();
            $connection->beginTransaction();
            
            foreach ($statements as $statement) {
                try {
                    $connection->exec($statement);
                    $templatesRestored++;
                } catch (PDOException $e) {
                    // Check if it's a duplicate key error (template already exists)
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        // Skip duplicate, continue with others
                        continue;
                    } else {
                        // For other errors, log and continue
                        error_log("Template restoration warning: " . $e->getMessage());
                    }
                }
            }
            
            $connection->commit();
            
            if ($templatesRestored > 0) {
                $success = true;
                
                // Log the action
                $userId = $_SESSION['user_id'] ?? null;
                if ($userId) {
                    $logSql = "INSERT INTO activity_logs (user_id, action, module, description, ip_address, created_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())";
                    $db->query($logSql, [
                        $userId,
                        'restore_templates',
                        'print_templates',
                        "Ripristinati {$templatesRestored} template di stampa predefiniti",
                        $_SERVER['REMOTE_ADDR'] ?? ''
                    ]);
                }
            } else {
                $errors[] = "Nessun template da ripristinare. Potrebbero essere già presenti nel database.";
            }
            
        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            $errors[] = "Errore durante il ripristino: " . $e->getMessage();
        }
    }
}

$csrfToken = CsrfProtection::generateToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ripristino Template di Stampa - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .restore-container {
            max-width: 800px;
            margin: 50px auto;
        }
        .card {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .template-list {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .template-list ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="restore-container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Ripristino Template di Stampa
                </h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Ripristino completato con successo!</strong>
                        <p class="mb-0 mt-2">
                            Sono stati ripristinati <strong><?php echo $templatesRestored; ?></strong> template di stampa predefiniti.
                        </p>
                    </div>
                    <div class="text-center mt-4">
                        <a href="settings.php#print-templates" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-2"></i>Torna alle Impostazioni
                        </a>
                        <a href="print_templates.php" class="btn btn-outline-primary">
                            <i class="bi bi-printer me-2"></i>Visualizza Template
                        </a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Errori:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h5>Informazioni</h5>
                        <p>
                            Questo strumento ripristina i 10 template di stampa predefiniti di EasyVol nel database.
                            Utilizza questa funzione se:
                        </p>
                        <ul>
                            <li>Visualizzi il messaggio "Nessun modello di stampa trovato"</li>
                            <li>I template predefiniti sono stati eliminati accidentalmente</li>
                            <li>Vuoi ripristinare i template originali del sistema</li>
                        </ul>
                    </div>

                    <div class="warning-box">
                        <h6>
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Nota Importante</strong>
                        </h6>
                        <ul class="mb-0">
                            <li>I template con lo stesso nome NON verranno sovrascritti</li>
                            <li>L'operazione è reversibile (puoi eliminare i template dopo se necessario)</li>
                            <li>I template personalizzati esistenti non saranno modificati</li>
                        </ul>
                    </div>

                    <div class="template-list">
                        <h6><strong>Template che verranno ripristinati:</strong></h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Soci (5)</strong>
                                <ul>
                                    <li>Tessera Socio</li>
                                    <li>Scheda Socio</li>
                                    <li>Attestato di Partecipazione</li>
                                    <li>Libro Soci</li>
                                    <li>Tessere Multiple</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <strong>Altri (5)</strong>
                                <ul>
                                    <li>Scheda Mezzo</li>
                                    <li>Elenco Mezzi</li>
                                    <li>Verbale di Riunione</li>
                                    <li>Foglio Presenze Riunione</li>
                                    <li>Elenco Eventi</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="restoreForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div class="text-center mt-4">
                            <a href="settings.php#print-templates" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-x-circle me-2"></i>Annulla
                            </a>
                            <button type="submit" class="btn btn-primary" id="restoreBtn">
                                <i class="bi bi-arrow-clockwise me-2"></i>
                                Ripristina Template
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6><i class="bi bi-question-circle me-2"></i>Hai bisogno di aiuto?</h6>
                <p class="mb-0">
                    Consulta il file <code>SEED_TEMPLATES_README.md</code> nella directory principale del progetto 
                    per maggiori informazioni e metodi alternativi di ripristino.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add confirmation dialog
        document.getElementById('restoreForm')?.addEventListener('submit', function(e) {
            if (!confirm('Sei sicuro di voler ripristinare i template di stampa predefiniti?')) {
                e.preventDefault();
            } else {
                const btn = document.getElementById('restoreBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ripristino in corso...';
            }
        });
    </script>
</body>
</html>
