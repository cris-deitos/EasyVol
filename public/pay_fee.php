<?php
/**
 * Caricamento Ricevuta Pagamento Quota
 * 
 * Pagina pubblica per il caricamento delle ricevute di pagamento quote associative
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\FeePaymentController;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\FileUploader;

$app = App::getInstance();

// Get app configuration for CAPTCHA
$db = $app->getDb();
$config = $app->getConfig();
$controller = new FeePaymentController($db, $config);

$errors = [];
$success = false;
$step = 1; // Step 1: verify member, Step 2: upload receipt

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';
    
    // Verify CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    }
    
    if ($action === 'verify') {
        // Step 1: Verify member
        $registrationNumber = trim($_POST['registration_number'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        
        if (empty($registrationNumber)) {
            $errors[] = 'La matricola è obbligatoria';
        }
        if (empty($lastName)) {
            $errors[] = 'Il cognome è obbligatorio';
        }
        
        // Verify CAPTCHA
        if (!empty($config['recaptcha']['enabled'])) {
            $recaptchaSecret = $config['recaptcha']['secret_key'] ?? '';
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            
            if (empty($recaptchaResponse)) {
                $errors[] = 'Completa la verifica CAPTCHA';
            } else {
                // Verify reCAPTCHA with cURL for better error handling
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'secret' => $recaptchaSecret,
                    'response' => $recaptchaResponse
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $verify = curl_exec($ch);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError) {
                    $errors[] = 'Errore nella verifica CAPTCHA. Riprova.';
                } else {
                    $captchaSuccess = json_decode($verify);
                    if (!$captchaSuccess || !$captchaSuccess->success) {
                        $errors[] = 'Verifica CAPTCHA fallita';
                    }
                }
            }
        }
        
        if (empty($errors)) {
            $member = $controller->verifyMember($registrationNumber, $lastName);
            if ($member) {
                $step = 2;
                // Store member data in session for step 2
                $_SESSION['fee_payment_member'] = $member;
            } else {
                $errors[] = 'Matricola e cognome non corrispondono. Verifica i dati inseriti.';
            }
        }
    } elseif ($action === 'submit') {
        // Step 2: Submit payment receipt
        if (empty($_SESSION['fee_payment_member'])) {
            $errors[] = 'Sessione scaduta. Riprova dall\'inizio.';
            $step = 1;
        } else {
            $member = $_SESSION['fee_payment_member'];
            $paymentDate = $_POST['payment_date'] ?? '';
            $paymentYear = $_POST['payment_year'] ?? '';
            
            if (empty($paymentDate)) {
                $errors[] = 'La data di pagamento è obbligatoria';
            }
            if (empty($paymentYear) || !is_numeric($paymentYear)) {
                $errors[] = 'L\'anno di riferimento è obbligatorio';
            }
            if (empty($_FILES['receipt_file']['name'])) {
                $errors[] = 'La ricevuta di pagamento è obbligatoria';
            }
            
            if (empty($errors)) {
                // Upload receipt file
                $uploadsPath = __DIR__ . '/../uploads/fee_receipts';
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                $uploader = new FileUploader($uploadsPath, $allowedTypes, 5242880); // 5MB max
                
                $uploadResult = $uploader->upload($_FILES['receipt_file'], date('Y'));
                
                if ($uploadResult['success']) {
                    // Create payment request
                    $requestData = [
                        'registration_number' => $member['registration_number'],
                        'last_name' => $member['last_name'],
                        'payment_year' => $paymentYear,
                        'payment_date' => $paymentDate,
                        'receipt_file' => $uploadResult['path']
                    ];
                    
                    $requestId = $controller->createPaymentRequest($requestData);
                    
                    if ($requestId) {
                        // Send emails
                        $controller->sendSubmissionEmails($member, $requestData);
                        
                        $success = true;
                        unset($_SESSION['fee_payment_member']);
                    } else {
                        $errors[] = 'Errore durante il salvataggio della richiesta. Riprova.';
                    }
                } else {
                    $errors[] = 'Errore durante il caricamento del file: ' . $uploadResult['error'];
                }
            }
        }
    }
}

// If step 2 and member data in session, show form
if ($step === 1 && !empty($_SESSION['fee_payment_member'])) {
    $step = 2;
}

$pageTitle = 'Carica Ricevuta Pagamento Quota';
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
    <?php if (!empty($config['recaptcha']['enabled']) && $step === 1): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <style>
        .public-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-section h1 {
            color: #667eea;
            font-weight: bold;
            margin-top: 15px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 10px;
            position: relative;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .step-line {
            width: 60px;
            height: 2px;
            background: #e9ecef;
            align-self: center;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            text-align: center;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="public-page">
        <div class="form-container">
            <div class="logo-section">
                <i class="bi bi-receipt-cutoff" style="font-size: 60px; color: #667eea;"></i>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            </div>
            
            <?php if (!$success): ?>
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                <div class="step-line"></div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
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
            
            <?php if ($success): ?>
            <!-- Success Message -->
            <div class="text-center">
                <div class="success-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h3>Ricevuta Caricata con Successo!</h3>
                <p class="mt-3">La tua ricevuta è stata inviata correttamente ed è in attesa di verifica.</p>
                <p>Riceverai una email di conferma non appena la richiesta sarà approvata.</p>
                <a href="pay_fee.php" class="btn btn-primary mt-4">
                    <i class="bi bi-arrow-left"></i> Carica Altra Ricevuta
                </a>
            </div>
            
            <?php elseif ($step === 1): ?>
            <!-- Step 1: Verify Member -->
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                <input type="hidden" name="action" value="verify">
                
                <p class="text-muted mb-4">
                    Inserisci la tua matricola e cognome per verificare l'identità prima di caricare la ricevuta.
                </p>
                
                <div class="mb-3">
                    <label for="registration_number" class="form-label">Matricola *</label>
                    <input type="text" class="form-control" id="registration_number" 
                           name="registration_number" required
                           value="<?php echo htmlspecialchars($_POST['registration_number'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="last_name" class="form-label">Cognome *</label>
                    <input type="text" class="form-control" id="last_name" 
                           name="last_name" required
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
                
                <?php if (!empty($config['recaptcha']['enabled'])): ?>
                <div class="mb-3">
                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha']['site_key'] ?? ''); ?>"></div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-arrow-right"></i> Continua
                </button>
            </form>
            
            <?php else: ?>
            <!-- Step 2: Upload Receipt -->
            <?php $member = $_SESSION['fee_payment_member']; ?>
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                <input type="hidden" name="action" value="submit">
                
                <div class="alert alert-info">
                    <strong>Socio Identificato:</strong><br>
                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?><br>
                    Matricola: <?php echo htmlspecialchars($member['registration_number']); ?>
                </div>
                
                <div class="mb-3">
                    <label for="payment_date" class="form-label">Data Pagamento *</label>
                    <input type="date" class="form-control" id="payment_date" 
                           name="payment_date" required
                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="payment_year" class="form-label">Anno Riferimento Quota *</label>
                    <select class="form-select" id="payment_year" name="payment_year" required>
                        <option value="">Seleziona anno...</option>
                        <?php
                        $currentYear = date('Y');
                        for ($year = $currentYear + 1; $year >= $currentYear - 5; $year--) {
                            $selected = (isset($_POST['payment_year']) && $_POST['payment_year'] == $year) ? 'selected' : '';
                            echo "<option value=\"{$year}\" {$selected}>{$year}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="receipt_file" class="form-label">Ricevuta di Pagamento *</label>
                    <input type="file" class="form-control" id="receipt_file" 
                           name="receipt_file" required
                           accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text">
                        Formati accettati: PDF, JPG, PNG. Dimensione massima: 5MB
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Invia Ricevuta
                    </button>
                    <a href="pay_fee.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Annulla
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
