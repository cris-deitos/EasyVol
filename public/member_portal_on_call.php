<?php
/**
 * Member Portal - On-Call Schedule Management
 * 
 * Public page for members to manage their on-call availability
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\MemberPortalController;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance(); // Public page - no authentication required

// Redirect to install if not configured
if (!$app->isInstalled()) {
    header("Location: install.php");
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MemberPortalController($db, $config);

// Log page access
AutoLogger::logPageAccess();

$error = '';
$success = '';
$isVerified = false;
$memberId = null;
$memberData = null;

// Check if member has completed verification via member_portal_verify
if (isset($_SESSION['portal_verified']) && isset($_SESSION['portal_member_id'])) {
    $isVerified = true;
    $memberId = $_SESSION['portal_member_id'];
} else {
    // Allow direct login with registration number and last name
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
            $error = 'Token di sicurezza non valido. Ricarica la pagina e riprova.';
        } else {
            $registrationNumber = trim($_POST['registration_number'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            
            if (empty($registrationNumber) || empty($lastName)) {
                $error = 'Inserisci matricola e cognome.';
            } else {
                // Verify member
                $member = $controller->verifyMember($registrationNumber, $lastName);
                
                if (!$member) {
                    $error = 'Matricola o cognome non corretto, oppure socio non attivo.';
                } else {
                    // Set session for this member (simplified login without code)
                    $_SESSION['portal_on_call_member_id'] = $member['id'];
                    $memberId = $member['id'];
                    $isVerified = true;
                }
            }
        }
    }
}

// If verified via on-call session
if (!$isVerified && isset($_SESSION['portal_on_call_member_id'])) {
    $isVerified = true;
    $memberId = $_SESSION['portal_on_call_member_id'];
}

// Handle on-call actions
if ($isVerified && $memberId) {
    // Get member basic info
    $memberSql = "SELECT first_name, last_name, badge_number FROM members WHERE id = ?";
    $memberData = $db->fetchOne($memberSql, [$memberId]);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
            $error = 'Token di sicurezza non valido. Ricarica la pagina e riprova.';
        } else {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'add':
                    $startDatetime = trim($_POST['start_datetime'] ?? '');
                    $endDatetime = trim($_POST['end_datetime'] ?? '');
                    $notes = trim($_POST['notes'] ?? '');
                    
                    if (empty($startDatetime) || empty($endDatetime)) {
                        $error = 'Data e ora di inizio e fine sono obbligatorie.';
                    } else {
                        if ($controller->addOnCallSchedule($memberId, $startDatetime, $endDatetime, $notes)) {
                            $success = 'Reperibilità aggiunta con successo!';
                        } else {
                            $error = 'Errore: reperibilità sovrapposta o date non valide.';
                        }
                    }
                    break;
                    
                case 'update':
                    $scheduleId = intval($_POST['schedule_id'] ?? 0);
                    $startDatetime = trim($_POST['start_datetime'] ?? '');
                    $endDatetime = trim($_POST['end_datetime'] ?? '');
                    $notes = trim($_POST['notes'] ?? '');
                    
                    if ($scheduleId <= 0 || empty($startDatetime) || empty($endDatetime)) {
                        $error = 'Parametri non validi.';
                    } else {
                        if ($controller->updateOnCallSchedule($scheduleId, $memberId, $startDatetime, $endDatetime, $notes)) {
                            $success = 'Reperibilità aggiornata con successo!';
                        } else {
                            $error = 'Errore: reperibilità sovrapposta, date non valide, o non hai i permessi.';
                        }
                    }
                    break;
                    
                case 'delete':
                    $scheduleId = intval($_POST['schedule_id'] ?? 0);
                    
                    if ($scheduleId <= 0) {
                        $error = 'ID non valido.';
                    } else {
                        if ($controller->deleteOnCallSchedule($scheduleId, $memberId)) {
                            $success = 'Reperibilità eliminata con successo!';
                        } else {
                            $error = 'Errore: non hai i permessi o reperibilità non trovata.';
                        }
                    }
                    break;
            }
        }
    }
    
    // Get member's on-call schedules
    $pastSchedules = $controller->getOnCallSchedules($memberId, 'past');
    $activeSchedules = $controller->getOnCallSchedules($memberId, 'active');
    $futureSchedules = $controller->getOnCallSchedules($memberId, 'future');
}

$associationName = $config['association']['name'] ?? 'Associazione';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Reperibilità - <?= htmlspecialchars($associationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            padding: 30px 15px;
        }
        
        .header-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .header-card h1 {
            color: #667eea;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 50px 40px;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            background: white;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f4f6f9;
            padding: 20px;
            border-radius: 16px 16px 0 0 !important;
        }
        
        .schedule-item {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            background: white;
        }
        
        .schedule-item.active {
            border-color: #28a745;
            background: #f0fff4;
        }
        
        .schedule-item.future {
            border-color: #ffc107;
            background: #fffbf0;
        }
        
        .schedule-item.past {
            border-color: #6c757d;
            background: #f8f9fa;
            opacity: 0.8;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .logout-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
            <?php if (!$isVerified): ?>
                <!-- Login Form -->
                <div class="login-card">
                    <div class="text-center mb-4">
                        <h2 style="color: #667eea;"><i class="bi bi-calendar-check"></i> Gestione Reperibilità</h2>
                        <p class="text-muted"><?= htmlspecialchars($associationName) ?></p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Inserisci la tua <strong>matricola</strong> e <strong>cognome</strong> per accedere alla gestione delle tue reperibilità.
                    </div>
                    
                    <form method="POST" action="member_portal_on_call.php">
                        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-hash"></i> Matricola</label>
                            <input type="text" class="form-control" name="registration_number" required autofocus
                                   placeholder="Es: 1" value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-person"></i> Cognome</label>
                            <input type="text" class="form-control" name="last_name" required 
                                   placeholder="Il tuo cognome" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Accedi
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="member_portal_verify.php" class="logout-link">
                            <i class="bi bi-arrow-left"></i> Torna al Portale Soci
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- On-Call Management -->
                <div class="header-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1><i class="bi bi-calendar-check"></i> Le Tue Reperibilità</h1>
                            <p class="text-muted mb-0">
                                <?= htmlspecialchars($memberData['first_name'] . ' ' . $memberData['last_name']) ?> 
                                (Mat. <?= htmlspecialchars($memberData['badge_number']) ?>)
                            </p>
                        </div>
                        <a href="?logout=1" class="logout-link" onclick="return confirm('Sei sicuro di voler uscire?');">
                            <i class="bi bi-box-arrow-left"></i> Esci
                        </a>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Add New Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Aggiungi Nuova Reperibilità</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="member_portal_on_call.php">
                            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Data e Ora Inizio <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="start_datetime" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Data e Ora Fine <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="end_datetime" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Note (opzionale)</label>
                                    <textarea class="form-control" name="notes" rows="2" 
                                              placeholder="Es: Disponibile per emergenze, Non disponibile dopo le 22:00..."></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Aggiungi Reperibilità
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Active Schedules -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-broadcast text-success"></i> Reperibilità in Corso</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeSchedules)): ?>
                            <p class="text-muted mb-0">Nessuna reperibilità in corso.</p>
                        <?php else: ?>
                            <?php foreach ($activeSchedules as $schedule): ?>
                                <div class="schedule-item active">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><i class="bi bi-clock"></i> 
                                                <?= date('d/m/Y H:i', strtotime($schedule['start_datetime'])) ?> - 
                                                <?= date('d/m/Y H:i', strtotime($schedule['end_datetime'])) ?>
                                            </strong>
                                            <?php if ($schedule['notes']): ?>
                                                <br><small class="text-muted"><i class="bi bi-sticky"></i> <?= htmlspecialchars($schedule['notes']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="editSchedule(<?= $schedule['id'] ?>, '<?= $schedule['start_datetime'] ?>', '<?= $schedule['end_datetime'] ?>', '<?= htmlspecialchars($schedule['notes'] ?? '', ENT_QUOTES) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="deleteSchedule(<?= $schedule['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Future Schedules -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-plus text-warning"></i> Reperibilità Future</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($futureSchedules)): ?>
                            <p class="text-muted mb-0">Nessuna reperibilità futura programmata.</p>
                        <?php else: ?>
                            <?php foreach ($futureSchedules as $schedule): ?>
                                <div class="schedule-item future">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><i class="bi bi-clock"></i> 
                                                <?= date('d/m/Y H:i', strtotime($schedule['start_datetime'])) ?> - 
                                                <?= date('d/m/Y H:i', strtotime($schedule['end_datetime'])) ?>
                                            </strong>
                                            <?php if ($schedule['notes']): ?>
                                                <br><small class="text-muted"><i class="bi bi-sticky"></i> <?= htmlspecialchars($schedule['notes']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="editSchedule(<?= $schedule['id'] ?>, '<?= $schedule['start_datetime'] ?>', '<?= $schedule['end_datetime'] ?>', '<?= htmlspecialchars($schedule['notes'] ?? '', ENT_QUOTES) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="deleteSchedule(<?= $schedule['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Past Schedules -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history text-secondary"></i> Reperibilità Passate</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($pastSchedules)): ?>
                            <p class="text-muted mb-0">Nessuna reperibilità passata.</p>
                        <?php else: ?>
                            <?php foreach ($pastSchedules as $schedule): ?>
                                <div class="schedule-item past">
                                    <div>
                                        <strong><i class="bi bi-clock"></i> 
                                            <?= date('d/m/Y H:i', strtotime($schedule['start_datetime'])) ?> - 
                                            <?= date('d/m/Y H:i', strtotime($schedule['end_datetime'])) ?>
                                        </strong>
                                        <?php if ($schedule['notes']): ?>
                                            <br><small class="text-muted"><i class="bi bi-sticky"></i> <?= htmlspecialchars($schedule['notes']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Nota:</strong> Le reperibilità che inserisci saranno visibili nella Centrale Operativa solo durante il periodo in cui sono attive.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Reperibilità</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="member_portal_on_call.php">
                    <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Data e Ora Inizio <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="start_datetime" id="edit_start_datetime" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data e Ora Fine <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="end_datetime" id="edit_end_datetime" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save"></i> Salva Modifiche
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Form (hidden) -->
    <form method="POST" action="member_portal_on_call.php" id="deleteForm" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="schedule_id" id="delete_schedule_id">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        
        function editSchedule(id, startDatetime, endDatetime, notes) {
            document.getElementById('edit_schedule_id').value = id;
            
            // Convert MySQL datetime to input format
            const start = new Date(startDatetime);
            const end = new Date(endDatetime);
            
            document.getElementById('edit_start_datetime').value = formatDateTimeLocal(start);
            document.getElementById('edit_end_datetime').value = formatDateTimeLocal(end);
            document.getElementById('edit_notes').value = notes || '';
            
            editModal.show();
        }
        
        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }
        
        function deleteSchedule(id) {
            if (confirm('Sei sicuro di voler eliminare questa reperibilità?')) {
                document.getElementById('delete_schedule_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Handle logout
        <?php
        if (isset($_GET['logout'])) {
            unset($_SESSION['portal_on_call_member_id']);
            unset($_SESSION['portal_verified']);
            unset($_SESSION['portal_member_id']);
            echo 'window.location.href = "member_portal_on_call.php";';
        }
        ?>
    </script>
</body>
</html>
