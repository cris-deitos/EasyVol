<?php
/**
 * Member Portal - Step 1: Member Verification
 * 
 * Public page for adult active members to verify their identity
 * by providing registration number and last name
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
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token di sicurezza non valido. Ricarica la pagina e riprova.';
    } else {
        $registrationNumber = trim($_POST['registration_number'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        
        // Validate input
        if (empty($registrationNumber) || empty($lastName)) {
            $error = 'Inserisci matricola e cognome.';
        } else {
            // Verify member
            $member = $controller->verifyMember($registrationNumber, $lastName);
            
            if (!$member) {
                $error = 'Matricola o cognome non corretto, oppure socio non attivo. Verifica i dati inseriti.';
            } else {
                // Get member email
                $email = $controller->getMemberEmail($member['id']);
                
                if (!$email) {
                    $error = 'Nessuna email associata al tuo profilo. Contatta la Segreteria.';
                } else {
                    // Check if member already has a valid verification code
                    $existingCode = $controller->hasValidVerificationCode($member['id']);
                    
                    if ($existingCode) {
                        // Valid code exists, go to verification page without sending new email
                        $_SESSION['portal_member_id'] = $member['id'];
                        $_SESSION['portal_email'] = $email;
                        
                        // Redirect to code verification page
                        header("Location: member_portal_code.php");
                        exit;
                    } else {
                        // No valid code exists, send a new one
                        $sent = $controller->sendVerificationCode($member['id'], $email);
                        
                        if ($sent) {
                            // Store member ID in session for next step
                            $_SESSION['portal_member_id'] = $member['id'];
                            $_SESSION['portal_email'] = $email;
                            
                            // Redirect to code verification page
                            header("Location: member_portal_code.php");
                            exit;
                        } else {
                            $error = 'Errore nell\'invio dell\'email. Riprova più tardi o contatta la Segreteria.';
                        }
                    }
                }
            }
        }
    }
}

$associationName = $config['association']['name'] ?? 'Associazione';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portale Soci - Verifica Identità - <?= htmlspecialchars($associationName) ?></title>
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
        }
        
        .info-box h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .info-box li {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
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
                <h2><i class="bi bi-person-badge"></i> Portale Soci</h2>
                <p><?= htmlspecialchars($associationName) ?></p>
            </div>
            
            <div class="step-indicator">
                <div class="steps">
                    <div class="step active">1</div>
                    <div class="step-line"></div>
                    <div class="step">2</div>
                    <div class="step-line"></div>
                    <div class="step">3</div>
                </div>
                <p class="mt-2 mb-0" style="font-size: 14px; color: #6c757d;">Verifica Identità</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h6><i class="bi bi-info-circle"></i> Accesso al Portale</h6>
                <p style="font-size: 14px; color: #6c757d; margin-bottom: 10px;">
                    Benvenuto nel portale per l'aggiornamento dei tuoi dati. Per accedere:
                </p>
                <ul>
                    <li>Inserisci la tua <strong>matricola</strong> e <strong>cognome</strong></li>
                    <li>Devi essere un socio <strong>maggiorenne</strong> e <strong>attivo</strong></li>
                    <li>Riceverai un codice di verifica via email</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
                
                <div class="mb-3">
                    <label for="registration_number" class="form-label">
                        <i class="bi bi-hash"></i> Matricola
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="registration_number" 
                           name="registration_number" 
                           required 
                           autofocus
                           placeholder="Es: 1"
                           value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>">
                </div>
                
                <div class="mb-3">
                    <label for="last_name" class="form-label">
                        <i class="bi bi-person"></i> Cognome
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="last_name" 
                           name="last_name" 
                           required 
                           placeholder="Il tuo cognome"
                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn btn-submit">
                    <i class="bi bi-arrow-right-circle"></i> Procedi
                </button>
            </form>
            
            <div class="back-link">
                <a href="login.php"><i class="bi bi-arrow-left"></i> Torna al Login</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
