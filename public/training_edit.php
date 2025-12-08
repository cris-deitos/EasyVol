<?php
/**
 * Gestione Formazione - Crea/Modifica Corso
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
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
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
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
            $courseTypes = [
                'A0' => 'A0 Corso informativo rivolto alla cittadinanza',
                'A1' => 'A1 Corso base per volontari operativi di Protezione Civile',
                'A2-01' => 'A2-01 ATTIVITA\' LOGISTICO GESTIONALI',
                'A2-02' => 'A2-02 OPERATORE SEGRETERIA',
                'A2-03' => 'A2-03 CUCINA IN EMERGENZA',
                'A2-04' => 'A2-04 RADIOCOMUNICAZIONI E PROCESSO COMUNICATIVO IN PROTEZIONE CIVILE',
                'A2-05' => 'A2-05 IDROGEOLOGICO: ALLUVIONE',
                'A2-06' => 'A2-06 IDROGEOLOGICO: FRANE',
                'A2-07' => 'A2-07 IDROGEOLOGICO: SISTEMI DI ALTO POMPAGGIO',
                'A2-08' => 'A2-08 USO MOTOSEGA E DECESPUGLIATORE',
                'A2-09' => 'A2-09 SICUREZZA IN PROTEZIONE CIVILE: D. Lgs. 81/08',
                'A2-10' => 'A2-10 TOPOGRAFIA E GPS',
                'A2-11' => 'A2-11 RICERCA DISPERSI',
                'A2-12' => 'A2-12 OPERATORE NATANTE IN EMERGENZA DI PROTEZIONE CIVILE',
                'A2-13' => 'A2-13 INTERVENTI ZOOTECNICI IN EMERGENZA DI PROTEZIONE CIVILE',
                'A2-14' => 'A2-14 PIANO DI PROTEZIONE CIVILE: DIVULGAZIONE E INFORMAZIONE',
                'A2-15' => 'A2-15 QUADERNI DI PRESIDIO',
                'A2-16' => 'A2-16 EVENTI A RILEVANTE IMPATTO LOCALE',
                'A2-17' => 'A2-17 SCUOLA I° CICLO DELL\'ISTRUZIONE',
                'A2-18' => 'A2-18 SCUOLA SECONDARIA SUPERIORE',
                'A3-01' => 'A3-01 CAPO SQUADRA',
                'A3-02' => 'A3-02 COORDINATORE TERRITORIALE DEL VOLONTARIATO',
                'A3-03' => 'A3-03 VICE COORDINATORE DI SEGRETERIA E SUPPORTO ALLA SALA OPERATIVA',
                'A3-04' => 'A3-04 PRESIDENTE ASSOCIAZIONE e/o COORD. GR. COMUNALE/INTERCOM.',
                'A3-05' => 'A3-05 COMPONENTI CCV (eletti)',
                'A3-06' => 'A3-06 SUPPORTO ALLA PIANIFICAZIONE',
                'A4-01' => 'A4-01 **SOMMOZZATORI di Protezione civile: Operatore tecnico assistenza sommozzatori PC 1°livello "Attività subacquee e soccorso nautico"',
                'A4-02' => 'A4-02 **SOMMOZZATORI di protezione civile Alta specializzazione "Attività subacquee"',
                'A4-03' => 'A4-03 ATTIVITA\' OPERATORI CINOFILI',
                'A4-04' => 'A4-04 ATTIVITA\' OPERATORI EQUESTRI',
                'A4-05' => 'A4-05 CATTURA IMENOTTERI E BONIFICA',
                'A4-06' => 'A4-06 T.S.A. - Tecniche Speleo Alpinistiche',
                'A4-07' => 'A4-07 S.R.T. - Swiftwater Rescue Technician',
                'A4-08' => 'A4-08 PATENTE PER OPERATORE RADIO AMATORIALE',
                'A4-09' => 'A4-09 OPERATORE GRU SU AUTO-CARRO',
                'A4-10' => 'A4-10 OPERATORE MULETTO',
                'A4-11' => 'A4-11 OPERATORE PER PIATTAFORME DI LAVORO ELEVABILI (PLE)',
                'A4-12' => 'A4-12 OPERATORE ESCAVATORE',
                'A4-13' => 'A4-13 OPERATORE TRATTORE',
                'A4-14' => 'A4-14 OPERATORE DRONI',
                'A4-15' => 'A4-15 HACCP',
                'A5-01' => 'A5-01 A.I.B. di 1° LIVELLO',
                'A5-02' => 'A5-02 A.I.B. AGGIORNAMENTI',
                'A5-03' => 'A5-03 CAPOSQUADRA A.I.B.',
                'A5-04' => 'A5-04 D.O.S. (in gestione direttamente a RL)',
                'Altro' => 'Altro da specificare'
            ];
            $data['course_name'] = $courseTypes[$data['course_type']] ?? $data['course_type'];
        }
        
        if (empty($data['start_date'])) {
            $errors[] = 'La data di inizio è obbligatoria';
        }
        
        if (!empty($data['end_date']) && $data['start_date'] > $data['end_date']) {
            $errors[] = 'La data di fine non può essere precedente alla data di inizio';
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
                                        $courseTypes = [
                                            'A0' => 'A0 Corso informativo rivolto alla cittadinanza',
                                            'A1' => 'A1 Corso base per volontari operativi di Protezione Civile',
                                            'A2-01' => 'A2-01 ATTIVITA\' LOGISTICO GESTIONALI',
                                            'A2-02' => 'A2-02 OPERATORE SEGRETERIA',
                                            'A2-03' => 'A2-03 CUCINA IN EMERGENZA',
                                            'A2-04' => 'A2-04 RADIOCOMUNICAZIONI E PROCESSO COMUNICATIVO IN PROTEZIONE CIVILE',
                                            'A2-05' => 'A2-05 IDROGEOLOGICO: ALLUVIONE',
                                            'A2-06' => 'A2-06 IDROGEOLOGICO: FRANE',
                                            'A2-07' => 'A2-07 IDROGEOLOGICO: SISTEMI DI ALTO POMPAGGIO',
                                            'A2-08' => 'A2-08 USO MOTOSEGA E DECESPUGLIATORE',
                                            'A2-09' => 'A2-09 SICUREZZA IN PROTEZIONE CIVILE: D. Lgs. 81/08',
                                            'A2-10' => 'A2-10 TOPOGRAFIA E GPS',
                                            'A2-11' => 'A2-11 RICERCA DISPERSI',
                                            'A2-12' => 'A2-12 OPERATORE NATANTE IN EMERGENZA DI PROTEZIONE CIVILE',
                                            'A2-13' => 'A2-13 INTERVENTI ZOOTECNICI IN EMERGENZA DI PROTEZIONE CIVILE',
                                            'A2-14' => 'A2-14 PIANO DI PROTEZIONE CIVILE: DIVULGAZIONE E INFORMAZIONE',
                                            'A2-15' => 'A2-15 QUADERNI DI PRESIDIO',
                                            'A2-16' => 'A2-16 EVENTI A RILEVANTE IMPATTO LOCALE',
                                            'A2-17' => 'A2-17 SCUOLA I° CICLO DELL\'ISTRUZIONE',
                                            'A2-18' => 'A2-18 SCUOLA SECONDARIA SUPERIORE',
                                            'A3-01' => 'A3-01 CAPO SQUADRA',
                                            'A3-02' => 'A3-02 COORDINATORE TERRITORIALE DEL VOLONTARIATO',
                                            'A3-03' => 'A3-03 VICE COORDINATORE DI SEGRETERIA E SUPPORTO ALLA SALA OPERATIVA',
                                            'A3-04' => 'A3-04 PRESIDENTE ASSOCIAZIONE e/o COORD. GR. COMUNALE/INTERCOM.',
                                            'A3-05' => 'A3-05 COMPONENTI CCV (eletti)',
                                            'A3-06' => 'A3-06 SUPPORTO ALLA PIANIFICAZIONE',
                                            'A4-01' => 'A4-01 **SOMMOZZATORI di Protezione civile: Operatore tecnico assistenza sommozzatori PC 1°livello "Attività subacquee e soccorso nautico"',
                                            'A4-02' => 'A4-02 **SOMMOZZATORI di protezione civile Alta specializzazione "Attività subacquee"',
                                            'A4-03' => 'A4-03 ATTIVITA\' OPERATORI CINOFILI',
                                            'A4-04' => 'A4-04 ATTIVITA\' OPERATORI EQUESTRI',
                                            'A4-05' => 'A4-05 CATTURA IMENOTTERI E BONIFICA',
                                            'A4-06' => 'A4-06 T.S.A. - Tecniche Speleo Alpinistiche',
                                            'A4-07' => 'A4-07 S.R.T. - Swiftwater Rescue Technician',
                                            'A4-08' => 'A4-08 PATENTE PER OPERATORE RADIO AMATORIALE',
                                            'A4-09' => 'A4-09 OPERATORE GRU SU AUTO-CARRO',
                                            'A4-10' => 'A4-10 OPERATORE MULETTO',
                                            'A4-11' => 'A4-11 OPERATORE PER PIATTAFORME DI LAVORO ELEVABILI (PLE)',
                                            'A4-12' => 'A4-12 OPERATORE ESCAVATORE',
                                            'A4-13' => 'A4-13 OPERATORE TRATTORE',
                                            'A4-14' => 'A4-14 OPERATORE DRONI',
                                            'A4-15' => 'A4-15 HACCP',
                                            'A5-01' => 'A5-01 A.I.B. di 1° LIVELLO',
                                            'A5-02' => 'A5-02 A.I.B. AGGIORNAMENTI',
                                            'A5-03' => 'A5-03 CAPOSQUADRA A.I.B.',
                                            'A5-04' => 'A5-04 D.O.S. (in gestione direttamente a RL)',
                                            'Altro' => 'Altro da specificare'
                                        ];
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
                                    <label for="start_date" class="form-label">Data Inizio *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($course['start_date'] ?? $_POST['start_date'] ?? ''); ?>" 
                                           required>
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
