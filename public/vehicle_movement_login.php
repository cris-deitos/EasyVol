<?php
/**
 * Public Vehicle Movement Management - Login
 * 
 * Public page for members to log in and manage vehicle movements
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\VehicleMovementController;

$app = App::getInstance();
$db = $app->getDb();
$config = $app->getConfig();

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registrationNumber = trim($_POST['registration_number'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    
    if (empty($registrationNumber) || empty($surname)) {
        $error = 'Inserire numero matricola e cognome';
    } else {
        $controller = new VehicleMovementController($db, $config);
        $member = $controller->authenticateMember($registrationNumber, $surname);
        
        if ($member) {
            // Store member info in session
            $_SESSION['vehicle_movement_member'] = [
                'id' => $member['id'],
                'registration_number' => $member['registration_number'],
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name']
            ];
            
            header('Location: vehicle_movement.php');
            exit;
        } else {
            $error = 'Credenziali non valide o qualifica non autorizzata. È necessario avere almeno la qualifica di AUTISTA o PILOTA.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimentazione Mezzi - Accesso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .login-body {
            padding: 30px;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-truck"></i>
            <h2 class="mb-0">Movimentazione Mezzi</h2>
            <p class="mb-0 mt-2">Accesso con Matricola e Cognome</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="registration_number" class="form-label">
                        <i class="bi bi-person-badge"></i> Numero Matricola
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="registration_number" 
                           name="registration_number" 
                           required 
                           autofocus
                           placeholder="Inserisci il tuo numero di matricola">
                </div>
                
                <div class="mb-4">
                    <label for="surname" class="form-label">
                        <i class="bi bi-person"></i> Cognome
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="surname" 
                           name="surname" 
                           required
                           placeholder="Inserisci il tuo cognome">
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Accedi
                </button>
            </form>
            
            <div class="mt-4 text-center">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> 
                    Per accedere è necessario avere la qualifica di AUTISTA o PILOTA NATANTE
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
