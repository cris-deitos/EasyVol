<?php
/**
 * Telegram Notification Recipients Management
 * 
 * Page to configure notification recipients for Telegram
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
if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();

$errors = [];
$success = false;
$successMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'add_recipient') {
                $configId = intval($_POST['config_id'] ?? 0);
                $recipientType = $_POST['recipient_type'] ?? '';
                $memberId = !empty($_POST['member_id']) ? intval($_POST['member_id']) : null;
                $telegramGroupId = trim($_POST['telegram_group_id'] ?? '');
                $telegramGroupName = trim($_POST['telegram_group_name'] ?? '');
                
                // Validation
                if ($configId <= 0) {
                    $errors[] = 'Tipo di azione non valido';
                }
                if (!in_array($recipientType, ['member', 'group'])) {
                    $errors[] = 'Tipo di destinatario non valido';
                }
                if ($recipientType === 'member' && (!$memberId || $memberId <= 0)) {
                    $errors[] = 'Socio non selezionato';
                }
                if ($recipientType === 'group' && empty($telegramGroupId)) {
                    $errors[] = 'ID gruppo Telegram non fornito';
                }
                
                // Check if member has Telegram ID
                if ($recipientType === 'member' && $memberId > 0) {
                    $sql = "SELECT COUNT(*) as count FROM member_contacts 
                            WHERE member_id = ? AND contact_type = 'telegram_id'";
                    $result = $db->fetchOne($sql, [$memberId]);
                    if ($result['count'] == 0) {
                        $errors[] = 'Il socio selezionato non ha un ID Telegram nei contatti';
                    }
                }
                
                if (empty($errors)) {
                    $sql = "INSERT INTO telegram_notification_recipients 
                            (config_id, recipient_type, member_id, telegram_group_id, telegram_group_name) 
                            VALUES (?, ?, ?, ?, ?)";
                    $db->execute($sql, [
                        $configId,
                        $recipientType,
                        $memberId,
                        $recipientType === 'group' ? $telegramGroupId : null,
                        $recipientType === 'group' ? $telegramGroupName : null
                    ]);
                    
                    $success = true;
                    $successMessage = 'Destinatario aggiunto con successo';
                }
            } elseif ($action === 'delete_recipient') {
                $recipientId = intval($_POST['recipient_id'] ?? 0);
                if ($recipientId > 0) {
                    $sql = "DELETE FROM telegram_notification_recipients WHERE id = ?";
                    $db->execute($sql, [$recipientId]);
                    $success = true;
                    $successMessage = 'Destinatario rimosso con successo';
                }
            } elseif ($action === 'toggle_action') {
                $configId = intval($_POST['config_id'] ?? 0);
                if ($configId > 0) {
                    $sql = "UPDATE telegram_notification_config 
                            SET is_enabled = NOT is_enabled 
                            WHERE id = ?";
                    $db->execute($sql, [$configId]);
                    $success = true;
                    $successMessage = 'Stato notifica aggiornato';
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Errore: ' . $e->getMessage();
        }
    }
}

// Load notification configurations
$sql = "SELECT * FROM telegram_notification_config ORDER BY action_type";
$notificationConfigs = $db->fetchAll($sql);

// Load all recipients grouped by config
$recipients = [];
$sql = "SELECT tnr.*, 
               m.first_name, m.last_name, m.registration_number,
               mc.value as telegram_id
        FROM telegram_notification_recipients tnr
        LEFT JOIN members m ON tnr.member_id = m.id
        LEFT JOIN member_contacts mc ON m.id = mc.member_id AND mc.contact_type = 'telegram_id'
        ORDER BY tnr.config_id, tnr.id";
$allRecipients = $db->fetchAll($sql);

foreach ($allRecipients as $recipient) {
    $configId = $recipient['config_id'];
    if (!isset($recipients[$configId])) {
        $recipients[$configId] = [];
    }
    $recipients[$configId][] = $recipient;
}

// Load members with Telegram ID for dropdown
$sql = "SELECT DISTINCT m.id, m.registration_number, m.first_name, m.last_name, mc.value as telegram_id
        FROM members m
        INNER JOIN member_contacts mc ON m.id = mc.member_id
        WHERE mc.contact_type = 'telegram_id' AND m.member_status = 'attivo'
        ORDER BY m.last_name, m.first_name";
$membersWithTelegram = $db->fetchAll($sql);

// Action type labels in Italian
$actionLabels = [
    'member_application' => 'Nuova domanda iscrizione socio',
    'junior_application' => 'Nuova domanda iscrizione cadetto',
    'fee_payment' => 'Nuovo pagamento quota associativa',
    'vehicle_departure' => 'Uscita mezzo',
    'vehicle_return' => 'Rientro mezzo',
    'event_created' => 'Nuovo evento/intervento',
    'scheduler_expiry' => 'Scadenze scadenzario',
    'vehicle_expiry' => 'Scadenze revisioni/assicurazioni mezzi',
    'license_expiry' => 'Scadenze patenti soci',
    'qualification_expiry' => 'Scadenze qualifiche soci',
    'course_expiry' => 'Scadenze corsi soci'
];

$pageTitle = 'Gestione Destinatari Notifiche Telegram';
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
                        <a href="settings.php?tab=telegram" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($successMessage); ?>
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
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <strong>Configurazione Destinatari</strong><br>
                    Per ogni tipo di notifica, puoi aggiungere uno o più destinatari. I destinatari possono essere:
                    <ul class="mb-0 mt-2">
                        <li><strong>Soci</strong>: Seleziona soci che hanno un ID Telegram nei loro contatti</li>
                        <li><strong>Gruppi</strong>: Inserisci l'ID di un gruppo Telegram (numero negativo, es: -1001234567890)</li>
                    </ul>
                </div>
                
                <?php foreach ($notificationConfigs as $config): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-bell"></i>
                                <?php echo htmlspecialchars($actionLabels[$config['action_type']] ?? $config['action_type']); ?>
                            </h5>
                            <form method="POST" class="d-inline">
                                <?php echo CsrfProtection::getHiddenField(); ?>
                                <input type="hidden" name="action" value="toggle_action">
                                <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $config['is_enabled'] ? 'btn-success' : 'btn-secondary'; ?>">
                                    <i class="bi bi-<?php echo $config['is_enabled'] ? 'check-circle' : 'x-circle'; ?>"></i>
                                    <?php echo $config['is_enabled'] ? 'Abilitato' : 'Disabilitato'; ?>
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <!-- Recipients List -->
                            <?php if (isset($recipients[$config['id']]) && count($recipients[$config['id']]) > 0): ?>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Destinatario</th>
                                                <th>ID Telegram</th>
                                                <th style="width: 100px;">Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recipients[$config['id']] as $recipient): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($recipient['recipient_type'] === 'member'): ?>
                                                            <span class="badge bg-primary">Socio</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">Gruppo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($recipient['recipient_type'] === 'member'): ?>
                                                            <?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?>
                                                            (<?php echo htmlspecialchars($recipient['registration_number']); ?>)
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($recipient['telegram_group_name'] ?: 'Gruppo Telegram'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($recipient['telegram_id'] ?: $recipient['telegram_group_id']); ?></code>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Sei sicuro di voler rimuovere questo destinatario?');">
                                                            <?php echo CsrfProtection::getHiddenField(); ?>
                                                            <input type="hidden" name="action" value="delete_recipient">
                                                            <input type="hidden" name="recipient_id" value="<?php echo $recipient['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Nessun destinatario configurato</p>
                            <?php endif; ?>
                            
                            <!-- Add Recipient Form -->
                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#addRecipient<?php echo $config['id']; ?>">
                                <i class="bi bi-plus-circle"></i> Aggiungi Destinatario
                            </button>
                            
                            <div class="collapse mt-3" id="addRecipient<?php echo $config['id']; ?>">
                                <form method="POST" class="border p-3 rounded bg-light">
                                    <?php echo CsrfProtection::getHiddenField(); ?>
                                    <input type="hidden" name="action" value="add_recipient">
                                    <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tipo Destinatario *</label>
                                        <select class="form-select" name="recipient_type" id="recipientType<?php echo $config['id']; ?>" required>
                                            <option value="">-- Seleziona --</option>
                                            <option value="member">Socio</option>
                                            <option value="group">Gruppo Telegram</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3 recipient-member" id="memberSelect<?php echo $config['id']; ?>" style="display: none;">
                                        <label class="form-label">Seleziona Socio *</label>
                                        <select class="form-select" name="member_id">
                                            <option value="">-- Seleziona socio --</option>
                                            <?php foreach ($membersWithTelegram as $member): ?>
                                                <option value="<?php echo $member['id']; ?>">
                                                    <?php echo htmlspecialchars($member['last_name'] . ' ' . $member['first_name']); ?>
                                                    (<?php echo htmlspecialchars($member['registration_number']); ?>) -
                                                    ID: <?php echo htmlspecialchars($member['telegram_id']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="recipient-group" id="groupFields<?php echo $config['id']; ?>" style="display: none;">
                                        <div class="mb-3">
                                            <label class="form-label">ID Gruppo Telegram *</label>
                                            <input type="text" class="form-control" name="telegram_group_id" 
                                                   placeholder="-1001234567890">
                                            <div class="form-text">L'ID gruppo è un numero negativo. Usa @userinfobot per ottenerlo.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nome Gruppo (opzionale)</label>
                                            <input type="text" class="form-control" name="telegram_group_name" 
                                                   placeholder="Nome descrittivo del gruppo">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus"></i> Aggiungi
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" 
                                            data-bs-target="#addRecipient<?php echo $config['id']; ?>">
                                        Annulla
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle recipient type change for all forms
        document.querySelectorAll('[id^="recipientType"]').forEach(select => {
            const configId = select.id.replace('recipientType', '');
            const memberSelect = document.getElementById('memberSelect' + configId);
            const groupFields = document.getElementById('groupFields' + configId);
            
            select.addEventListener('change', function() {
                if (this.value === 'member') {
                    memberSelect.style.display = 'block';
                    groupFields.style.display = 'none';
                    memberSelect.querySelector('select').required = true;
                    groupFields.querySelectorAll('input').forEach(i => i.required = false);
                } else if (this.value === 'group') {
                    memberSelect.style.display = 'none';
                    groupFields.style.display = 'block';
                    memberSelect.querySelector('select').required = false;
                    groupFields.querySelector('input[name="telegram_group_id"]').required = true;
                } else {
                    memberSelect.style.display = 'none';
                    groupFields.style.display = 'none';
                    memberSelect.querySelector('select').required = false;
                    groupFields.querySelectorAll('input').forEach(i => i.required = false);
                }
            });
        });
    });
    </script>
</body>
</html>
