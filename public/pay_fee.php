<?php
/**
 * Caricamento Ricevuta Pagamento Quota
 * 
 * Pagina pubblica per il caricamento delle ricevute di pagamento quote associative
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\FeePaymentController;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\FileUploader;

$app = App::getInstance();

// Get app configuration for CAPTCHA
$db = $app->getDb();
$config = $app->getConfig();
$controller = new FeePaymentController($db, $config);

// Log page access
AutoLogger::logPageAccess();

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
            $amount = $_POST['amount'] ?? '';
            
            if (empty($paymentDate)) {
                $errors[] = 'La data di pagamento è obbligatoria';
            }
            if (empty($paymentYear) || !is_numeric($paymentYear)) {
                $errors[] = 'L\'anno di riferimento è obbligatorio';
            }
            if (empty($amount) || !is_numeric($amount) || floatval($amount) <= 0) {
                $errors[] = 'L\'importo pagato è obbligatorio e deve essere maggiore di zero';
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
                    // Convert absolute path to relative path for storage
                    // Get the document root path
                    $docRoot = realpath(__DIR__ . '/..');
                    $uploadedFile = realpath($uploadResult['path']);
                    
                    // Calculate relative path from document root
                    if ($uploadedFile && strpos($uploadedFile, $docRoot) === 0) {
                        $relativePath = substr($uploadedFile, strlen($docRoot) + 1);
                        // Normalize path separators for cross-platform compatibility
                        $relativePath = str_replace('\\', '/', $relativePath);
                    } else {
                        // Fallback: use the original method if realpath fails
                        $relativePath = str_replace(__DIR__ . '/../', '', $uploadResult['path']);
                    }
                    
                    // Create payment request
                    $requestData = [
                        'registration_number' => $member['registration_number'],
                        'last_name' => $member['last_name'],
                        'payment_year' => $paymentYear,
                        'payment_date' => $paymentDate,
                        'amount' => floatval($amount),
                        'receipt_file' => $relativePath
                    ];
                    
                    $requestId = $controller->createPaymentRequest($requestData);
                    
                    if ($requestId) {
                        // Send emails
                        $controller->sendSubmissionEmails($member, $requestData);
                        
                        // Send Telegram notification for payment submission
                        try {
                            require_once __DIR__ . '/../src/Services/TelegramService.php';
                            $telegramService = new \EasyVol\Services\TelegramService($db, $config);
                            
                            if ($telegramService->isEnabled()) {
                                $message = "📤 <b>Nuova ricevuta pagamento quota caricata</b>\n\n";
                                $message .= "👤 <b>Socio:</b> " . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . "\n";
                                $message .= "🔢 <b>Matricola:</b> " . htmlspecialchars($member['registration_number']) . "\n";
                                $message .= "📅 <b>Anno:</b> " . $paymentYear . "\n";
                                $message .= "💵 <b>Data pagamento:</b> " . date('d/m/Y', strtotime($paymentDate)) . "\n";
                                if ($amount) {
                                    $message .= "💸 <b>Importo:</b> €" . number_format(floatval($amount), 2, ',', '.') . "\n";
                                }
                                $message .= "\nℹ️ In attesa di verifica e approvazione";
                                
                                $telegramService->sendNotification('fee_payment', $message);
                            }
                        } catch (\Exception $e) {
                            error_log("Errore invio notifica Telegram per caricamento pagamento: " . $e->getMessage());
                            // Don't fail the submission if notification fails
                        }
                        
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <?php if (!empty($config['recaptcha']['enabled']) && $step === 1): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 20px 0;
        }
        
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }
        
        body::before {
            width: 500px;
            height: 500px;
            top: -250px;
            right: -250px;
            animation-delay: 0s;
        }
        
        body::after {
            width: 400px;
            height: 400px;
            bottom: -200px;
            left: -200px;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-50px) rotate(180deg); }
        }
        
        .public-page {
            position: relative;
            z-index: 1;
            max-width: 600px;
            width: 100%;
            padding: 20px;
            margin: auto;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 50px 40px;
            animation: fadeIn 0.8s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo-section h1 {
            font-size: 28px;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-top: 15px;
            margin-bottom: 8px;
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
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .step.active {
            background: var(--primary-gradient);
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
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-control,
        .form-select {
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-text {
            font-size: 13px;
            color: #6c757d;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            background: var(--primary-gradient);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            background: var(--primary-gradient);
        }
        
        .btn-secondary {
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            border: 2px solid #6c757d;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 25px;
        }
        
        .alert-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            color: #495057;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            text-align: center;
            margin: 30px 0;
        }
        
        .text-muted {
            font-size: 14px;
            color: #6c757d;
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
                           placeholder="Es: 1 per Volontari, C-1 per Cadetti"
                           value="<?php echo htmlspecialchars($_POST['registration_number'] ?? ''); ?>">
                    <div class="form-text">
                        Inserisci il numero di matricola.  Per i Cadetti usa il prefisso C- (es:  C-1)
                    </div>
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
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Attenzione:</strong><br>
                    Nel caso in cui un pagamento unico copra la quota di due o più soci, 
                    la ricevuta dovrà essere caricata per ogni socio singolarmente.
                </div>
                
                <div class="mb-3">
                    <label for="payment_date" class="form-label">Data Pagamento *</label>
                    <input type="date" class="form-control" id="payment_date" 
                           name="payment_date" required
                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="amount" class="form-label">Importo Pagato (€) *</label>
                    <input type="number" class="form-control" id="amount" 
                           name="amount" required step="0.01" min="0.01"
                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    <div class="form-text">
                        Inserire l'importo effettivamente pagato per questa quota
                    </div>
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
