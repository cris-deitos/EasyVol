<?php
/**
 * Cron Jobs Status Page
 * 
 * This page provides a simple interface to check if cron job endpoints are properly configured.
 * Useful for testing and debugging cron job setup.
 * 
 * NOTE: This page does NOT execute cron jobs. It only shows configuration status.
 */

require_once __DIR__ . '/../../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();
$config = $app->getConfig();

$cronConfig = $config['cron'] ?? [];
$tokenConfigured = !empty($cronConfig['secret_token']);
$webAllowed = $cronConfig['allow_web'] ?? true;
$cliAllowed = $cronConfig['allow_cli'] ?? true;
$hasIpWhitelist = !empty($cronConfig['allowed_ips']);

// List of all available cron jobs
$cronJobs = [
    'email_queue' => [
        'name' => 'Email Queue Processor',
        'frequency' => 'Every 5 minutes',
        'description' => 'Processes the email queue'
    ],
    'vehicle_alerts' => [
        'name' => 'Vehicle Alerts',
        'frequency' => 'Daily at 08:00',
        'description' => 'Checks vehicle expiration dates'
    ],
    'scheduler_alerts' => [
        'name' => 'Scheduler Alerts',
        'frequency' => 'Daily at 08:00',
        'description' => 'Sends reminders for upcoming deadlines'
    ],
    'member_expiry_alerts' => [
        'name' => 'Member Expiry Alerts',
        'frequency' => 'Daily at 08:00',
        'description' => 'Checks member licenses and qualifications'
    ],
    'health_surveillance_alerts' => [
        'name' => 'Health Surveillance Alerts',
        'frequency' => 'Daily at 08:00',
        'description' => 'Checks health surveillance visit expiration'
    ],
    'annual_member_verification' => [
        'name' => 'Annual Member Verification',
        'frequency' => 'January 7th at 09:00',
        'description' => 'Sends annual data verification emails'
    ],
    'backup' => [
        'name' => 'Database Backup',
        'frequency' => 'Daily at 02:00',
        'description' => 'Creates automated database backup'
    ],
    'sync_all_expiry_dates' => [
        'name' => 'Sync Expiry Dates',
        'frequency' => 'Weekly',
        'description' => 'Synchronizes all expiry dates to scheduler'
    ]
];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasyVol - Cron Jobs Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        header h1 {
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .status-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .status-box.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        
        .status-box.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .status-box h2 {
            margin-bottom: 15px;
            color: #333;
            font-size: 1.3em;
        }
        
        .config-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .config-item:last-child {
            border-bottom: none;
        }
        
        .config-label {
            font-weight: 600;
        }
        
        .config-value {
            text-align: right;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge.success {
            background: #28a745;
            color: white;
        }
        
        .badge.error {
            background: #dc3545;
            color: white;
        }
        
        .badge.warning {
            background: #ffc107;
            color: #333;
        }
        
        .cron-list {
            list-style: none;
        }
        
        .cron-item {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        
        .cron-item h3 {
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .cron-meta {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 8px;
        }
        
        .cron-url {
            background: white;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85em;
            word-break: break-all;
            margin-top: 10px;
            border: 1px solid #dee2e6;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #17a2b8;
            color: #0c5460;
        }
        
        .alert strong {
            display: block;
            margin-bottom: 5px;
        }
        
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔄 Cron Jobs Status</h1>
            <p>Stato della configurazione dei cron job per EasyVol</p>
        </header>
        
        <div class="content">
            <?php if (!$webAllowed): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Web execution disabled</strong>
                    L'esecuzione web dei cron job è disabilitata. Abilita <code>cron.allow_web</code> in config.php.
                </div>
            <?php endif; ?>
            
            <?php if (!$tokenConfigured): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Token non configurato</strong>
                    Il token segreto per i cron job non è configurato. Aggiungi <code>cron.secret_token</code> in config.php.
                    <br><br>
                    Genera un token sicuro con: <code>openssl rand -hex 32</code>
                </div>
            <?php endif; ?>
            
            <div class="status-box <?php echo ($tokenConfigured && $webAllowed) ? 'success' : 'error'; ?>">
                <h2>Configurazione Generale</h2>
                <div class="config-item">
                    <span class="config-label">Secret Token</span>
                    <span class="config-value">
                        <?php if ($tokenConfigured): ?>
                            <span class="badge success">✓ Configurato</span>
                        <?php else: ?>
                            <span class="badge error">✗ Non configurato</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="config-item">
                    <span class="config-label">Esecuzione Web (HTTPS)</span>
                    <span class="config-value">
                        <?php if ($webAllowed): ?>
                            <span class="badge success">✓ Abilitata</span>
                        <?php else: ?>
                            <span class="badge error">✗ Disabilitata</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="config-item">
                    <span class="config-label">Esecuzione CLI</span>
                    <span class="config-value">
                        <?php if ($cliAllowed): ?>
                            <span class="badge success">✓ Abilitata</span>
                        <?php else: ?>
                            <span class="badge warning">Disabilitata</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="config-item">
                    <span class="config-label">IP Whitelist</span>
                    <span class="config-value">
                        <?php if ($hasIpWhitelist): ?>
                            <span class="badge warning">Attiva (<?php echo count($cronConfig['allowed_ips']); ?> IP)</span>
                        <?php else: ?>
                            <span class="badge success">Tutti gli IP</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <?php if ($tokenConfigured && $webAllowed): ?>
                <div class="alert alert-info">
                    <strong>ℹ️ Configurazione completata</strong>
                    I cron job sono pronti per essere configurati. Copia gli URL qui sotto e configurali nel pannello di controllo del tuo hosting.
                </div>
            <?php endif; ?>
            
            <h2 style="margin-bottom: 20px;">Cron Jobs Disponibili</h2>
            
            <ul class="cron-list">
                <?php foreach ($cronJobs as $key => $job): ?>
                    <li class="cron-item">
                        <h3><?php echo htmlspecialchars($job['name']); ?></h3>
                        <div class="cron-meta">
                            <strong>Frequenza:</strong> <?php echo htmlspecialchars($job['frequency']); ?><br>
                            <strong>Descrizione:</strong> <?php echo htmlspecialchars($job['description']); ?>
                        </div>
                        
                        <?php if ($tokenConfigured && $webAllowed): ?>
                            <div style="margin-top: 15px;">
                                <strong>URL per esecuzione HTTPS:</strong>
                                <div class="cron-url">
                                    <?php 
                                    $baseUrl = $config['app']['url'] ?? 'https://tuosito.com';
                                    $url = $baseUrl . '/public/cron/' . $key . '.php?token=***TOKEN_NASCOSTO***';
                                    echo htmlspecialchars($url); 
                                    ?>
                                </div>
                                <small style="color: #6c757d;">
                                    Sostituisci ***TOKEN_NASCOSTO*** con il tuo token segreto configurato in config.php
                                </small>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px;">
                <h3 style="margin-bottom: 15px;">📚 Documentazione</h3>
                <p>Per istruzioni complete sulla configurazione dei cron job, consulta:</p>
                <ul style="margin: 15px 0; padding-left: 20px;">
                    <li><a href="README.md" class="btn">README - Cron Jobs Web</a></li>
                    <li style="margin-top: 10px;"><a href="../../cron/README.md" class="btn">README - Cron Jobs CLI</a></li>
                </ul>
            </div>
        </div>
        
        <footer>
            <p>EasyVol - Sistema Gestionale per Associazioni di Volontariato</p>
            <p>Per supporto, consulta la documentazione o apri una issue su GitHub</p>
        </footer>
    </div>
</body>
</html>
