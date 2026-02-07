<?php
/**
 * Reinvia email di benvenuto a utente
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\UserController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permission
if (!$app->checkPermission('users', 'edit')) {
    header('Location: users.php?error=access_denied');
    exit;
}

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId <= 0) {
    header('Location: users.php?error=invalid_id');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new UserController($db, $config);

// CSRF protection for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = new CsrfProtection();
    if (!$csrf->validate($_POST['csrf_token'] ?? '')) {
        header('Location: users.php?error=csrf');
        exit;
    }
    
    $currentUserId = $app->getUserId();
    $result = $controller->resendWelcomeEmail($userId, $currentUserId);
    
    if ($result === true) {
        header('Location: users.php?success=email_sent');
    } else {
        $error = is_array($result) && isset($result['error']) ? $result['error'] : 'Errore sconosciuto';
        header('Location: users.php?error=' . urlencode($error));
    }
    exit;
}

// Show confirmation page for GET request
// Use $targetUser to avoid variable collision with navbar.php which sets $user to logged-in user
$targetUser = $controller->get($userId);
if (!$targetUser) {
    header('Location: users.php?error=not_found');
    exit;
}

$csrf = new CsrfProtection();
$pageTitle = 'Reinvia Email di Benvenuto';
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
                        <a href="users.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Attenzione!</strong> Questa operazione:
                            <ul class="mb-0 mt-2">
                                <li>Resetterà la password dell'utente alla password predefinita</li>
                                <li>L'utente dovrà cambiarla al prossimo accesso</li>
                                <li>Invierà un'email con le nuove credenziali</li>
                            </ul>
                        </div>
                        
                        <h5>Dettagli Utente:</h5>
                        <dl class="row">
                            <dt class="col-sm-3">Username:</dt>
                            <dd class="col-sm-9"><strong><?php echo htmlspecialchars($targetUser['username']); ?></strong></dd>
                            
                            <dt class="col-sm-3">Nome:</dt>
                            <dd class="col-sm-9"><?php echo htmlspecialchars($targetUser['full_name'] ?? '-'); ?></dd>
                            
                            <dt class="col-sm-3">Email:</dt>
                            <dd class="col-sm-9"><strong><?php echo htmlspecialchars($targetUser['email']); ?></strong></dd>
                            
                            <dt class="col-sm-3">Ruolo:</dt>
                            <dd class="col-sm-9"><?php echo htmlspecialchars($targetUser['role_name'] ?? 'Nessuno'); ?></dd>
                        </dl>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            L'email verrà inviata a: <strong><?php echo htmlspecialchars($targetUser['email']); ?></strong>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                            
                            <div class="border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-envelope"></i> Conferma e Invia Email
                                </button>
                                <a href="users.php" class="btn btn-secondary">
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
