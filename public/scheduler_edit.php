<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\SchedulerController;
use EasyVol\Controllers\UserController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$isEdit = isset($_GET['id']);
$requiredPermission = $isEdit ? 'edit' : 'create';

if (!$app->checkPermission('scheduler', $requiredPermission)) {
    die('Accesso negato');
}

$controller = new SchedulerController($app->getDb(), $app->getConfig());
$userController = new UserController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

$item = null;
$errors = [];
$success = false;

// Load item if editing
if ($isEdit) {
    $item = $controller->get($_GET['id']);
    if (!$item) {
        die('Scadenza non trovata');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!$csrf->validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'due_date' => $_POST['due_date'] ?? '',
            'category' => trim($_POST['category'] ?? ''),
            'priority' => $_POST['priority'] ?? 'media',
            'status' => $_POST['status'] ?? 'in_attesa',
            'reminder_days' => (int)($_POST['reminder_days'] ?? 7),
            'assigned_to' => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
            'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            'recurrence_type' => !empty($_POST['recurrence_type']) ? $_POST['recurrence_type'] : null,
            'recurrence_end_date' => !empty($_POST['recurrence_end_date']) ? $_POST['recurrence_end_date'] : null
        ];
        
        // Process recipients
        $recipients = [];
        if (!empty($_POST['recipient_users'])) {
            foreach ($_POST['recipient_users'] as $userId) {
                if (!empty($userId)) {
                    $recipients[] = [
                        'type' => 'user',
                        'user_id' => (int)$userId,
                        'member_id' => null,
                        'external_email' => null
                    ];
                }
            }
        }
        if (!empty($_POST['recipient_members'])) {
            foreach ($_POST['recipient_members'] as $memberId) {
                if (!empty($memberId)) {
                    $recipients[] = [
                        'type' => 'member',
                        'user_id' => null,
                        'member_id' => (int)$memberId,
                        'external_email' => null
                    ];
                }
            }
        }
        if (!empty($_POST['external_emails'])) {
            $emails = array_filter(array_map('trim', explode(',', $_POST['external_emails'])));
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = [
                        'type' => 'external',
                        'user_id' => null,
                        'member_id' => null,
                        'external_email' => $email
                    ];
                }
            }
        }
        $data['recipients'] = $recipients;
        
        // Validation
        if (empty($data['title'])) {
            $errors[] = 'Il titolo è obbligatorio';
        }
        if (empty($data['due_date'])) {
            $errors[] = 'La data di scadenza è obbligatoria';
        }
        
        // Validate recurring deadline fields
        if ($data['is_recurring']) {
            if (empty($data['recurrence_type'])) {
                $errors[] = 'Il tipo di ricorrenza è obbligatorio per le scadenze ricorrenti';
            }
        }
        
        if (empty($errors)) {
            if ($isEdit) {
                $result = $controller->update($_GET['id'], $data, $app->getUserId());
            } else {
                $result = $controller->create($data, $app->getUserId());
            }
            
            if ($result) {
                $success = true;
                header('Location: scheduler.php?success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        }
    }
}

// Get users for assignment dropdown
$users = $userController->index([], 1, 100);

// Get members for recipients dropdown
use EasyVol\Controllers\MemberController;
$memberController = new MemberController($app->getDb(), $app->getConfig());
$members = $memberController->index(['member_status' => 'attivo'], 1, 500);

$pageTitle = $isEdit ? 'Modifica Scadenza' : 'Nuova Scadenza';
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
    <?php include '../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-calendar-check"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="scheduler.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna alla lista
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong>Errore:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Scadenza salvata con successo!
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Titolo *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($item['title'] ?? $_POST['title'] ?? ''); ?>" 
                                           required maxlength="255">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="due_date" class="form-label">Data Scadenza *</label>
                                    <input type="date" class="form-control" id="due_date" name="due_date" 
                                           value="<?php echo htmlspecialchars($item['due_date'] ?? $_POST['due_date'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4"><?php echo htmlspecialchars($item['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="category" class="form-label">Categoria</label>
                                    <input type="text" class="form-control" id="category" name="category" 
                                           value="<?php echo htmlspecialchars($item['category'] ?? $_POST['category'] ?? ''); ?>" 
                                           maxlength="100"
                                           placeholder="Es: Assicurazione, Revisione...">
                                    <small class="form-text text-muted">Opzionale</small>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="priority" class="form-label">Priorità</label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <?php
                                        $currentPriority = $item['priority'] ?? $_POST['priority'] ?? 'media';
                                        $priorities = ['bassa' => 'Bassa', 'media' => 'Media', 'alta' => 'Alta', 'urgente' => 'Urgente'];
                                        foreach ($priorities as $value => $label):
                                        ?>
                                            <option value="<?php echo $value; ?>" <?php echo $currentPriority === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="status" class="form-label">Stato</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <?php
                                        $currentStatus = $item['status'] ?? $_POST['status'] ?? 'in_attesa';
                                        $statuses = ['in_attesa' => 'In Attesa', 'in_corso' => 'In Corso', 'completato' => 'Completato', 'scaduto' => 'Scaduto'];
                                        foreach ($statuses as $value => $label):
                                        ?>
                                            <option value="<?php echo $value; ?>" <?php echo $currentStatus === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="reminder_days" class="form-label">Promemoria (giorni prima)</label>
                                    <input type="number" class="form-control" id="reminder_days" name="reminder_days" 
                                           value="<?php echo htmlspecialchars($item['reminder_days'] ?? $_POST['reminder_days'] ?? 7); ?>" 
                                           min="0" max="365">
                                    <small class="form-text text-muted">0 = nessun promemoria</small>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3"><i class="bi bi-arrow-repeat"></i> Ricorrenza Scadenza</h5>
                            <p class="text-muted small">Configura la scadenza per ripetersi automaticamente nel tempo.</p>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" 
                                               value="1" <?php echo (!empty($item['is_recurring']) || !empty($_POST['is_recurring'])) ? 'checked' : ''; ?>
                                               onchange="toggleRecurrenceFields()">
                                        <label class="form-check-label" for="is_recurring">
                                            <strong>Abilita ricorrenza automatica</strong>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Se abilitata, la scadenza si ripeterà automaticamente secondo le impostazioni sotto.</small>
                                </div>
                            </div>
                            
                            <div id="recurrence_fields" style="<?php echo (empty($item['is_recurring']) && empty($_POST['is_recurring'])) ? 'display: none;' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="recurrence_type" class="form-label">Tipo Ricorrenza *</label>
                                        <select class="form-select" id="recurrence_type" name="recurrence_type">
                                            <option value="">Seleziona tipo ricorrenza</option>
                                            <?php
                                            $currentRecurrenceType = $item['recurrence_type'] ?? $_POST['recurrence_type'] ?? '';
                                            $recurrenceTypes = [
                                                'weekly' => 'Settimanale - Stesso giorno ogni settimana',
                                                'monthly' => 'Mensile - Stesso giorno ogni mese',
                                                'yearly' => 'Annuale - Una volta l\'anno nello stesso giorno'
                                            ];
                                            foreach ($recurrenceTypes as $value => $label):
                                            ?>
                                                <option value="<?php echo $value; ?>" <?php echo $currentRecurrenceType === $value ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">Frequenza con cui la scadenza si ripete</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="recurrence_end_date" class="form-label">Data Fine Ricorrenza</label>
                                        <input type="date" class="form-control" id="recurrence_end_date" name="recurrence_end_date" 
                                               value="<?php echo htmlspecialchars($item['recurrence_end_date'] ?? $_POST['recurrence_end_date'] ?? ''); ?>">
                                        <small class="form-text text-muted">Opzionale. Se non specificata, la ricorrenza continua a tempo indeterminato</small>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Come funziona la ricorrenza:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Le nuove scadenze verranno generate automaticamente in base alla data iniziale e al tipo di ricorrenza</li>
                                        <li>Ogni occorrenza eredita le stesse impostazioni (priorità, categoria, destinatari, etc.)</li>
                                        <li>Se imposti una data di fine, non verranno generate scadenze oltre quella data</li>
                                        <li>Se non imposti una data di fine, la ricorrenza continua finché non elimini la scadenza principale</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="mb-3">
                                <label for="assigned_to" class="form-label">Assegnato a</label>
                                <select class="form-select" id="assigned_to" name="assigned_to">
                                    <option value="">Nessuno</option>
                                    <?php
                                    $currentAssignedTo = $item['assigned_to'] ?? $_POST['assigned_to'] ?? null;
                                    foreach ($users as $user):
                                    ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo $currentAssignedTo == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3"><i class="bi bi-envelope"></i> Destinatari Email Promemoria</h5>
                            <p class="text-muted small">Seleziona gli utenti o i soci che riceveranno una email di promemoria per questa scadenza, oppure inserisci email esterne.</p>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="recipient_users" class="form-label">Utenti Sistema</label>
                                    <select class="form-select" id="recipient_users" name="recipient_users[]" multiple size="5">
                                        <?php
                                        $selectedUserRecipients = [];
                                        if (isset($item['recipients'])) {
                                            foreach ($item['recipients'] as $r) {
                                                if ($r['recipient_type'] === 'user') {
                                                    $selectedUserRecipients[] = $r['user_id'];
                                                }
                                            }
                                        }
                                        foreach ($users as $user):
                                        ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                    <?php echo in_array($user['id'], $selectedUserRecipients) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Tieni premuto Ctrl (Cmd su Mac) per selezionare più utenti</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="recipient_members" class="form-label">Soci</label>
                                    <select class="form-select" id="recipient_members" name="recipient_members[]" multiple size="5">
                                        <?php
                                        $selectedMemberRecipients = [];
                                        if (isset($item['recipients'])) {
                                            foreach ($item['recipients'] as $r) {
                                                if ($r['recipient_type'] === 'member') {
                                                    $selectedMemberRecipients[] = $r['member_id'];
                                                }
                                            }
                                        }
                                        foreach ($members as $member):
                                        ?>
                                            <option value="<?php echo $member['id']; ?>" 
                                                    <?php echo in_array($member['id'], $selectedMemberRecipients) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($member['last_name'] . ' ' . $member['first_name']); ?>
                                                (<?php echo htmlspecialchars($member['registration_number']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Tieni premuto Ctrl (Cmd su Mac) per selezionare più soci</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="external_emails" class="form-label">Email Esterne</label>
                                <input type="text" class="form-control" id="external_emails" name="external_emails" 
                                       value="<?php 
                                       if (isset($item['recipients'])) {
                                           $externalEmails = [];
                                           foreach ($item['recipients'] as $r) {
                                               if ($r['recipient_type'] === 'external') {
                                                   $externalEmails[] = $r['external_email'];
                                               }
                                           }
                                           echo htmlspecialchars(implode(', ', $externalEmails));
                                       }
                                       ?>" 
                                       placeholder="email1@esempio.com, email2@esempio.com">
                                <small class="form-text text-muted">Inserisci più email separate da virgola</small>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="scheduler.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRecurrenceFields() {
            const isRecurring = document.getElementById('is_recurring').checked;
            const recurrenceFields = document.getElementById('recurrence_fields');
            
            if (isRecurring) {
                recurrenceFields.style.display = 'block';
                document.getElementById('recurrence_type').required = true;
            } else {
                recurrenceFields.style.display = 'none';
                document.getElementById('recurrence_type').required = false;
                // Clear values when disabled
                document.getElementById('recurrence_type').value = '';
                document.getElementById('recurrence_end_date').value = '';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleRecurrenceFields();
        });
    </script>
</body>
</html>
