<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();

// Redirect to install if not configured
if (!$app->isInstalled()) {
    header("Location: install.php");
    exit;
}

// Redirect if already logged in
if ($app->isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Per favore inserisci username e password.';
    } else {
        try {
            $db = $app->getDb();
            
            // Get user with role
            $stmt = $db->query(
                "SELECT u.*, r.name as role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.username = ? AND u.is_active = 1",
                [$username]
            );
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
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
                
                // Merge permissions (user-specific permissions override role permissions)
                $permissionsMap = [];
                foreach ($rolePermissions as $perm) {
                    $key = $perm['module'] . '_' . $perm['action'];
                    $permissionsMap[$key] = $perm;
                }
                foreach ($userPermissions as $perm) {
                    $key = $perm['module'] . '_' . $perm['action'];
                    $permissionsMap[$key] = $perm;
                }
                $user['permissions'] = array_values($permissionsMap);
                
                // Update last login
                $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
                
                // Set session
                $_SESSION['user'] = $user;
                
                // Log activity
                $app->logActivity('login', 'auth', null, 'User logged in');
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = 'Username o password non corretti.';
                $app->logActivity('login_failed', 'auth', null, "Failed login attempt for username: $username");
            }
        } catch (Exception $e) {
            $error = 'Errore di sistema. Riprova più tardi.';
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
    <title>Login - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 450px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: none;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <div class="login-card card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-heart-pulse text-danger" style="font-size: 4rem;"></i>
                <h2 class="mt-3">EasyVol</h2>
                <p class="text-muted">Sistema Gestionale Protezione Civile</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right"></i> Accedi
                </button>
            </form>

            <hr>

            <div class="text-center">
                <small class="text-muted">
                    <a href="register.php" class="text-decoration-none">Registrazione Nuovo Socio</a>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
