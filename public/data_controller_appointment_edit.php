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
        $appointee_type = $_POST['appointee_type'] ?? 'user';
        
        $data = [
            'user_id' => null,
            'member_id' => null,
            'external_person_name' => null,
            'external_person_surname' => null,
            'external_person_tax_code' => null,
            'external_person_birth_date' => null,
            'external_person_birth_place' => null,
            'external_person_birth_province' => null,
            'external_person_gender' => null,
            'external_person_address' => null,
            'external_person_city' => null,
            'external_person_province' => null,
            'external_person_postal_code' => null,
            'external_person_phone' => null,
            'external_person_email' => null,
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
        
        // Set the appropriate ID based on appointee type
        if ($appointee_type === 'user') {
            $data['user_id'] = intval($_POST['user_id'] ?? 0);
            if ($data['user_id'] == 0) {
                $errors[] = 'Selezionare un utente';
            }
        } elseif ($appointee_type === 'member') {
            $data['member_id'] = intval($_POST['member_id'] ?? 0);
            if ($data['member_id'] == 0) {
                $errors[] = 'Selezionare un socio';
            }
        } elseif ($appointee_type === 'external') {
            $data['external_person_name'] = trim($_POST['external_person_name'] ?? '');
            $data['external_person_surname'] = trim($_POST['external_person_surname'] ?? '');
            $data['external_person_tax_code'] = trim($_POST['external_person_tax_code'] ?? '');
            $data['external_person_birth_date'] = !empty($_POST['external_person_birth_date']) ? $_POST['external_person_birth_date'] : null;
            $data['external_person_birth_place'] = trim($_POST['external_person_birth_place'] ?? '');
            $data['external_person_birth_province'] = trim($_POST['external_person_birth_province'] ?? '');
            $data['external_person_gender'] = $_POST['external_person_gender'] ?? null;
            $data['external_person_address'] = trim($_POST['external_person_address'] ?? '');
            $data['external_person_city'] = trim($_POST['external_person_city'] ?? '');
            $data['external_person_province'] = trim($_POST['external_person_province'] ?? '');
            $data['external_person_postal_code'] = trim($_POST['external_person_postal_code'] ?? '');
            $data['external_person_phone'] = trim($_POST['external_person_phone'] ?? '');
            $data['external_person_email'] = trim($_POST['external_person_email'] ?? '');
            
            if (empty($data['external_person_name']) || empty($data['external_person_surname'])) {
                $errors[] = 'Nome e cognome della persona esterna sono obbligatori';
            }
        }
        
        if (empty($errors)) {
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
}

// Get all users
$users = $db->fetchAll("SELECT u.id, u.username, u.email, u.member_id,
                        COALESCE(CONCAT(m.first_name, ' ', m.last_name), u.username) as display_name
                        FROM users u
                        LEFT JOIN members m ON u.member_id = m.id
                        WHERE u.is_active = 1
                        ORDER BY display_name");

// Get all active members
$members = $db->fetchAll("SELECT id, registration_number, first_name, last_name,
                          CONCAT(first_name, ' ', last_name, ' (', registration_number, ')') as display_name
                          FROM members
                          WHERE member_status = 'attivo'
                          ORDER BY last_name, first_name");

// Determine current appointee type for edit mode
$current_appointee_type = 'user';
if ($isEdit && $appointment) {
    if (!empty($appointment['external_person_name'])) {
        $current_appointee_type = 'external';
    } elseif (!empty($appointment['member_id'])) {
        $current_appointee_type = 'member';
    } elseif (!empty($appointment['user_id'])) {
        $current_appointee_type = 'user';
    }
}

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
                                <div class="col-md-12">
                                    <label class="form-label">Tipo Nominato *</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="appointee_type" id="appointee_user" value="user" 
                                               <?php echo ($current_appointee_type === 'user') ? 'checked' : ''; ?> autocomplete="off">
                                        <label class="btn btn-outline-primary" for="appointee_user">
                                            <i class="bi bi-person-badge"></i> Utente
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="appointee_type" id="appointee_member" value="member" 
                                               <?php echo ($current_appointee_type === 'member') ? 'checked' : ''; ?> autocomplete="off">
                                        <label class="btn btn-outline-primary" for="appointee_member">
                                            <i class="bi bi-person"></i> Socio
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="appointee_type" id="appointee_external" value="external" 
                                               <?php echo ($current_appointee_type === 'external') ? 'checked' : ''; ?> autocomplete="off">
                                        <label class="btn btn-outline-primary" for="appointee_external">
                                            <i class="bi bi-person-check"></i> Persona Esterna
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="bi bi-info-circle"></i> Seleziona se nominare un utente, un socio o una persona esterna
                                    </small>
                                </div>
                            </div>
                            
                            <!-- User Selection -->
                            <div id="user-section" class="row mb-4" style="display: <?php echo ($current_appointee_type === 'user') ? 'flex' : 'none'; ?>;">
                                <div class="col-md-12">
                                    <label for="user_id" class="form-label">Utente *</label>
                                    <select class="form-select" id="user_id" name="user_id">
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
                            </div>
                            
                            <!-- Member Selection -->
                            <div id="member-section" class="row mb-4" style="display: <?php echo ($current_appointee_type === 'member') ? 'flex' : 'none'; ?>;">
                                <div class="col-md-12">
                                    <label for="member_id" class="form-label">Socio *</label>
                                    <select class="form-select" id="member_id" name="member_id">
                                        <option value="">Seleziona socio...</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?php echo $member['id']; ?>" 
                                                <?php echo ($appointment['member_id'] ?? 0) == $member['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($member['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        <i class="bi bi-info-circle"></i> Seleziona un socio che non è già utente del sistema
                                    </small>
                                </div>
                            </div>
                            
                            <!-- External Person Fields -->
                            <div id="external-section" style="display: <?php echo ($current_appointee_type === 'external') ? 'block' : 'none'; ?>;">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="external_person_name" class="form-label">Nome *</label>
                                        <input type="text" class="form-control" id="external_person_name" name="external_person_name" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="external_person_surname" class="form-label">Cognome *</label>
                                        <input type="text" class="form-control" id="external_person_surname" name="external_person_surname" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_surname'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="external_person_tax_code" class="form-label">Codice Fiscale</label>
                                        <input type="text" class="form-control" id="external_person_tax_code" name="external_person_tax_code" 
                                               maxlength="16" value="<?php echo htmlspecialchars($appointment['external_person_tax_code'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="external_person_birth_date" class="form-label">Data di Nascita</label>
                                        <input type="date" class="form-control" id="external_person_birth_date" name="external_person_birth_date" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_birth_date'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="external_person_gender" class="form-label">Genere</label>
                                        <select class="form-select" id="external_person_gender" name="external_person_gender">
                                            <option value="">-</option>
                                            <option value="M" <?php echo ($appointment['external_person_gender'] ?? '') === 'M' ? 'selected' : ''; ?>>M</option>
                                            <option value="F" <?php echo ($appointment['external_person_gender'] ?? '') === 'F' ? 'selected' : ''; ?>>F</option>
                                            <option value="other" <?php echo ($appointment['external_person_gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Altro</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="external_person_birth_place" class="form-label">Luogo di Nascita</label>
                                        <input type="text" class="form-control" id="external_person_birth_place" name="external_person_birth_place" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_birth_place'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="external_person_birth_province" class="form-label">Prov. Nascita</label>
                                        <input type="text" class="form-control" id="external_person_birth_province" name="external_person_birth_province" 
                                               maxlength="2" value="<?php echo htmlspecialchars($appointment['external_person_birth_province'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="external_person_address" class="form-label">Indirizzo</label>
                                        <input type="text" class="form-control" id="external_person_address" name="external_person_address" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_address'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="external_person_city" class="form-label">Città</label>
                                        <input type="text" class="form-control" id="external_person_city" name="external_person_city" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="external_person_province" class="form-label">Provincia</label>
                                        <input type="text" class="form-control" id="external_person_province" name="external_person_province" 
                                               maxlength="2" value="<?php echo htmlspecialchars($appointment['external_person_province'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="external_person_postal_code" class="form-label">CAP</label>
                                        <input type="text" class="form-control" id="external_person_postal_code" name="external_person_postal_code" 
                                               maxlength="10" value="<?php echo htmlspecialchars($appointment['external_person_postal_code'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="external_person_phone" class="form-label">Telefono</label>
                                        <input type="text" class="form-control" id="external_person_phone" name="external_person_phone" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="external_person_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="external_person_email" name="external_person_email" 
                                               value="<?php echo htmlspecialchars($appointment['external_person_email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row mb-4">
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
    <script>
        // Handle appointee type switching
        document.querySelectorAll('input[name="appointee_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const userSection = document.getElementById('user-section');
                const memberSection = document.getElementById('member-section');
                const externalSection = document.getElementById('external-section');
                
                // Hide all sections
                userSection.style.display = 'none';
                memberSection.style.display = 'none';
                externalSection.style.display = 'none';
                
                // Show the selected section
                if (this.value === 'user') {
                    userSection.style.display = 'flex';
                } else if (this.value === 'member') {
                    memberSection.style.display = 'flex';
                } else if (this.value === 'external') {
                    externalSection.style.display = 'block';
                }
            });
        });
    </script>
</body>
</html>
