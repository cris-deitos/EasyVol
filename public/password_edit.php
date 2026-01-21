<?php
/**
 * Gestione Password - Crea/Modifica/Visualizza
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\PasswordController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PasswordController($db, $config);
$csrf = new CsrfProtection();
$user = $app->getCurrentUser();

$passwordId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $passwordId > 0;
$isEditMode = $isEdit && isset($_GET['edit']);

// Verifica permessi
if ($isEdit && !$app->checkPermission('password_management', 'view')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('password_management', 'create')) {
    die('Accesso negato');
}
if ($isEditMode && !$app->checkPermission('password_management', 'edit')) {
    die('Accesso negato');
}

$password = null;
$errors = [];
$success = false;

if ($isEdit) {
    $password = $controller->getPassword($passwordId, $user['id'], $isEditMode);
    if (!$password) {
        header('Location: passwords.php?error=not_found');
        exit;
    }
    
    // Check if user can edit
    $canEdit = ($password['created_by'] == $user['id']) || 
               $controller->userHasAccess($passwordId, $user['id'], true);
    
    // Log page access
    $logAction = $isEditMode ? 'password_edit_page' : 'password_view_page';
    $logDesc = $isEditMode ? 'Apertura pagina modifica password' : 'Apertura pagina visualizzazione password';
    $logSql = "INSERT INTO activity_logs (user_id, action, module, record_id, description, ip_address, user_agent)
               VALUES (?, ?, 'password_management', ?, ?, ?, ?)";
    $db->execute($logSql, [
        $user['id'],
        $logAction,
        $passwordId,
        $logDesc,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
} else {
    AutoLogger::logPageAccess();
}

// Get all users for permission management (only if creator)
$allUsers = [];
$passwordPermissions = [];
if ($isEdit && $password['created_by'] == $user['id']) {
    $allUsers = $controller->getAllUsers($user['id']);
    $passwordPermissions = $controller->getPasswordPermissions($passwordId, $user['id']);
}

// Gestione form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'save_password':
                $data = [
                    'title' => trim($_POST['title'] ?? ''),
                    'link' => trim($_POST['link'] ?? ''),
                    'username' => trim($_POST['username'] ?? ''),
                    'password' => $_POST['password'] ?? '',
                    'notes' => trim($_POST['notes'] ?? '')
                ];
                
                // Validazione
                if (empty($data['title'])) {
                    $errors[] = 'Il titolo è obbligatorio';
                }
                
                if (empty($data['password']) && !$isEdit) {
                    $errors[] = 'La password è obbligatoria';
                }
                
                if (empty($errors)) {
                    if ($isEdit) {
                        // Update
                        $result = $controller->update($passwordId, $data, $user['id']);
                        if ($result) {
                            $success = true;
                            $_SESSION['success_message'] = 'Password aggiornata con successo';
                            header('Location: password_edit.php?id=' . $passwordId);
                            exit;
                        } else {
                            $errors[] = 'Errore durante l\'aggiornamento della password';
                        }
                    } else {
                        // Create
                        $newId = $controller->create($data, $user['id']);
                        if ($newId) {
                            $success = true;
                            $_SESSION['success_message'] = 'Password creata con successo';
                            header('Location: password_edit.php?id=' . $newId);
                            exit;
                        } else {
                            $errors[] = 'Errore durante la creazione della password';
                        }
                    }
                }
                break;
                
            case 'grant_permission':
                if ($password['created_by'] != $user['id']) {
                    $errors[] = 'Solo il creatore può gestire i permessi';
                } else {
                    $targetUserId = intval($_POST['user_id'] ?? 0);
                    $canView = isset($_POST['can_view']) ? 1 : 0;
                    $canEdit = isset($_POST['can_edit']) ? 1 : 0;
                    
                    if ($targetUserId > 0) {
                        $result = $controller->grantPermission($passwordId, $targetUserId, $canView, $canEdit, $user['id']);
                        if ($result) {
                            $_SESSION['success_message'] = 'Permesso concesso con successo';
                            header('Location: password_edit.php?id=' . $passwordId);
                            exit;
                        } else {
                            $errors[] = 'Errore durante la concessione del permesso';
                        }
                    } else {
                        $errors[] = 'Utente non valido';
                    }
                }
                break;
                
            case 'revoke_permission':
                if ($password['created_by'] != $user['id']) {
                    $errors[] = 'Solo il creatore può gestire i permessi';
                } else {
                    $targetUserId = intval($_POST['user_id'] ?? 0);
                    if ($targetUserId > 0) {
                        $result = $controller->revokePermission($passwordId, $targetUserId, $user['id']);
                        if ($result) {
                            $_SESSION['success_message'] = 'Permesso revocato con successo';
                            header('Location: password_edit.php?id=' . $passwordId);
                            exit;
                        } else {
                            $errors[] = 'Errore durante la revoca del permesso';
                        }
                    }
                }
                break;
        }
    }
}

// Check for success message
$successMessage = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

$pageTitle = $isEdit ? ($isEditMode ? 'Modifica Password' : 'Visualizza Password') : 'Nuova Password';
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
    <style>
        .password-field {
            font-family: monospace;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-key"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="passwords.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna all'elenco
                        </a>
                    </div>
                </div>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
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
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <?php if ($isEdit && !$isEditMode): ?>
                                        Dettagli Password
                                    <?php else: ?>
                                        Informazioni Password
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($isEdit && !$isEditMode): ?>
                                    <!-- View Mode -->
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Titolo:</strong></label>
                                        <p><?php echo htmlspecialchars($password['title']); ?></p>
                                    </div>
                                    
                                    <?php if (!empty($password['link'])): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Link:</strong></label>
                                            <p>
                                                <a href="<?php echo htmlspecialchars($password['link']); ?>" 
                                                   target="_blank" 
                                                   rel="noopener noreferrer"
                                                   class="btn btn-outline-primary">
                                                    <i class="bi bi-box-arrow-up-right"></i> LINK DI ACCESSO
                                                </a>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($password['username'])): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Nome Utente:</strong></label>
                                            <p><?php echo htmlspecialchars($password['username']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Password:</strong></label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control password-field" 
                                                   id="password-display" 
                                                   value="<?php echo htmlspecialchars($password['password_decrypted'] ?? ''); ?>" 
                                                   readonly>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                                <i class="bi bi-eye" id="toggle-icon"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyPassword()">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($password['notes'])): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Note:</strong></label>
                                            <p class="border p-3 bg-light"><?php echo nl2br(htmlspecialchars($password['notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Creato da:</strong></label>
                                        <p><?php echo htmlspecialchars($password['creator_name'] ?? $password['creator_username']); ?></p>
                                    </div>
                                    
                                    <?php if ($canEdit): ?>
                                        <a href="password_edit.php?id=<?php echo $passwordId; ?>&edit=1" class="btn btn-warning">
                                            <i class="bi bi-pencil"></i> Modifica
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Edit/Create Mode -->
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                        <input type="hidden" name="action" value="save_password">
                                        
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Titolo <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="title" name="title" 
                                                   value="<?php echo htmlspecialchars($password['title'] ?? ''); ?>" 
                                                   required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="link" class="form-label">Link</label>
                                            <input type="url" class="form-control" id="link" name="link" 
                                                   value="<?php echo htmlspecialchars($password['link'] ?? ''); ?>" 
                                                   placeholder="https://...">
                                            <div class="form-text">Link del sito o applicazione (opzionale)</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Nome Utente</label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?php echo htmlspecialchars($password['username'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="password" class="form-label">
                                                Password <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="password" class="form-control password-field" id="password" name="password" 
                                                       value="<?php echo $isEdit ? htmlspecialchars($password['password_decrypted'] ?? '') : ''; ?>"
                                                       <?php if (!$isEdit): ?>required<?php endif; ?>>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordEdit()">
                                                    <i class="bi bi-eye" id="toggle-icon-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" type="button" onclick="generatePassword()">
                                                    <i class="bi bi-arrow-repeat"></i> Genera
                                                </button>
                                            </div>
                                            <?php if ($isEdit): ?>
                                                <div class="form-text">Lascia vuoto per mantenere la password corrente</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Note</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($password['notes'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Salva
                                            </button>
                                            <a href="passwords.php" class="btn btn-secondary">
                                                <i class="bi bi-x-circle"></i> Annulla
                                            </a>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isEdit && $password['created_by'] == $user['id']): ?>
                        <!-- Permission Management Panel -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-shield-lock"></i> Gestione Permessi
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small">
                                        Come creatore, puoi condividere questa password con altri utenti.
                                    </p>
                                    
                                    <!-- Add Permission Form -->
                                    <form method="POST" action="" class="mb-4">
                                        <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                        <input type="hidden" name="action" value="grant_permission">
                                        
                                        <div class="mb-3">
                                            <label for="user_id" class="form-label">Utente</label>
                                            <select class="form-select" id="user_id" name="user_id" required>
                                                <option value="">Seleziona utente...</option>
                                                <?php foreach ($allUsers as $u): ?>
                                                    <option value="<?php echo $u['id']; ?>">
                                                        <?php echo htmlspecialchars($u['full_name'] ?? $u['username']); ?>
                                                        (<?php echo htmlspecialchars($u['username']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="can_view" name="can_view" checked>
                                                <label class="form-check-label" for="can_view">
                                                    Può visualizzare
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="can_edit" name="can_edit">
                                                <label class="form-check-label" for="can_edit">
                                                    Può modificare
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-sm btn-primary w-100">
                                            <i class="bi bi-plus-circle"></i> Aggiungi Permesso
                                        </button>
                                    </form>
                                    
                                    <!-- Current Permissions List -->
                                    <?php if (!empty($passwordPermissions)): ?>
                                        <h6 class="border-top pt-3">Permessi Attuali</h6>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($passwordPermissions as $perm): ?>
                                                <div class="list-group-item px-0">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($perm['full_name'] ?? $perm['username']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php if ($perm['can_edit']): ?>
                                                                    <i class="bi bi-pencil"></i> Modifica
                                                                <?php else: ?>
                                                                    <i class="bi bi-eye"></i> Solo visualizzazione
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                                            <input type="hidden" name="action" value="revoke_permission">
                                                            <input type="hidden" name="user_id" value="<?php echo $perm['user_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="return confirm('Revocare il permesso a questo utente?')">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted small border-top pt-3">
                                            Nessun permesso condiviso. Solo tu puoi accedere a questa password.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility in view mode
        function togglePassword() {
            const passwordInput = document.getElementById('password-display');
            const toggleIcon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }
        
        // Toggle password visibility in edit mode
        function togglePasswordEdit() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon-edit');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }
        
        // Copy password to clipboard
        function copyPassword() {
            const passwordInput = document.getElementById('password-display');
            passwordInput.select();
            document.execCommand('copy');
            alert('Password copiata negli appunti!');
        }
        
        // Generate random password
        function generatePassword() {
            const length = 16;
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
            let password = '';
            
            // Ensure at least one of each: lowercase, uppercase, number, special
            password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)];
            password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
            password += '0123456789'[Math.floor(Math.random() * 10)];
            password += '!@#$%^&*()_+-=[]{}|;:,.<>?'[Math.floor(Math.random() * 23)];
            
            // Fill the rest randomly
            for (let i = password.length; i < length; i++) {
                password += charset[Math.floor(Math.random() * charset.length)];
            }
            
            // Shuffle the password
            password = password.split('').sort(() => Math.random() - 0.5).join('');
            
            document.getElementById('password').value = password;
            document.getElementById('password').type = 'text';
            document.getElementById('toggle-icon-edit').classList.remove('bi-eye');
            document.getElementById('toggle-icon-edit').classList.add('bi-eye-slash');
        }
    </script>
</body>
</html>
