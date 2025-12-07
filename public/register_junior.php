<?php
/**
 * Registrazione Pubblica Soci Minorenni (Cadetti)
 * 
 * Pagina pubblica per la registrazione di nuovi soci minorenni
 * Include: anagrafica, recapiti, indirizzi, dati genitori/tutori
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\ApplicationController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance(); // Public page - no authentication required

$db = $app->getDb();
$config = $app->getConfig();
$controller = new ApplicationController($db, $config);

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
            'dlgs_volontariato' => 'D.Lgs. 117/2017 - Codice Terzo Settore',
            'dlgs_certificato' => 'D.Lgs. 81/2008 - Certificazione medica',
            'statuto' => 'Statuto Associativo e Regolamento Interno',
            'rischi_conoscenza' => 'Conoscenza dei rischi',
            'esenzione_responsabilita' => 'Esenzione di responsabilità',
            'dichiarazione_sostitutiva' => 'Dichiarazione sostitutiva di certificazione',
            'privacy_accepted' => 'Normativa Privacy',
            'privacy_foto' => 'Autorizzazione foto e video'
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
                // Dati anagrafici minore
                'last_name' => trim($_POST['last_name'] ?? ''),
                'first_name' => trim($_POST['first_name'] ?? ''),
                'birth_date' => $_POST['birth_date'] ?? '',
                'birth_place' => trim($_POST['birth_place'] ?? ''),
                'birth_province' => trim($_POST['birth_province'] ?? ''),
                'tax_code' => strtoupper(trim($_POST['tax_code'] ?? '')),
                'gender' => $_POST['gender'] ?? '',
                'nationality' => trim($_POST['nationality'] ?? 'Italiana'),
                
                // Indirizzo residenza
                'residence_street' => trim($_POST['residence_street'] ?? ''),
                'residence_number' => trim($_POST['residence_number'] ?? ''),
                'residence_city' => trim($_POST['residence_city'] ?? ''),
                'residence_province' => trim($_POST['residence_province'] ?? ''),
                'residence_cap' => trim($_POST['residence_cap'] ?? ''),
                
                // Indirizzo domicilio (se diverso)
                'domicile_street' => trim($_POST['domicile_street'] ?? ''),
                'domicile_number' => trim($_POST['domicile_number'] ?? ''),
                'domicile_city' => trim($_POST['domicile_city'] ?? ''),
                'domicile_province' => trim($_POST['domicile_province'] ?? ''),
                'domicile_cap' => trim($_POST['domicile_cap'] ?? ''),
                
                // Recapiti minore
                'phone' => trim($_POST['phone'] ?? ''),
                'mobile' => trim($_POST['mobile'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                
                // Salute
                'health_vegetarian' => !empty($_POST['health_vegetarian']),
                'health_vegan' => !empty($_POST['health_vegan']),
                'health_allergies' => trim($_POST['health_allergies'] ?? ''),
                'health_intolerances' => trim($_POST['health_intolerances'] ?? ''),
                'health_conditions' => trim($_POST['health_conditions'] ?? ''),
                
                // Genitori/Tutori
                'guardians' => [],
                
                // Consensi
                'dlgs_volontariato' => !empty($_POST['dlgs_volontariato']),
                'dlgs_certificato' => !empty($_POST['dlgs_certificato']),
                'statuto' => !empty($_POST['statuto']),
                'rischi_conoscenza' => !empty($_POST['rischi_conoscenza']),
                'esenzione_responsabilita' => !empty($_POST['esenzione_responsabilita']),
                'dichiarazione_sostitutiva' => !empty($_POST['dichiarazione_sostitutiva']),
                'privacy_accepted' => !empty($_POST['privacy_accepted']),
                'privacy_foto' => !empty($_POST['privacy_foto']),
                
                // Data e luogo compilazione
                'compilation_place' => trim($_POST['compilation_place'] ?? ''),
                'compilation_date' => $_POST['compilation_date'] ?? date('Y-m-d')
            ];
            
            // Aggiungi dati padre se compilati
            if (!empty($_POST['father_last_name']) || !empty($_POST['father_first_name'])) {
                $data['guardians'][] = [
                    'type' => 'padre',
                    'last_name' => trim($_POST['father_last_name'] ?? ''),
                    'first_name' => trim($_POST['father_first_name'] ?? ''),
                    'tax_code' => strtoupper(trim($_POST['father_tax_code'] ?? '')),
                    'birth_date' => $_POST['father_birth_date'] ?? '',
                    'birth_place' => trim($_POST['father_birth_place'] ?? ''),
                    'phone' => trim($_POST['father_phone'] ?? ''),
                    'email' => trim($_POST['father_email'] ?? '')
                ];
            }
            
            // Aggiungi dati madre se compilati
            if (!empty($_POST['mother_last_name']) || !empty($_POST['mother_first_name'])) {
                $data['guardians'][] = [
                    'type' => 'madre',
                    'last_name' => trim($_POST['mother_last_name'] ?? ''),
                    'first_name' => trim($_POST['mother_first_name'] ?? ''),
                    'tax_code' => strtoupper(trim($_POST['mother_tax_code'] ?? '')),
                    'birth_date' => $_POST['mother_birth_date'] ?? '',
                    'birth_place' => trim($_POST['mother_birth_place'] ?? ''),
                    'phone' => trim($_POST['mother_phone'] ?? ''),
                    'email' => trim($_POST['mother_email'] ?? '')
                ];
            }
            
            // Aggiungi dati tutore se compilati
            if (!empty($_POST['tutor_last_name']) || !empty($_POST['tutor_first_name'])) {
                $data['guardians'][] = [
                    'type' => 'tutore',
                    'last_name' => trim($_POST['tutor_last_name'] ?? ''),
                    'first_name' => trim($_POST['tutor_first_name'] ?? ''),
                    'tax_code' => strtoupper(trim($_POST['tutor_tax_code'] ?? '')),
                    'birth_date' => $_POST['tutor_birth_date'] ?? '',
                    'birth_place' => trim($_POST['tutor_birth_place'] ?? ''),
                    'phone' => trim($_POST['tutor_phone'] ?? ''),
                    'email' => trim($_POST['tutor_email'] ?? '')
                ];
            }
            
            // Verifica che ci sia almeno un genitore/tutore
            if (empty($data['guardians'])) {
                $errors[] = 'È necessario inserire almeno i dati di un genitore o tutore';
            }
            
            if (empty($errors)) {
                $result = $controller->createJunior($data);
                
                if ($result['success']) {
                    $success = true;
                    $applicationCode = $result['code'];
                } else {
                    $errors[] = $result['error'] ?? 'Errore durante l\'invio della domanda';
                }
            }
        }
    }
}

$pageTitle = 'Domanda di Iscrizione - Socio Minorenne (Cadetto)';
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
        .guardian-box {
            background: #e7f3ff;
            border: 1px solid #0d6efd;
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
                            <h2 class="mt-3">Domanda Inviata!</h2>
                            <p class="lead">La domanda di iscrizione è stata ricevuta con successo.</p>
                            <div class="alert alert-info">
                                <strong>Codice domanda:</strong> <?php echo htmlspecialchars($applicationCode); ?><br>
                                <small>Conserva questo codice per future comunicazioni</small>
                            </div>
                            <p>Riceverai un'email di conferma con il PDF della domanda all'indirizzo fornito.</p>
                            <p>Il nostro team esaminerà la domanda e ti contatterà a breve.</p>
                            <div class="alert alert-warning mt-4">
                                <h5><i class="bi bi-exclamation-triangle"></i> Importante</h5>
                                <p><strong>Il PDF generato deve essere stampato, firmato dal socio minorenne e da entrambi i genitori (o tutore), e consegnato in originale presso la sede dell'associazione.</strong></p>
                            </div>
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
                                    Dopo l'invio riceverai un PDF via email che dovrà essere stampato, firmato dal minore e dai genitori, e consegnato in sede.
                                </div>
                                
                                <!-- DATI ANAGRAFICI MINORE -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-person"></i> Dati Anagrafici del Minore</h5>
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
                                    <h5 class="mb-0"><i class="bi bi-telephone"></i> Recapiti del Minore</h5>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="phone" class="form-label">Telefono Fisso</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="mobile" class="form-label">Cellulare</label>
                                        <input type="tel" class="form-control" id="mobile" name="mobile" 
                                               value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- INFORMAZIONI SANITARIE -->
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
                                
                                <!-- DATI PADRE -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Dati del Padre</h5>
                                </div>
                                
                                <div class="guardian-box">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="father_last_name" class="form-label">Cognome</label>
                                            <input type="text" class="form-control" id="father_last_name" name="father_last_name" 
                                                   value="<?php echo htmlspecialchars($_POST['father_last_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="father_first_name" class="form-label">Nome</label>
                                            <input type="text" class="form-control" id="father_first_name" name="father_first_name" 
                                                   value="<?php echo htmlspecialchars($_POST['father_first_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="father_tax_code" class="form-label">Codice Fiscale</label>
                                            <input type="text" class="form-control text-uppercase" id="father_tax_code" name="father_tax_code" 
                                                   value="<?php echo htmlspecialchars($_POST['father_tax_code'] ?? ''); ?>" 
                                                   maxlength="16" pattern="[A-Z0-9]{16}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="father_birth_date" class="form-label">Data di Nascita</label>
                                            <input type="date" class="form-control" id="father_birth_date" name="father_birth_date" 
                                                   value="<?php echo htmlspecialchars($_POST['father_birth_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="father_birth_place" class="form-label">Luogo di Nascita</label>
                                            <input type="text" class="form-control" id="father_birth_place" name="father_birth_place" 
                                                   value="<?php echo htmlspecialchars($_POST['father_birth_place'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="father_phone" class="form-label">Telefono</label>
                                            <input type="tel" class="form-control" id="father_phone" name="father_phone" 
                                                   value="<?php echo htmlspecialchars($_POST['father_phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="father_email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="father_email" name="father_email" 
                                                   value="<?php echo htmlspecialchars($_POST['father_email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- DATI MADRE -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Dati della Madre</h5>
                                </div>
                                
                                <div class="guardian-box">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="mother_last_name" class="form-label">Cognome</label>
                                            <input type="text" class="form-control" id="mother_last_name" name="mother_last_name" 
                                                   value="<?php echo htmlspecialchars($_POST['mother_last_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="mother_first_name" class="form-label">Nome</label>
                                            <input type="text" class="form-control" id="mother_first_name" name="mother_first_name" 
                                                   value="<?php echo htmlspecialchars($_POST['mother_first_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="mother_tax_code" class="form-label">Codice Fiscale</label>
                                            <input type="text" class="form-control text-uppercase" id="mother_tax_code" name="mother_tax_code" 
                                                   value="<?php echo htmlspecialchars($_POST['mother_tax_code'] ?? ''); ?>" 
                                                   maxlength="16" pattern="[A-Z0-9]{16}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="mother_birth_date" class="form-label">Data di Nascita</label>
                                            <input type="date" class="form-control" id="mother_birth_date" name="mother_birth_date" 
                                                   value="<?php echo htmlspecialchars($_POST['mother_birth_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="mother_birth_place" class="form-label">Luogo di Nascita</label>
                                            <input type="text" class="form-control" id="mother_birth_place" name="mother_birth_place" 
                                                   value="<?php echo htmlspecialchars($_POST['mother_birth_place'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="mother_phone" class="form-label">Telefono</label>
                                            <input type="tel" class="form-control" id="mother_phone" name="mother_phone" 
                                                   value="<?php echo htmlspecialchars($_POST['mother_phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="mother_email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="mother_email" name="mother_email" 
                                                   value="<?php echo htmlspecialchars($_POST['mother_email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- DATI TUTORE (opzionale, se non ci sono i genitori) -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Dati del Tutore (se non ci sono i genitori)</h5>
                                </div>
                                
                                <div class="guardian-box">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="tutor_last_name" class="form-label">Cognome</label>
                                            <input type="text" class="form-control" id="tutor_last_name" name="tutor_last_name" 
                                                   value="<?php echo htmlspecialchars($_POST['tutor_last_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="tutor_first_name" class="form-label">Nome</label>
                                            <input type="text" class="form-control" id="tutor_first_name" name="tutor_first_name" 
                                                   value="<?php echo htmlspecialchars($_POST['tutor_first_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="tutor_tax_code" class="form-label">Codice Fiscale</label>
                                            <input type="text" class="form-control text-uppercase" id="tutor_tax_code" name="tutor_tax_code" 
                                                   value="<?php echo htmlspecialchars($_POST['tutor_tax_code'] ?? ''); ?>" 
                                                   maxlength="16" pattern="[A-Z0-9]{16}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="tutor_birth_date" class="form-label">Data di Nascita</label>
                                            <input type="date" class="form-control" id="tutor_birth_date" name="tutor_birth_date" 
                                                   value="<?php echo htmlspecialchars($_POST['tutor_birth_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="tutor_birth_place" class="form-label">Luogo di Nascita</label>
                                            <input type="text" class="form-control" id="tutor_birth_place" name="tutor_birth_place" 
                                                   value="<?php echo htmlspecialchars($_POST['tutor_birth_place'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="tutor_phone" class="form-label">Telefono</label>
                                            <input type="tel" class="form-control" id="tutor_phone" name="tutor_phone" 
                                                   value="<?php echo htmlspecialchars($_POST['tutor_phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="tutor_email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="tutor_email" name="tutor_email" 
                                                   value="<?php echo htmlspecialchars($_POST['tutor_email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- DICHIARAZIONI E CONSENSI -->
                                <div class="section-header">
                                    <h5 class="mb-0"><i class="bi bi-file-text"></i> Dichiarazioni Obbligatorie</h5>
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
                                        <input class="form-check-input" type="checkbox" id="rischi_conoscenza" name="rischi_conoscenza" required>
                                        <label class="form-check-label" for="rischi_conoscenza">
                                            Sono a conoscenza dei rischi che le attività associative e ludico-sportive possono comportare al Socio Cadetto ed a terzi <span class="required-star">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="declaration-box mb-3">
                                    <h6>Esenzione di Responsabilità</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="esenzione_responsabilita" name="esenzione_responsabilita" required>
                                        <label class="form-check-label" for="esenzione_responsabilita">
                                            Si solleva da qualunque responsabilità e si rinuncia ad ogni azione di rivalsa l'Associazione, il Presidente, 
                                            il Consiglio Direttivo e gli Istruttori per la partecipazione alle attività associative, compreso il viaggio 
                                            di trasferimento alla località prestabilita e ritorno, con qualsiasi mezzo di locomozione <span class="required-star">*</span>
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
                                    Dopo l'invio riceverai un PDF via email che dovrà essere stampato, firmato dal minore e dai genitori/tutore, e consegnato in sede.
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
