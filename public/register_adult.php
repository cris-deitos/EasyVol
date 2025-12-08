<?php
/**
 * Registrazione Pubblica Soci Maggiorenni
 * 
 * Pagina pubblica per la registrazione di nuovi soci maggiorenni con tutti i dati richiesti
 * Include: anagrafica, recapiti, indirizzi, patenti, corsi, allergie/patologie, datore di lavoro
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ApplicationController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance(); // Public page - no authentication required

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ApplicationController($db, $config);

// Log page access
AutoLogger::logPageAccess();

$errors = [];
$success = false;
$applicationCode = '';

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        // Valida accettazione clausole obbligatorie
        $requiredConsents = [
            'art6_operativo' => 'Art. 6 - Disponibilità operativa',
            'art6_unica_org' => 'Art. 6 - Dichiarazione organizzazione unica',
            'art7_condanne' => 'Art. 7 - Dichiarazione condanne penali',
            'dlgs_volontariato' => 'D.Lgs. 117/2017 - Codice Terzo Settore',
            'dlgs_sicurezza' => 'D.Lgs. 81/2008 - Sicurezza sul lavoro - DPI',
            'dlgs_certificato' => 'D.Lgs. 81/2008 - Certificazione medica',
            'statuto' => 'Statuto Associativo e Regolamento Interno',
            'rischi_specifici' => 'Conoscenza pericoli e rischi specifici',
            'rischi_attrezzature' => 'Conoscenza rischi attrezzature',
            'responsabilita_salute' => 'Responsabilità comunicazione problemi salute',
            'privacy_accepted' => 'Normativa Privacy',
            'privacy_foto' => 'Autorizzazione foto e video',
            'dichiarazione_sostitutiva' => 'Dichiarazione sostitutiva di certificazione'
        ];
        
        foreach ($requiredConsents as $field => $label) {
            if (empty($_POST[$field])) {
                $errors[] = "Devi accettare: $label";
            }
        }
        
        // Verifica CAPTCHA (se abilitato)
        if (!empty($config['recaptcha']['enabled'])) {
            $recaptchaSecret = $config['recaptcha']['secret_key'] ?? '';
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            
            if (empty($recaptchaResponse)) {
                $errors[] = 'Completa la verifica CAPTCHA';
            } else {
                $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}");
                $captchaSuccess = json_decode($verify);
                if (!$captchaSuccess->success) {
                    $errors[] = 'Verifica CAPTCHA fallita';
                }
            }
        }
        
        if (empty($errors)) {
            // Prepara i dati dell'applicazione
            $data = [
                // Dati anagrafici
                'last_name' => trim($_POST['last_name'] ?? ''),
                'first_name' => trim($_POST['first_name'] ?? ''),
                'birth_date' => $_POST['birth_date'] ?? '',
                'birth_place' => trim($_POST['birth_place'] ?? ''),
                'birth_province' => trim($_POST['birth_province'] ?? ''),
                'tax_code' => strtoupper(trim($_POST['tax_code'] ?? '')),
                'gender' => $_POST['gender'] ?? '',
                'nationality' => trim($_POST['nationality'] ?? 'Italiana'),
                
                // Indirizzi
                'residence_street' => trim($_POST['residence_street'] ?? ''),
                'residence_number' => trim($_POST['residence_number'] ?? ''),
                'residence_city' => trim($_POST['residence_city'] ?? ''),
                'residence_province' => trim($_POST['residence_province'] ?? ''),
                'residence_cap' => trim($_POST['residence_cap'] ?? ''),
                
                'domicile_street' => trim($_POST['domicile_street'] ?? ''),
                'domicile_number' => trim($_POST['domicile_number'] ?? ''),
                'domicile_city' => trim($_POST['domicile_city'] ?? ''),
                'domicile_province' => trim($_POST['domicile_province'] ?? ''),
                'domicile_cap' => trim($_POST['domicile_cap'] ?? ''),
                
                // Recapiti
                'phone' => trim($_POST['phone'] ?? ''),
                'mobile' => trim($_POST['mobile'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'pec' => trim($_POST['pec'] ?? ''),
                
                // Patenti
                'licenses' => array_filter([
                    ['type' => 'A', 'number' => trim($_POST['license_a_number'] ?? ''), 'issue_date' => $_POST['license_a_issue'] ?? '', 'expiry_date' => $_POST['license_a_expiry'] ?? ''],
                    ['type' => 'B', 'number' => trim($_POST['license_b_number'] ?? ''), 'issue_date' => $_POST['license_b_issue'] ?? '', 'expiry_date' => $_POST['license_b_expiry'] ?? ''],
                    ['type' => 'C', 'number' => trim($_POST['license_c_number'] ?? ''), 'issue_date' => $_POST['license_c_issue'] ?? '', 'expiry_date' => $_POST['license_c_expiry'] ?? ''],
                    ['type' => 'D', 'number' => trim($_POST['license_d_number'] ?? ''), 'issue_date' => $_POST['license_d_issue'] ?? '', 'expiry_date' => $_POST['license_d_expiry'] ?? ''],
                    ['type' => 'E', 'number' => trim($_POST['license_e_number'] ?? ''), 'issue_date' => $_POST['license_e_issue'] ?? '', 'expiry_date' => $_POST['license_e_expiry'] ?? ''],
                    ['type' => 'Nautica', 'number' => trim($_POST['license_nautica_number'] ?? ''), 'issue_date' => $_POST['license_nautica_issue'] ?? '', 'expiry_date' => $_POST['license_nautica_expiry'] ?? ''],
                    ['type' => 'Muletto', 'number' => trim($_POST['license_muletto_number'] ?? ''), 'issue_date' => $_POST['license_muletto_issue'] ?? '', 'expiry_date' => $_POST['license_muletto_expiry'] ?? ''],
                    ['type' => 'Altro', 'description' => trim($_POST['license_altro_desc'] ?? ''), 'number' => trim($_POST['license_altro_number'] ?? ''), 'issue_date' => $_POST['license_altro_issue'] ?? '', 'expiry_date' => $_POST['license_altro_expiry'] ?? ''],
                ], function($license) { return !empty($license['number']) || !empty($license['description']); }),
                
                // Corsi e specializzazioni
                'courses' => array_filter([
                    ['name' => trim($_POST['course_1_name'] ?? ''), 'completion_date' => $_POST['course_1_date'] ?? '', 'expiry_date' => $_POST['course_1_expiry'] ?? ''],
                    ['name' => trim($_POST['course_2_name'] ?? ''), 'completion_date' => $_POST['course_2_date'] ?? '', 'expiry_date' => $_POST['course_2_expiry'] ?? ''],
                    ['name' => trim($_POST['course_3_name'] ?? ''), 'completion_date' => $_POST['course_3_date'] ?? '', 'expiry_date' => $_POST['course_3_expiry'] ?? ''],
                ], function($course) { return !empty($course['name']); }),
                
                // Salute
                'health_vegetarian' => !empty($_POST['health_vegetarian']),
                'health_vegan' => !empty($_POST['health_vegan']),
                'health_allergies' => trim($_POST['health_allergies'] ?? ''),
                'health_intolerances' => trim($_POST['health_intolerances'] ?? ''),
                'health_conditions' => trim($_POST['health_conditions'] ?? ''),
                
                // Datore di lavoro
                'employer_name' => trim($_POST['employer_name'] ?? ''),
                'employer_address' => trim($_POST['employer_address'] ?? ''),
                'employer_city' => trim($_POST['employer_city'] ?? ''),
                'employer_phone' => trim($_POST['employer_phone'] ?? ''),
                
                // Consensi
                'art6_operativo' => !empty($_POST['art6_operativo']),
                'art6_unica_org' => !empty($_POST['art6_unica_org']),
                'art7_condanne' => !empty($_POST['art7_condanne']),
                'dlgs_volontariato' => !empty($_POST['dlgs_volontariato']),
                'dlgs_sicurezza' => !empty($_POST['dlgs_sicurezza']),
                'dlgs_certificato' => !empty($_POST['dlgs_certificato']),
                'statuto' => !empty($_POST['statuto']),
                'rischi_specifici' => !empty($_POST['rischi_specifici']),
                'rischi_attrezzature' => !empty($_POST['rischi_attrezzature']),
                'responsabilita_salute' => !empty($_POST['responsabilita_salute']),
                'privacy_accepted' => !empty($_POST['privacy_accepted']),
                'privacy_foto' => !empty($_POST['privacy_foto']),
                'dichiarazione_sostitutiva' => !empty($_POST['dichiarazione_sostitutiva']),
                
                // Data e luogo compilazione
                'compilation_place' => trim($_POST['compilation_place'] ?? ''),
                'compilation_date' => $_POST['compilation_date'] ?? date('Y-m-d')
            ];
            
            $result = $controller->createAdult($data);
            
            if ($result['success']) {
                $success = true;
                $applicationCode = $result['code'];
                
                // Show warnings if PDF or email failed
                if (!empty($result['processing_errors'])) {
                    foreach ($result['processing_errors'] as $error) {
                        $errors[] = "Attenzione: " . $error;
                    }
                }
            } else {
                $errors[] = $result['error'] ?? 'Errore durante l\'invio della domanda';
            }
        }
    }
}

$pageTitle = 'Domanda di Iscrizione - Socio Maggiorenne';
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
    <?php if (!empty($config['recaptcha']['enabled'])): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <style>
        .section-header {
            background: #f8f9fa;
            padding: 10px 15px;
            margin-top: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #0d6efd;
        }
        .declaration-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .required-star {
            color: red;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php echo htmlspecialchars($config['association']['name'] ?? 'EasyVol'); ?>
            </a>
        </div>
    </nav>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if ($success): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-check-circle text-success" style="font-size: 80px;"></i>
                            <h2 class="mt-3">Domanda Inviata con Successo!</h2>
                            <p class="lead">La tua domanda di iscrizione è stata ricevuta correttamente.</p>
                            <div class="alert alert-info">
                                <strong>Codice Domanda:</strong> <?php echo htmlspecialchars($applicationCode); ?><br>
                                <small>Conserva questo codice per future comunicazioni</small>
                            </div>
                            
                            <div class="alert alert-success mt-3">
                                <p class="mb-2">
                                    <i class="bi bi-envelope-check"></i>
                                    Abbiamo inviato un'email all'indirizzo <strong><?php echo htmlspecialchars($_POST['email'] ?? ''); ?></strong>
                                    con il modulo PDF precompilato in allegato.
                                </p>
                            </div>
                            
                            <div class="alert alert-warning mt-4">
                                <h5><i class="bi bi-list-check"></i> Prossimi Passi:</h5>
                                <ol class="text-start mt-3">
                                    <li><strong>Controlla la tua email</strong> (anche nella cartella spam se non la trovi)</li>
                                    <li><strong>Stampa il modulo PDF</strong> allegato all'email</li>
                                    <li><strong>Firma negli spazi indicati</strong></li>
                                    <li><strong>Consegna il modulo firmato</strong> presso la nostra sede insieme a:
                                        <ul class="mt-2">
                                            <li>Copie di Attestati e Specializzazioni personali in campi inerenti alla Protezione Civile</li>
                                            <li>Copie Patenti di Guida per conduzione di mezzi speciali, Brevetti o Patentini per natanti o velivoli</li>
                                        </ul>
                                    </li>
                                </ol>
                            </div>
                            
                            <p class="mt-3">Il nostro team esaminerà la tua domanda e ti contatterà a breve.</p>
                            <a href="index.php" class="btn btn-primary mt-3">Torna alla Home</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h3>
                        </div>
                        <div class="card-body">
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
                            
                            <form method="POST" action="" id="registrationForm">
                                <?php echo CsrfProtection::getHiddenField(); ?>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Attenzione:</strong> Compila tutti i campi obbligatori contrassegnati con <span class="required-star">*</span>. 
                                    Dopo l'invio riceverai un PDF via email che dovrà essere stampato, firmato e consegnato in sede con i documenti allegati.
                                </div>
                                
                                <!-- DATI ANAGRAFICI -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-person"></i> Dati Anagrafici</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Cognome <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">Nome <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="tax_code" class="form-label">Codice Fiscale <span class="required-star">*</span></label>
                                        <input type="text" class="form-control text-uppercase" id="tax_code" name="tax_code" 
                                               value="<?php echo htmlspecialchars($_POST['tax_code'] ?? ''); ?>" 
                                               maxlength="16" pattern="[A-Z0-9]{16}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="birth_date" class="form-label">Data di Nascita <span class="required-star">*</span></label>
                                        <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                               value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="gender" class="form-label">Sesso <span class="required-star">*</span></label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Seleziona...</option>
                                            <option value="M" <?php echo ($_POST['gender'] ?? '') === 'M' ? 'selected' : ''; ?>>Maschile</option>
                                            <option value="F" <?php echo ($_POST['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>Femminile</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="birth_place" class="form-label">Luogo di Nascita <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="birth_place" name="birth_place" 
                                               value="<?php echo htmlspecialchars($_POST['birth_place'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="birth_province" class="form-label">Provincia <span class="required-star">*</span></label>
                                        <input type="text" class="form-control text-uppercase" id="birth_province" name="birth_province" 
                                               value="<?php echo htmlspecialchars($_POST['birth_province'] ?? ''); ?>" 
                                               maxlength="2" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="nationality" class="form-label">Nazionalità</label>
                                        <input type="text" class="form-control" id="nationality" name="nationality" 
                                               value="<?php echo htmlspecialchars($_POST['nationality'] ?? 'Italiana'); ?>">
                                    </div>
                                </div>
                                
                                <!-- INDIRIZZO RESIDENZA -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-house"></i> Indirizzo di Residenza</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="residence_street" class="form-label">Via <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="residence_street" name="residence_street" 
                                               value="<?php echo htmlspecialchars($_POST['residence_street'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="residence_number" class="form-label">Numero <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="residence_number" name="residence_number" 
                                               value="<?php echo htmlspecialchars($_POST['residence_number'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <label for="residence_city" class="form-label">Città <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="residence_city" name="residence_city" 
                                               value="<?php echo htmlspecialchars($_POST['residence_city'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="residence_province" class="form-label">Provincia <span class="required-star">*</span></label>
                                        <input type="text" class="form-control text-uppercase" id="residence_province" name="residence_province" 
                                               value="<?php echo htmlspecialchars($_POST['residence_province'] ?? ''); ?>" 
                                               maxlength="2" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="residence_cap" class="form-label">CAP <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="residence_cap" name="residence_cap" 
                                               value="<?php echo htmlspecialchars($_POST['residence_cap'] ?? ''); ?>" 
                                               maxlength="5" pattern="[0-9]{5}" required>
                                    </div>
                                </div>
                                
                                <!-- INDIRIZZO DOMICILIO -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Indirizzo di Domicilio (se diverso dalla residenza)</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="domicile_street" class="form-label">Via</label>
                                        <input type="text" class="form-control" id="domicile_street" name="domicile_street" 
                                               value="<?php echo htmlspecialchars($_POST['domicile_street'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="domicile_number" class="form-label">Numero</label>
                                        <input type="text" class="form-control" id="domicile_number" name="domicile_number" 
                                               value="<?php echo htmlspecialchars($_POST['domicile_number'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <label for="domicile_city" class="form-label">Città</label>
                                        <input type="text" class="form-control" id="domicile_city" name="domicile_city" 
                                               value="<?php echo htmlspecialchars($_POST['domicile_city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="domicile_province" class="form-label">Provincia</label>
                                        <input type="text" class="form-control text-uppercase" id="domicile_province" name="domicile_province" 
                                               value="<?php echo htmlspecialchars($_POST['domicile_province'] ?? ''); ?>" 
                                               maxlength="2">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="domicile_cap" class="form-label">CAP</label>
                                        <input type="text" class="form-control" id="domicile_cap" name="domicile_cap" 
                                               value="<?php echo htmlspecialchars($_POST['domicile_cap'] ?? ''); ?>" 
                                               maxlength="5" pattern="[0-9]{5}">
                                    </div>
                                </div>
                                
                                <!-- RECAPITI -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-telephone"></i> Recapiti</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="phone" class="form-label">Telefono Fisso</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="mobile" class="form-label">Cellulare <span class="required-star">*</span></label>
                                        <input type="tel" class="form-control" id="mobile" name="mobile" 
                                               value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="email" class="form-label">Email <span class="required-star">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="pec" class="form-label">PEC</label>
                                        <input type="email" class="form-control" id="pec" name="pec" 
                                               value="<?php echo htmlspecialchars($_POST['pec'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- PATENTI -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-card-heading"></i> Patenti e Abilitazioni</h5>
                                </div>
                                
                                <p class="text-muted small">Compila solo le patenti che possiedi</p>
                                
                                <?php
                                $licenseTypes = [
                                    'a' => 'Patente A',
                                    'b' => 'Patente B',
                                    'c' => 'Patente C',
                                    'd' => 'Patente D',
                                    'e' => 'Patente E',
                                    'nautica' => 'Patente Nautica',
                                    'muletto' => 'Patentino Muletto',
                                    'altro' => 'Altro (specificare)'
                                ];
                                
                                foreach ($licenseTypes as $key => $label):
                                ?>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <h6><?php echo $label; ?></h6>
                                        <div class="row">
                                            <?php if ($key === 'altro'): ?>
                                            <div class="col-md-3">
                                                <label class="form-label">Descrizione</label>
                                                <input type="text" class="form-control" name="license_<?php echo $key; ?>_desc" 
                                                       value="<?php echo htmlspecialchars($_POST["license_{$key}_desc"] ?? ''); ?>">
                                            </div>
                                            <?php endif; ?>
                                            <div class="col-md-3">
                                                <label class="form-label">Numero</label>
                                                <input type="text" class="form-control" name="license_<?php echo $key; ?>_number" 
                                                       value="<?php echo htmlspecialchars($_POST["license_{$key}_number"] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Data Rilascio</label>
                                                <input type="date" class="form-control" name="license_<?php echo $key; ?>_issue" 
                                                       value="<?php echo htmlspecialchars($_POST["license_{$key}_issue"] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Data Scadenza</label>
                                                <input type="date" class="form-control" name="license_<?php echo $key; ?>_expiry" 
                                                       value="<?php echo htmlspecialchars($_POST["license_{$key}_expiry"] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <!-- CORSI E SPECIALIZZAZIONI -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-mortarboard"></i> Corsi e Specializzazioni</h5>
                                </div>
                                
                                <p class="text-muted small">Indica eventuali corsi di Protezione Civile o specializzazioni già in possesso</p>
                                
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <h6>Corso <?php echo $i; ?></h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Nome Corso</label>
                                                <input type="text" class="form-control" name="course_<?php echo $i; ?>_name" 
                                                       value="<?php echo htmlspecialchars($_POST["course_{$i}_name"] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Data Completamento</label>
                                                <input type="date" class="form-control" name="course_<?php echo $i; ?>_date" 
                                                       value="<?php echo htmlspecialchars($_POST["course_{$i}_date"] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Data Scadenza</label>
                                                <input type="date" class="form-control" name="course_<?php echo $i; ?>_expiry" 
                                                       value="<?php echo htmlspecialchars($_POST["course_{$i}_expiry"] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                                
                                <!-- ALLERGIE E PATOLOGIE -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-heart-pulse"></i> Informazioni Sanitarie</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="health_vegetarian" name="health_vegetarian" 
                                                   <?php echo !empty($_POST['health_vegetarian']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="health_vegetarian">
                                                Vegetariano
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="health_vegan" name="health_vegan" 
                                                   <?php echo !empty($_POST['health_vegan']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="health_vegan">
                                                Vegano
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="health_allergies" class="form-label">Allergie</label>
                                    <textarea class="form-control" id="health_allergies" name="health_allergies" rows="2"><?php echo htmlspecialchars($_POST['health_allergies'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="health_intolerances" class="form-label">Intolleranze Alimentari</label>
                                    <textarea class="form-control" id="health_intolerances" name="health_intolerances" rows="2"><?php echo htmlspecialchars($_POST['health_intolerances'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="health_conditions" class="form-label">Patologie</label>
                                    <textarea class="form-control" id="health_conditions" name="health_conditions" rows="2"><?php echo htmlspecialchars($_POST['health_conditions'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- DATORE DI LAVORO -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-briefcase"></i> Datore di Lavoro</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="employer_name" class="form-label">Ragione Sociale</label>
                                        <input type="text" class="form-control" id="employer_name" name="employer_name" 
                                               value="<?php echo htmlspecialchars($_POST['employer_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="employer_address" class="form-label">Indirizzo</label>
                                        <input type="text" class="form-control" id="employer_address" name="employer_address" 
                                               value="<?php echo htmlspecialchars($_POST['employer_address'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="employer_city" class="form-label">Città</label>
                                        <input type="text" class="form-control" id="employer_city" name="employer_city" 
                                               value="<?php echo htmlspecialchars($_POST['employer_city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="employer_phone" class="form-label">Telefono</label>
                                        <input type="tel" class="form-control" id="employer_phone" name="employer_phone" 
                                               value="<?php echo htmlspecialchars($_POST['employer_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- DICHIARAZIONI E CONSENSI -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-file-text"></i> Dichiarazioni Obbligatorie</h5>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Art. 6 - Regolamento Regionale del 18 Ottobre 2010</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="art6_operativo" name="art6_operativo" required>
                                        <label class="form-check-label" for="art6_operativo">
                                            Dichiaro di essere disponibile a svolgere compiti operativi nell'ambito di interventi di Protezione Civile <span class="required-star">*</span>
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="art6_unica_org" name="art6_unica_org" required>
                                        <label class="form-check-label" for="art6_unica_org">
                                            Dichiaro la propria operatività a favore di una sola organizzazione di volontariato di Protezione Civile <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Art. 7 - Regolamento Regionale del 18 Ottobre 2010</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="art7_condanne" name="art7_condanne" required>
                                        <label class="form-check-label" for="art7_condanne">
                                            Dichiaro di non avere riportato condanne penali per reati dolosi contro le persone o contro il patrimonio <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>D.Lgs. 3 luglio 2017, n. 117 – Codice del Terzo Settore</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="dlgs_volontariato" name="dlgs_volontariato" required>
                                        <label class="form-check-label" for="dlgs_volontariato">
                                            Sono informato che l'attività di volontariato è svolta in modo personale, spontaneo e gratuito e che non è prevista l'erogazione di alcun compenso. 
                                            Autorizzo esclusivamente il rimborso delle sole spese effettivamente sostenute e documentate <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Regolamento D.Lgs. 81/2008 - Tutela della Salute e della Sicurezza</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="dlgs_sicurezza" name="dlgs_sicurezza" required>
                                        <label class="form-check-label" for="dlgs_sicurezza">
                                            Sono a conoscenza dell'obbligo di indossare i DPI (Divisa completa, scarpe antinfortunistiche, occhiali protettivi, guanti da lavoro e caschetto protettivo) in tutte le attività <span class="required-star">*</span>
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="dlgs_certificato" name="dlgs_certificato" required>
                                        <label class="form-check-label" for="dlgs_certificato">
                                            Sono disponibile ad esibire certificazione medica qualora richiesto <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Statuto Associativo e Regolamento Interno</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="statuto" name="statuto" required>
                                        <label class="form-check-label" for="statuto">
                                            Mi impegno a rispettare lo Statuto Associativo ed il Regolamento Interno, e condivido lo scopo ed i valori <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Conoscenza dei Rischi</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="rischi_specifici" name="rischi_specifici" required>
                                        <label class="form-check-label" for="rischi_specifici">
                                            Sono a conoscenza dei pericoli, rischi specifici e collaterali nelle attività di Protezione Civile <span class="required-star">*</span>
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="rischi_attrezzature" name="rischi_attrezzature" required>
                                        <label class="form-check-label" for="rischi_attrezzature">
                                            Sono a conoscenza dei rischi specifici e collaterali nell'utilizzo delle attrezzature <span class="required-star">*</span>
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="responsabilita_salute" name="responsabilita_salute" required>
                                        <label class="form-check-label" for="responsabilita_salute">
                                            Sono consapevole che l'Associazione non è responsabile per la mancata comunicazione di eventuali problemi di salute <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Dichiarazione Sostitutiva di Certificazione</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="dichiarazione_sostitutiva" name="dichiarazione_sostitutiva" required>
                                        <label class="form-check-label" for="dichiarazione_sostitutiva">
                                            Sono consapevole che ai sensi degli Artt. 46-76 D.P.R. n. 445/2000, chiunque rilasci dichiarazioni mendaci è punibile ai sensi del Codice penale <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Normativa sulla Privacy</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="privacy_accepted" name="privacy_accepted" required>
                                        <label class="form-check-label" for="privacy_accepted">
                                            Autorizzo il trattamento dei dati personali ai sensi del GDPR 2016/679 e del D.lgs. 196/2003 <span class="required-star">*</span>
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="privacy_foto" name="privacy_foto" required>
                                        <label class="form-check-label" for="privacy_foto">
                                            Acconsento alla pubblicazione di fotografie e riprese video su pubblicazioni, sito internet e social media dell'Associazione. 
                                            Sollevo l'Associazione da responsabilità per uso improprio da parte di terzi <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- LUOGO E DATA -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Luogo e Data di Compilazione</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="compilation_place" class="form-label">Luogo <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="compilation_place" name="compilation_place" 
                                               value="<?php echo htmlspecialchars($_POST['compilation_place'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="compilation_date" class="form-label">Data <span class="required-star">*</span></label>
                                        <input type="date" class="form-control" id="compilation_date" name="compilation_date" 
                                               value="<?php echo htmlspecialchars($_POST['compilation_date'] ?? date('Y-m-d')); ?>" required>
                                    </div>
                                </div>
                                
                                <?php if (!empty($config['recaptcha']['enabled'])): ?>
                                    <div class="mb-3">
                                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha']['site_key']); ?>"></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-send"></i> Invia Domanda di Iscrizione
                                    </button>
                                </div>
                                
                                <p class="text-muted small mt-3">
                                    <span class="required-star">*</span> Campi obbligatori<br>
                                    Dopo l'invio riceverai un PDF via email che dovrà essere stampato, firmato e consegnato in sede insieme agli allegati richiesti.
                                </p>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <footer class="bg-light py-4 mt-5">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['association']['name'] ?? 'EasyVol'); ?>. Tutti i diritti riservati.</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
