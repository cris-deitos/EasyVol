<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\UserController;

$app = App::getInstance();
$db = $app->getDb();

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if user needs to change password
if (!isset($_SESSION['must_change_password_user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['must_change_password_user_id'];
$error = '';
$success = false;

// Log page access
AutoLogger::logPageAccess();

// Get user info
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    unset($_SESSION['must_change_password_user_id']);
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword)) {
        $error = 'La nuova password è obbligatoria.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'La password deve essere di almeno 8 caratteri.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Le password non coincidono.';
    } else {
        // Verify the new password is not the same as the default
        // Check against current hashed password (which is the default password)
        if (password_verify($newPassword, $user['password'])) {
            // If the new password matches their current password (which is default), reject it
            $error = 'Non puoi utilizzare la password predefinita. Scegli una password diversa.';
        }
    }
    
    if (empty($error)) {
        try {
            $config = $app->getConfig();
            $controller = new UserController($db, $config);
            
            // Update password and clear must_change_password flag
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $db->execute(
                "UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?",
                [$hashedPassword, $userId]
            );
            
            // Log the password change
            $db->execute(
                "INSERT INTO activity_logs (user_id, module, action, record_id, description, ip_address, created_at) 
                 VALUES (?, 'users', 'password_changed', ?, 'Password changed after forced reset', ?, NOW())",
                [$userId, $userId, $_SERVER['REMOTE_ADDR'] ?? null]
            );
            
            // Clear the session variable
            unset($_SESSION['must_change_password_user_id']);
            
            // Now log the user in
            // Get fresh user data
            $user = $db->fetchOne(
                "SELECT u.*, r.name as role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?",
                [$userId]
            );
            
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
            
            // Merge permissions
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
            $db->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            
            // Set session
            $_SESSION['user'] = $user;
            
            // Send Telegram notification for user login (first access after password change)
            try {
                require_once __DIR__ . '/../src/Services/TelegramService.php';
                $telegramService = new \EasyVol\Services\TelegramService($db, $config);
                
                if ($telegramService->isEnabled()) {
                    // Format login date and time in Italian format
                    $loginDateTime = date('d/m/Y H:i:s');
                    
                    // Build notification message with user details
                    $message = "🔐 <b>Nuovo Accesso al Gestionale</b>\n\n";
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
                    $message .= "ℹ️ <b>Nota:</b> Primo accesso dopo cambio password\n";
                    
                    // Send notification to configured recipients
                    $telegramService->sendNotification('user_login', $message);
                }
            } catch (\Exception $e) {
                error_log("Errore invio notifica Telegram per primo login utente: " . $e->getMessage());
                // Don't fail login if notification fails
            }
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
            
        } catch (Exception $e) {
            $error = 'Errore durante il cambio password. Riprova.';
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambio Password Obbligatorio - EasyVol</title>
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
        
        .change-container {
            position: relative;
            z-index: 1;
            max-width: 500px;
            width: 100%;
            padding: 20px;
        }
        
        .change-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 50px 40px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .change-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo-container img {
            width: 100px;
            height: 100px;
            margin-bottom: 15px;
            animation: fadeInDown 0.8s ease;
        }
        
        .logo-container h2 {
            font-size: 26px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
        
        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 15px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s ease;
            border-left: none;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        
        .btn-change {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .btn-change:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #ffd93d 0%, #f59e0b 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="change-container">
        <div class="change-card">
            <div class="logo-container">
                <img src="../assets/images/easyvol-logo.svg" alt="Protezione Civile Logo">
                <h2>Cambio Password Richiesto</h2>
                <p>Per motivi di sicurezza, devi cambiare la tua password</p>
            </div>

            <div class="alert alert-warning mb-4">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Benvenuto, <?= htmlspecialchars($user['username']) ?>!</strong><br>
                Devi cambiare la password predefinita prima di procedere.
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" name="new_password" 
                           placeholder="Nuova Password" required minlength="8" autofocus>
                </div>

                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" name="confirm_password" 
                           placeholder="Conferma Password" required minlength="8">
                </div>

                <div class="mb-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        La password deve essere di almeno 8 caratteri e diversa da quella predefinita.
                    </small>
                </div>

                <button type="submit" class="btn btn-change btn-primary w-100">
                    <i class="bi bi-check-circle me-2"></i>Cambia Password
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
