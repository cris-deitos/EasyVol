<?php
/**
 * Test Email Configuration (PHPMailer/SMTP)
 * 
 * This file tests the email sending configuration using PHPMailer with SMTP.
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
        
        $method = $config['email']['method'] ?? 'smtp';
        $smtpHost = $config['email']['smtp_host'] ?? 'N/A';
        $smtpPort = $config['email']['smtp_port'] ?? 587;
        $fromAddr = $config['email']['from_address'] ?? $config['email']['from_email'] ?? 'N/A';
        $fromName = $config['email']['from_name'] ?? 'EasyVol';
        
        $subject = 'Test Email da EasyVol - PHPMailer/SMTP';
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Test Email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; }
                .info-table { width: 100%; border-collapse: collapse; }
                .info-table td { padding: 8px; border-bottom: 1px solid #eee; }
                .info-table td:first-child { font-weight: bold; width: 150px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>✅ Test Email Riuscito!</h2>
                </div>
                <div class="content">
                    <p>Questa è un\'email di test per verificare che il sistema di invio email con <strong>PHPMailer</strong> funzioni correttamente.</p>
                    
                    <h3>📧 Configurazione Utilizzata:</h3>
                    <table class="info-table">
                        <tr><td>Metodo:</td><td>' . htmlspecialchars(strtoupper($method)) . '</td></tr>
                        <tr><td>SMTP Host:</td><td>' . htmlspecialchars($smtpHost) . '</td></tr>
                        <tr><td>SMTP Port:</td><td>' . htmlspecialchars($smtpPort) . '</td></tr>
                        <tr><td>From:</td><td>' . htmlspecialchars($fromName) . ' &lt;' . htmlspecialchars($fromAddr) . '&gt;</td></tr>
                        <tr><td>Reply-To:</td><td>' . htmlspecialchars($config['email']['reply_to'] ?? 'N/A') . '</td></tr>
                        <tr><td>Charset:</td><td>' . htmlspecialchars($config['email']['charset'] ?? 'UTF-8') . '</td></tr>
                    </table>
                    
                    <p style="margin-top: 20px;"><strong>Data/Ora invio:</strong> ' . date('d/m/Y H:i:s') . '</p>
                </div>
                <div class="footer">
                    <p>Questo messaggio è stato inviato automaticamente da EasyVol</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $result = $emailSender->send($testEmail, $subject, $body);
        
        if ($result) {
            $testResult['success'] = true;
            $testResult['message'] = 'Email inviata con successo a ' . htmlspecialchars($testEmail);
        } else {
            $testResult['success'] = false;
            $testResult['message'] = 'Errore durante l\'invio dell\'email. Controlla i log del server per maggiori dettagli.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email (PHPMailer/SMTP) - EasyVol</title>
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
                    <h1 class="h2"><i class="bi bi-envelope-check"></i> Test Email (PHPMailer/SMTP)</h1>
                    <a href="settings.php#mail-tab" class="btn btn-outline-secondary">
                        <i class="bi bi-gear"></i> Impostazioni Email
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-hdd-network me-2"></i>Configurazione Email Corrente</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $config = $app->getConfig();
                                $emailConfig = $config['email'] ?? [];
                                $method = $emailConfig['method'] ?? 'smtp';
                                ?>
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <th width="200">Email Abilitata:</th>
                                        <td>
                                            <?php if ($emailConfig['enabled'] ?? false): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sì</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="bi bi-x-circle"></i> No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Metodo di Invio:</th>
                                        <td>
                                            <span class="badge bg-<?= $method === 'smtp' ? 'primary' : 'secondary' ?>">
                                                <?= htmlspecialchars(strtoupper($method)) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr class="table-info">
                                        <th colspan="2"><strong><i class="bi bi-hdd-network me-1"></i> Configurazione SMTP</strong></th>
                                    </tr>
                                    <tr>
                                        <th>Host SMTP:</th>
                                        <td>
                                            <?= htmlspecialchars($emailConfig['smtp_host'] ?? '') ?: '<span class="text-danger">Non configurato</span>' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Porta SMTP:</th>
                                        <td><?= htmlspecialchars($emailConfig['smtp_port'] ?? 587) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Username SMTP:</th>
                                        <td>
                                            <?= !empty($emailConfig['smtp_username']) ? htmlspecialchars($emailConfig['smtp_username']) : '<span class="text-muted">Non configurato</span>' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Password SMTP:</th>
                                        <td>
                                            <?= !empty($emailConfig['smtp_password']) ? '<span class="text-success">●●●●●●●● (configurata)</span>' : '<span class="text-muted">Non configurata</span>' ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Crittografia:</th>
                                        <td>
                                            <?php 
                                            $enc = $emailConfig['smtp_encryption'] ?? 'tls';
                                            echo match($enc) {
                                                'tls' => '<span class="badge bg-success">TLS</span>',
                                                'ssl' => '<span class="badge bg-success">SSL</span>',
                                                default => '<span class="badge bg-warning">Nessuna</span>'
                                            };
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Autenticazione:</th>
                                        <td>
                                            <?= ($emailConfig['smtp_auth'] ?? true) ? '<span class="badge bg-success">Abilitata</span>' : '<span class="badge bg-secondary">Disabilitata</span>' ?>
                                        </td>
                                    </tr>
                                    <tr class="table-info">
                                        <th colspan="2"><strong><i class="bi bi-person-lines-fill me-1"></i> Informazioni Mittente</strong></th>
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
                                        <td><?= htmlspecialchars($emailConfig['reply_to'] ?? '') ?: '<span class="text-muted">Non configurato</span>' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Return Path:</th>
                                        <td><?= htmlspecialchars($emailConfig['return_path'] ?? '') ?: '<span class="text-muted">Non configurato</span>' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Charset:</th>
                                        <td><?= htmlspecialchars($emailConfig['charset'] ?? 'UTF-8') ?></td>
                                    </tr>
                                </table>
                                
                                <?php if ($method === 'smtp' && empty($emailConfig['smtp_host'])): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Attenzione:</strong> Il metodo SMTP è selezionato ma l'host SMTP non è configurato. 
                                    <a href="settings.php" class="alert-link">Configura le impostazioni email</a>.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-send me-2"></i>Invia Email di Test</h5>
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
                                    
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-send"></i> Invia Email di Test
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-check2-all me-2"></i>Diagnostica</h5>
                            </div>
                            <div class="card-body">
                                <h6>PHPMailer disponibile:</h6>
                                <?php if (class_exists('\PHPMailer\PHPMailer\PHPMailer')): ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Sì</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x"></i> No</span>
                                    <div class="alert alert-danger mt-2">
                                        <small>PHPMailer non è installato. Esegui:<br>
                                        <code>composer install</code></small>
                                    </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <h6>Funzione mail() disponibile:</h6>
                                <?php if (function_exists('mail')): ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Sì</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="bi bi-dash"></i> No</span>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <h6>Estensione OpenSSL:</h6>
                                <?php if (extension_loaded('openssl')): ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Abilitata</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x"></i> Non abilitata</span>
                                    <small class="text-danger d-block mt-1">Richiesta per TLS/SSL</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Troubleshooting</h5>
                            </div>
                            <div class="card-body">
                                <ul class="small mb-0">
                                    <li>Verifica che le credenziali SMTP siano corrette</li>
                                    <li>Per Gmail, usa una "App Password" (non la password normale)</li>
                                    <li>Controlla che la porta (587/465) non sia bloccata dal firewall</li>
                                    <li>Abilita "Debug SMTP" nelle impostazioni per vedere errori dettagliati</li>
                                    <li>Verifica che l'email non finisca nella cartella spam</li>
                                    <li>Controlla i log di PHP per errori</li>
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
