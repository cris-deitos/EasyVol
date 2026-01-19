<?php
/**
 * Gestione Formazione - Crea/Modifica Corso
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Utils\TrainingCourseTypes;
use EasyVol\Controllers\TrainingController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new TrainingController($db, $config);
$csrf = new CsrfProtection();

$courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $courseId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('training', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('training', 'create')) {
    die('Accesso negato');
}

$course = null;
$errors = [];
$success = false;

if ($isEdit) {
    $course = $controller->get($courseId);
    if (!$course) {
        header('Location: training.php?error=not_found');
        exit;
    }
}

// Gestione form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'course_name' => trim($_POST['course_name'] ?? ''),
            'course_type' => trim($_POST['course_type'] ?? ''),
            'sspc_course_code' => trim($_POST['sspc_course_code'] ?? ''),
            'sspc_edition_code' => trim($_POST['sspc_edition_code'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'instructor' => trim($_POST['instructor'] ?? ''),
            'max_participants' => !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : null,
            'status' => $_POST['status'] ?? 'pianificato'
        ];
        
        // Validazione
        if (empty($data['course_type'])) {
            $errors[] = 'Il tipo di corso è obbligatorio';
        }
        
        // Se course_name è vuoto, usa il nome del tipo corso
        if (empty($data['course_name'])) {
            $data['course_name'] = TrainingCourseTypes::getName($data['course_type']) ?? $data['course_type'];
        }
        
        // Valida che la data di fine non sia precedente alla data di inizio
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $startDate = new \DateTime($data['start_date']);
            $endDate = new \DateTime($data['end_date']);
            if ($startDate > $endDate) {
                $errors[] = 'La data di fine non può essere precedente alla data di inizio';
            }
        }
        
        if (empty($errors)) {
            $userId = $app->getUserId();
            
            if ($isEdit) {
                $result = $controller->update($courseId, $data, $userId);
            } else {
                $result = $controller->create($data, $userId);
                if ($result) {
                    $courseId = $result;
                }
            }
            
            if ($result) {
                $success = true;
                header('Location: training_view.php?id=' . $courseId . '&success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio del corso';
            }
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Corso' : 'Nuovo Corso';
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
                        <a href="<?php echo $isEdit ? 'training_view.php?id=' . $courseId : 'training.php'; ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Errori:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Corso salvato con successo!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="course_type" class="form-label">Tipo Corso *</label>
                                    <select class="form-select" id="course_type" name="course_type" required>
                                        <option value="">Seleziona tipo di corso...</option>
                                        <?php
                                        $courseTypes = TrainingCourseTypes::getAll();
                                        $selectedType = $course['course_type'] ?? $_POST['course_type'] ?? '';
                                        foreach ($courseTypes as $code => $name):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($code); ?>" 
                                                    <?php echo $selectedType === $code ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="course_name" class="form-label">Nome Corso (personalizzato)</label>
                                    <input type="text" class="form-control" id="course_name" name="course_name" 
                                           value="<?php echo htmlspecialchars($course['course_name'] ?? $_POST['course_name'] ?? ''); ?>" 
                                           placeholder="Lascia vuoto per usare il nome standard del tipo corso">
                                    <small class="form-text text-muted">
                                        Compila questo campo solo se vuoi personalizzare il nome del corso. Altrimenti verrà usato il nome del tipo corso selezionato.
                                    </small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="sspc_course_code" class="form-label">Codice Corso SSPC</label>
                                    <input type="text" class="form-control" id="sspc_course_code" name="sspc_course_code" 
                                           value="<?php echo htmlspecialchars($course['sspc_course_code'] ?? $_POST['sspc_course_code'] ?? ''); ?>"
                                           placeholder="Es: A1-2025-001">
                                    <small class="form-text text-muted">
                                        Codice del corso nel Sistema di Supporto alla Protezione Civile
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <label for="sspc_edition_code" class="form-label">Codice Edizione SSPC</label>
                                    <input type="text" class="form-control" id="sspc_edition_code" name="sspc_edition_code" 
                                           value="<?php echo htmlspecialchars($course['sspc_edition_code'] ?? $_POST['sspc_edition_code'] ?? ''); ?>"
                                           placeholder="Es: ED-001">
                                    <small class="form-text text-muted">
                                        Codice dell'edizione nel Sistema di Supporto alla Protezione Civile
                                    </small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($course['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="instructor" class="form-label">Istruttore</label>
                                    <input type="text" class="form-control" id="instructor" name="instructor" 
                                           value="<?php echo htmlspecialchars($course['instructor'] ?? $_POST['instructor'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="location" class="form-label">Luogo</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($course['location'] ?? $_POST['location'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Data Inizio</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($course['start_date'] ?? $_POST['start_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">Data Fine</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($course['end_date'] ?? $_POST['end_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="max_participants" class="form-label">Max Partecipanti</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="<?php echo htmlspecialchars($course['max_participants'] ?? $_POST['max_participants'] ?? ''); ?>" 
                                           min="1" placeholder="Illimitato se vuoto">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Stato</label>
                                    <select class="form-select" id="status" name="status">
                                        <?php
                                        $statuses = [
                                            'pianificato' => 'Pianificato',
                                            'in_corso' => 'In Corso',
                                            'completato' => 'Completato',
                                            'annullato' => 'Annullato'
                                        ];
                                        $selectedStatus = $course['status'] ?? $_POST['status'] ?? 'pianificato';
                                        foreach ($statuses as $value => $label):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                                    <?php echo $selectedStatus === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva Corso
                                </button>
                                <a href="<?php echo $isEdit ? 'training_view.php?id=' . $courseId : 'training.php'; ?>" 
                                   class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annulla
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
