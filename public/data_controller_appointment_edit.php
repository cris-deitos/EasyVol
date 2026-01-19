<?php
/**
 * Gestione Nomine Responsabili Trattamento Dati - Modifica/Crea
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\GdprController;
use EasyVol\Utils\AutoLogger;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$appointmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $appointmentId > 0;

if (!$app->checkPermission('gdpr_compliance', 'manage_appointments')) {
    die('Accesso negato');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && $app->checkPermission('gdpr_compliance', 'manage_appointments')) {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        header('Location: data_controller_appointments.php?error=csrf');
        exit;
    }
    $deleteId = intval($_POST['delete_id']);
    $db = $app->getDb();
    $controller = new GdprController($db, $app->getConfig());
    if ($controller->deleteAppointment($deleteId, $app->getUserId())) {
        header('Location: data_controller_appointments.php?success=deleted');
        exit;
    }
}

AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new GdprController($db, $config);

$appointment = [];
$errors = [];
$success = false;

if ($isEdit) {
    $appointment = $controller->getAppointment($appointmentId);
    if (!$appointment) {
        header('Location: data_controller_appointments.php?error=not_found');
        exit;
    }
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'user_id' => intval($_POST['user_id'] ?? 0),
            'appointment_type' => $_POST['appointment_type'] ?? 'authorized_person',
            'appointment_date' => $_POST['appointment_date'] ?? date('Y-m-d'),
            'revocation_date' => !empty($_POST['revocation_date']) ? $_POST['revocation_date'] : null,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'scope' => trim($_POST['scope'] ?? ''),
            'responsibilities' => trim($_POST['responsibilities'] ?? ''),
            'data_categories_access' => trim($_POST['data_categories_access'] ?? ''),
            'appointment_document_path' => trim($_POST['appointment_document_path'] ?? ''),
            'training_completed' => !empty($_POST['training_completed']) ? 1 : 0,
            'training_date' => !empty($_POST['training_date']) ? $_POST['training_date'] : null,
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->updateAppointment($appointmentId, $data, $app->getUserId());
            } else {
                $result = $controller->createAppointment($data, $app->getUserId());
                $appointmentId = $result;
            }
            
            if ($result) {
                header('Location: data_controller_appointments.php?success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Get all users
$users = $db->fetchAll("SELECT u.id, u.username, u.email, u.member_id,
                        COALESCE(CONCAT(m.first_name, ' ', m.last_name), u.username) as display_name
                        FROM users u
                        LEFT JOIN members m ON u.member_id = m.id
                        WHERE u.is_active = 1
                        ORDER BY display_name");

$pageTitle = $isEdit ? 'Modifica Nomina Responsabile' : 'Nuova Nomina Responsabile';
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
                        <a href="data_controller_appointments.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5>Errori:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php echo CsrfProtection::getHiddenField(); ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="user_id" class="form-label">Utente *</label>
                                    <select class="form-select" id="user_id" name="user_id" required>
                                        <option value="">Seleziona utente...</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                <?php echo ($appointment['user_id'] ?? 0) == $user['id'] ? 'selected' : ''; ?>
                                                data-member-id="<?php echo $user['member_id'] ?? ''; ?>">
                                                <?php echo htmlspecialchars($user['display_name']); ?>
                                                <?php if (!$user['member_id']): ?>
                                                    <em>(senza socio collegato)</em>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        <i class="bi bi-info-circle"></i> Per generare il documento di nomina, l'utente deve essere collegato a un socio
                                    </small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="appointment_type" class="form-label">Tipo Nomina *</label>
                                    <select class="form-select" id="appointment_type" name="appointment_type" required>
                                        <option value="data_controller" <?php echo ($appointment['appointment_type'] ?? '') === 'data_controller' ? 'selected' : ''; ?>>Titolare del Trattamento</option>
                                        <option value="data_processor" <?php echo ($appointment['appointment_type'] ?? '') === 'data_processor' ? 'selected' : ''; ?>>Responsabile del Trattamento</option>
                                        <option value="dpo" <?php echo ($appointment['appointment_type'] ?? '') === 'dpo' ? 'selected' : ''; ?>>Data Protection Officer (DPO)</option>
                                        <option value="authorized_person" <?php echo ($appointment['appointment_type'] ?? 'authorized_person') === 'authorized_person' ? 'selected' : ''; ?>>Persona Autorizzata</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="appointment_date" class="form-label">Data Nomina *</label>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                           value="<?php echo htmlspecialchars($appointment['appointment_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="revocation_date" class="form-label">Data Revoca</label>
                                    <input type="date" class="form-control" id="revocation_date" name="revocation_date" 
                                           value="<?php echo htmlspecialchars($appointment['revocation_date'] ?? ''); ?>">
                                    <small class="form-text text-muted">Lasciare vuoto se la nomina è ancora attiva</small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                               <?php echo ($appointment['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Nomina Attiva
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="scope" class="form-label">Ambito di Competenza</label>
                                    <textarea class="form-control" id="scope" name="scope" rows="3"><?php echo htmlspecialchars($appointment['scope'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Descrivere l'ambito in cui il responsabile opera (es: "Gestione soci e volontari")</small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="responsibilities" class="form-label">Responsabilità Specifiche</label>
                                    <textarea class="form-control" id="responsibilities" name="responsibilities" rows="4"><?php echo htmlspecialchars($appointment['responsibilities'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Elencare le responsabilità assegnate al responsabile del trattamento</small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="data_categories_access" class="form-label">Categorie di Dati Accessibili</label>
                                    <textarea class="form-control" id="data_categories_access" name="data_categories_access" rows="3"><?php echo htmlspecialchars($appointment['data_categories_access'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Indicare quali categorie di dati personali può trattare (es: "Dati anagrafici, contatti, formazione")</small>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="training_completed" name="training_completed" value="1"
                                               <?php echo ($appointment['training_completed'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="training_completed">
                                            Formazione GDPR Completata
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="training_date" class="form-label">Data Formazione</label>
                                    <input type="date" class="form-control" id="training_date" name="training_date" 
                                           value="<?php echo htmlspecialchars($appointment['training_date'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="notes" class="form-label">Note</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="data_controller_appointments.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva Nomina
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($isEdit && $appointment): ?>
                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Azioni</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($app->checkPermission('gdpr_compliance', 'print_appointment')): ?>
                            <a href="data_controller_appointment_print.php?id=<?php echo $appointmentId; ?>" 
                               class="btn btn-success" target="_blank">
                                <i class="bi bi-printer"></i> Stampa Nomina (PDF)
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($app->checkPermission('gdpr_compliance', 'manage_appointments')): ?>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Sei sicuro di voler eliminare questa nomina?')">
                                <?php echo CsrfProtection::getHiddenField(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo $appointmentId; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Elimina Nomina
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
