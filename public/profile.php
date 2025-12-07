<?php
/**
 * Profilo Utente
 * 
 * Pagina per visualizzare e modificare il profilo dell'utente corrente
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = $app->getDb();
$userId = $app->getUserId();
$user = $app->getCurrentUser();

$errors = [];
$success = false;

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? '')
        ];
        
        // Validazione
        if (empty($data['full_name'])) {
            $errors[] = 'Il nome completo è obbligatorio';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'L\'email è obbligatoria';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida';
        }
        
        // Gestione cambio password
        $updatePassword = false;
        if (!empty($_POST['new_password'])) {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $currentPassword = $_POST['current_password'] ?? '';
            
            // Verifica password attuale
            $stmt = $db->query("SELECT password FROM users WHERE id = ?", [$userId]);
            $userDb = $stmt->fetch();
            
            if (!password_verify($currentPassword, $userDb['password'])) {
                $errors[] = 'Password attuale non corretta';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'La nuova password deve essere di almeno 8 caratteri';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Le nuove password non coincidono';
            } else {
                $updatePassword = true;
                $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
        }
        
        if (empty($errors)) {
            try {
                // Aggiorna profilo
                $sql = "UPDATE users SET full_name = ?, email = ?";
                $params = [$data['full_name'], $data['email']];
                
                if ($updatePassword) {
                    $sql .= ", password = ?";
                    $params[] = $data['password'];
                }
                
                $sql .= ", updated_at = NOW() WHERE id = ?";
                $params[] = $userId;
                
                $db->query($sql, $params);
                
                $success = true;
                
                // Ricarica i dati utente
                $user = $app->getCurrentUser();
            } catch (\Exception $e) {
                $errors[] = 'Errore durante l\'aggiornamento del profilo: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Il Mio Profilo';
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
                    <h1 class="h2"><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Errori:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Profilo aggiornato con successo!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Informazioni Profilo</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                        <small class="form-text text-muted">L'username non può essere modificato</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Nome Completo *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role_name" class="form-label">Ruolo</label>
                                        <input type="text" class="form-control" id="role_name" 
                                               value="<?php echo htmlspecialchars($user['role_name'] ?? 'N/D'); ?>" disabled>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h6 class="mb-3">Cambio Password</h6>
                                    <p class="text-muted small">Lasciare vuoto per mantenere la password attuale</p>
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Password Attuale</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Nuova Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="8">
                                        <small class="form-text text-muted">Minimo 8 caratteri</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Conferma Nuova Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva Modifiche
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informazioni Account</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Stato:</strong> 
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Non Attivo</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Creato il:</strong><br>
                                    <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                </p>
                                <?php if (!empty($user['last_login'])): ?>
                                <p><strong>Ultimo accesso:</strong><br>
                                    <?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
