<?php
/**
 * Test Sendmail Configuration
 * 
 * This file tests the native PHP mail() function to verify sendmail is working correctly.
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\EmailSender;

// Only allow access for authenticated admins
$app = App::getInstance();

if (!$app->isLoggedIn()) {
    die('Accesso negato - Login richiesto');
}

if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato - Permessi insufficienti');
}

$testResult = [];
$testEmail = $_POST['test_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($testEmail)) {
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $testResult['success'] = false;
        $testResult['message'] = 'Indirizzo email non valido';
    } else {
        $db = $app->getDb();
        $config = $app->getConfig();
        
        $emailSender = new EmailSender($config, $db);
        
        $subject = 'Test Email da EasyVol';
        $body = '
        <html>
        <head>
            <title>Test Email</title>
        </head>
        <body>
            <h2>Test Email da EasyVol</h2>
            <p>Questa è un\'email di test per verificare che il sistema di invio email funzioni correttamente.</p>
            <p><strong>Configurazione:</strong></p>
            <ul>
                <?php 
                $fromAddr = $config['email']['from_address'] ?? $config['email']['from_email'] ?? 'N/A';
                $fromName = $config['email']['from_name'] ?? 'N/A';
                ?>
                <li>From: <?= htmlspecialchars($fromName) ?> &lt;<?= htmlspecialchars($fromAddr) ?>&gt;</li>
                <li>Reply-To: ' . htmlspecialchars($config['email']['reply_to'] ?? 'N/A') . '</li>
                <li>Charset: ' . htmlspecialchars($config['email']['charset'] ?? 'UTF-8') . '</li>
                <li>Encoding: ' . htmlspecialchars($config['email']['encoding'] ?? '8bit') . '</li>
            </ul>
            <p>Data/Ora: ' . date('Y-m-d H:i:s') . '</p>
        </body>
        </html>
        ';
        
        $result = $emailSender->send($testEmail, $subject, $body);
        
        if ($result) {
            $testResult['success'] = true;
            $testResult['message'] = 'Email inviata con successo a ' . htmlspecialchars($testEmail);
        } else {
            $testResult['success'] = false;
            $testResult['message'] = 'Errore durante l\'invio dell\'email. Controlla i log del server.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Sendmail - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-envelope-check"></i> Test Sendmail</h1>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Configurazione Email Corrente</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $config = $app->getConfig();
                                $emailConfig = $config['email'] ?? [];
                                ?>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Email Abilitata:</th>
                                        <td>
                                            <?php if ($emailConfig['enabled'] ?? false): ?>
                                                <span class="badge bg-success">Sì</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>From Address:</th>
                                        <td><?= htmlspecialchars($emailConfig['from_address'] ?? $emailConfig['from_email'] ?? 'Non configurato') ?></td>
                                    </tr>
                                    <tr>
                                        <th>From Name:</th>
                                        <td><?= htmlspecialchars($emailConfig['from_name'] ?? 'Non configurato') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Reply-To:</th>
                                        <td><?= htmlspecialchars($emailConfig['reply_to'] ?? 'Non configurato') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Return Path:</th>
                                        <td><?= htmlspecialchars($emailConfig['return_path'] ?? 'Non configurato') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Charset:</th>
                                        <td><?= htmlspecialchars($emailConfig['charset'] ?? 'UTF-8') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Encoding:</th>
                                        <td><?= htmlspecialchars($emailConfig['encoding'] ?? '8bit') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Sendmail Params:</th>
                                        <td><?= htmlspecialchars($emailConfig['sendmail_params'] ?? 'Nessuno') ?></td>
                                    </tr>
                                </table>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Nota:</strong> Questa configurazione utilizza la funzione mail() nativa di PHP con sendmail.
                                    Assicurati che sendmail sia configurato correttamente sul server.
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Invia Email di Test</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($testResult)): ?>
                                    <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?>">
                                        <i class="bi bi-<?= $testResult['success'] ? 'check-circle' : 'x-circle' ?>"></i>
                                        <?= htmlspecialchars($testResult['message']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="test_email" class="form-label">Indirizzo Email di Test</label>
                                        <input type="email" class="form-control" id="test_email" name="test_email" 
                                               value="<?= htmlspecialchars($testEmail) ?>" required
                                               placeholder="esempio@dominio.it">
                                        <div class="form-text">
                                            Inserisci un indirizzo email valido dove inviare l'email di test.
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Invia Email di Test
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Diagnostica</h5>
                            </div>
                            <div class="card-body">
                                <h6>Funzione mail() disponibile:</h6>
                                <?php if (function_exists('mail')): ?>
                                    <span class="badge bg-success">Sì</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No</span>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <h6>Configurazione PHP sendmail:</h6>
                                <small class="text-muted">
                                    <strong>sendmail_path:</strong><br>
                                    <code><?= htmlspecialchars(ini_get('sendmail_path') ?: 'Non configurato') ?></code>
                                </small>
                                
                                <hr>
                                
                                <h6>Troubleshooting:</h6>
                                <ul class="small">
                                    <li>Verifica che sendmail sia installato sul server</li>
                                    <li>Controlla i log di PHP per errori</li>
                                    <li>Verifica che le email non finiscano nella cartella spam</li>
                                    <li>Assicurati che il firewall permetta l'invio di email</li>
                                </ul>
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
