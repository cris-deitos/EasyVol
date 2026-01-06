<?php
/**
 * Member Portal - Step 2: Code Verification
 * 
 * Verify the code sent to member's email
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\MemberPortalController;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\AutoLogger;

// Assicurati che la sessione sia avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$app = App::getInstance(); // Public page - no authentication required

// Redirect to install if not configured
if (!$app->isInstalled()) {
    header("Location: install.php");
    exit;
}

// Check if member ID is in session (came from verification page)
if (!isset($_SESSION['portal_member_id'])) {
    header("Location: member_portal_verify.php");
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MemberPortalController($db, $config);

// Log page access
AutoLogger::logPageAccess();

$error = '';
$memberId = $_SESSION['portal_member_id'];
$email = $_SESSION['portal_email'] ?? '';

// Mask email for display
$maskedEmail = '';
if ($email) {
    $parts = explode('@', $email);
    if (count($parts) == 2) {
        $localPart = $parts[0];
        $domain = $parts[1];
        $maskedLocal = substr($localPart, 0, 2) . str_repeat('*', max(0, strlen($localPart) - 2));
        $maskedEmail = $maskedLocal . '@' . $domain;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token di sicurezza non valido. Ricarica la pagina e riprova.';
    } else {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        
        // Validate input
        if (empty($code)) {
            $error = 'Inserisci il codice di verifica.';
        } else {
            // Verify code
            $valid = $controller->verifyCode($memberId, $code);
            
            if ($valid) {
                // Code is valid, set session flag and redirect to update page
                $_SESSION['portal_verified'] = true;
                header("Location: member_portal_update.php");
                exit;
            } else {
                $error = 'Codice non valido o scaduto. Richiedi un nuovo codice.';
            }
        }
    }
}

// Handle resend request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $sent = $controller->sendVerificationCode($memberId, $email);
    if ($sent) {
        $success = 'Nuovo codice inviato via email.';
    } else {
        $error = 'Errore nell\'invio dell\'email. Riprova più tardi.';
    }
}

$associationName = $config['association']['name'] ?? 'Associazione';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portale Soci - Verifica Codice - <?= htmlspecialchars($associationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
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
        
        .portal-container {
            position: relative;
            z-index: 1;
            max-width: 550px;
            width: 100%;
            padding: 20px;
        }
        
        .portal-card {
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
        
        .logo-container {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo-container h2 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .logo-container p {
            font-size: 14px;
            color: #6c757d;
            font-weight: 400;
        }
        
        .step-indicator {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .step-indicator .steps {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .step-line.completed {
            background: #28a745;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .code-input {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 8px;
            text-transform: uppercase;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 25px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
            text-align: center;
        }
        
        .info-box .email-sent-icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .info-box p {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .resend-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .resend-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .back-link a {
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-card">
            <div class="logo-container">
                <h2><i class="bi bi-shield-check"></i> Portale Soci</h2>
                <p><?= htmlspecialchars($associationName) ?></p>
            </div>
            
            <div class="step-indicator">
                <div class="steps">
                    <div class="step completed"><i class="bi bi-check"></i></div>
                    <div class="step-line completed"></div>
                    <div class="step active">2</div>
                    <div class="step-line"></div>
                    <div class="step">3</div>
                </div>
                <p class="mt-2 mb-0" style="font-size: 14px; color: #6c757d;">Verifica Codice Email</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <div class="email-sent-icon">
                    <i class="bi bi-envelope-check"></i>
                </div>
                <p><strong>Codice inviato via email</strong></p>
                <p>Abbiamo inviato un codice di verifica a:</p>
                <p><strong><?= htmlspecialchars($maskedEmail) ?></strong></p>
                <p style="font-size: 12px; margin-top: 10px;">Il codice scadrà tra 15 minuti</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
                
                <div class="mb-3">
                    <label for="code" class="form-label text-center d-block">
                        <i class="bi bi-key"></i> Codice di Verifica
                    </label>
                    <input type="text" 
                           class="form-control code-input" 
                           id="code" 
                           name="code" 
                           required 
                           autofocus
                           maxlength="10"
                           placeholder="XXXXXXXX"
                           autocomplete="off">
                    <small class="form-text text-muted d-block text-center mt-2">
                        Inserisci il codice ricevuto via email
                    </small>
                </div>
                
                <button type="submit" class="btn btn-submit">
                    <i class="bi bi-check-circle"></i> Verifica Codice
                </button>
            </form>
            
            <div class="resend-link">
                <a href="?resend=1"><i class="bi bi-arrow-repeat"></i> Non hai ricevuto il codice? Invia di nuovo</a>
            </div>
            
            <div class="back-link">
                <a href="member_portal_verify.php"><i class="bi bi-arrow-left"></i> Torna indietro</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format code input
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    </script>
</body>
</html>
