<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\RateLimiter;

$app = App::getInstance();

// Redirect to install if not configured
if (!$app->isInstalled()) {
    header("Location: install.php");
    exit;
}

// Redirect if already logged in
if ($app->isLoggedIn()) {
    $user = $app->getCurrentUser();
    // Check if user is operations center user
    if (isset($user['is_operations_center_user']) && $user['is_operations_center_user']) {
        header("Location: operations_center.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token di sicurezza non valido. Ricarica la pagina e riprova.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Per favore inserisci username e password.';
        } else {
            // Check rate limiting by IP address
            $db = $app->getDb();
            $rateLimiter = new RateLimiter($db);
            $clientIp = RateLimiter::getClientIp();
            $rateCheck = $rateLimiter->check($clientIp, 'login_co');
            
            if (!$rateCheck['allowed']) {
                $error = 'Troppi tentativi di accesso. Riprova tra qualche minuto.';
                $app->logActivity('login_co_rate_limited', 'auth', null, "Rate limited EasyCO login attempt from IP: $clientIp");
            } else {
                try {
                    // Get user with role - MUST be operations center user
                    $stmt = $db->query(
                        "SELECT u.*, r.name as role_name 
                        FROM users u 
                        LEFT JOIN roles r ON u.role_id = r.id 
                        WHERE u.username = ? AND u.is_active = 1 AND u.is_operations_center_user = 1",
                        [$username]
                    );
                    
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Record successful attempt and reset rate limit
                        $rateLimiter->recordAttempt($clientIp, 'login_co', true);
                        $rateLimiter->reset($clientIp, 'login_co');
                // Check if password change is required
                if ($user['must_change_password']) {
                    // Store user id in session for password change
                    $_SESSION['must_change_password_user_id'] = $user['id'];
                    header("Location: change_password.php");
                    exit;
                }
                
                // Get role-based permissions
                $rolePermissions = [];
                if ($user['role_id']) {
                    $stmt = $db->query(
                        "SELECT p.* FROM permissions p
                        INNER JOIN role_permissions rp ON p.id = rp.permission_id
                        WHERE rp.role_id = ?",
                        [$user['role_id']]
                    );
                    $rolePermissions = $stmt->fetchAll();
                }
                
                // Get user-specific permissions
                $stmt = $db->query(
                    "SELECT p.* FROM permissions p
                    INNER JOIN user_permissions up ON p.id = up.permission_id
                    WHERE up.user_id = ?",
                    [$user['id']]
                );
                $userPermissions = $stmt->fetchAll();
                
                // Merge permissions (user-specific permissions supplement role permissions)
                $permissionsMap = [];
                foreach ($rolePermissions as $perm) {
                    $key = $perm['module'] . '::' . $perm['action'];
                    $permissionsMap[$key] = $perm;
                }
                foreach ($userPermissions as $perm) {
                    $key = $perm['module'] . '::' . $perm['action'];
                    $permissionsMap[$key] = $perm;
                }
                $user['permissions'] = array_values($permissionsMap);
                
                // Update last login
                $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
                
                // Set session
                $_SESSION['user'] = $user;
                
                // Log activity
                $app->logActivity('login_co', 'auth', null, 'EasyCO user logged in');
                
                // Send Telegram notification for user login
                try {
                    require_once __DIR__ . '/../src/Services/TelegramService.php';
                    $telegramService = new \EasyVol\Services\TelegramService($db, $app->getConfig());
                    
                    if ($telegramService->isEnabled()) {
                        // Format login date and time in Italian format
                        $loginDateTime = date('d/m/Y H:i:s');
                        
                        // Build notification message with user details
                        $message = "🔐 <b>Nuovo Accesso al Gestionale (EasyCO)</b>\n\n";
                        $message .= "👤 <b>Username:</b> " . htmlspecialchars($user['username']) . "\n";
                        
                        if (!empty($user['full_name'])) {
                            // Split full name into first name and last name
                            $nameParts = explode(' ', trim($user['full_name']), 2);
                            $firstName = $nameParts[0] ?? '';
                            $lastName = $nameParts[1] ?? '';
                            
                            if (!empty($firstName)) {
                                $message .= "📝 <b>Nome:</b> " . htmlspecialchars($firstName) . "\n";
                            }
                            if (!empty($lastName)) {
                                $message .= "📝 <b>Cognome:</b> " . htmlspecialchars($lastName) . "\n";
                            }
                        }
                        
                        if (!empty($user['role_name'])) {
                            $message .= "👔 <b>Profilo:</b> " . htmlspecialchars($user['role_name']) . "\n";
                        }
                        
                        $message .= "📅 <b>Data e Ora:</b> " . $loginDateTime . "\n";
                        
                        // Send notification to configured recipients
                        $telegramService->sendNotification('user_login', $message);
                    }
                } catch (\Exception $e) {
                    error_log("Errore invio notifica Telegram per login utente: " . $e->getMessage());
                    // Don't fail login if notification fails
                }
                
                    header("Location: operations_center.php");
                    exit;
                } else {
                    // Record failed attempt
                    $rateLimiter->recordAttempt($clientIp, 'login_co', false);
                    
                    $error = 'Username o password non corretti, oppure non hai accesso a EasyCO.';
                    $app->logActivity('login_co_failed', 'auth', null, "Failed EasyCO login attempt for username: $username");
                }
            } catch (Exception $e) {
                $error = 'Errore di sistema. Riprova più tardi.';
                error_log($e->getMessage());
            }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EasyCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/easyco.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background circles */
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
        
        .login-container {
            position: relative;
            z-index: 1;
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 50px 40px;
            transition: box-shadow 0.3s ease;
        }
        
        .login-card:hover {
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo-container i {
            font-size: 80px;
            background: linear-gradient(135deg, #ff8c00 0%, #ff6b00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            display: block;
            animation: fadeInDown 0.8s ease;
        }
        
        .logo-container h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            animation: fadeInDown 0.8s ease 0.2s backwards;
        }
        
        .logo-container p {
            font-size: 14px;
            color: #6c757d;
            font-weight: 400;
            animation: fadeInDown 0.8s ease 0.4s backwards;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .input-group {
            margin-bottom: 25px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s ease;
            border-left: none;
        }
        
        .btn-login {
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }
    </style>
</head>
<body class="easyco-login">
    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <i class="bi bi-broadcast-pin"></i>
                <h2>EasyCO</h2>
                <p>Centrale Operativa - Sistema di Emergenza</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
                
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Username" required autofocus>
                </div>

                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>

                <button type="submit" class="btn btn-login btn-easyco-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Accedi a EasyCO
                </button>
            </form>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
