<?php
/**
 * Gestione Utenti - Crea/Modifica
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\UserController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new UserController($db, $config);
$csrf = new CsrfProtection();

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $userId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('users', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('users', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$editUser = null;
$errors = [];
$success = false;

if ($isEdit) {
    $editUser = $controller->get($userId);
    if (!$editUser) {
        header('Location: users.php?error=not_found');
        exit;
    }
}

$roles = $controller->getRoles();

// Ottieni tutti i permessi disponibili
$allPermissions = $db->fetchAll("SELECT * FROM permissions ORDER BY module, action");

// Ottieni i permessi specifici dell'utente (se in modifica)
$userPermissions = [];
if ($isEdit) {
    // Pass parameter directly to execute() to ensure proper binding
    $stmt = $db->getConnection()->prepare("SELECT permission_id FROM user_permissions WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $userPermissions = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'permission_id');
}

// Ottieni membri per collegamento
$members = $db->fetchAll("SELECT id, first_name, last_name, registration_number FROM members ORDER BY last_name, first_name");

// Gestione form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'full_name' => trim($_POST['full_name'] ?? ''),
            'member_id' => !empty($_POST['member_id']) ? intval($_POST['member_id']) : null,
            'role_id' => !empty($_POST['role_id']) ? intval($_POST['role_id']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_operations_center_user' => isset($_POST['is_operations_center_user']) ? 1 : 0
        ];
        
        // Validazione
        if (empty($data['username'])) {
            $errors[] = 'L\'username è obbligatorio';
        } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,}$/', $data['username'])) {
            $errors[] = 'L\'username deve contenere almeno 3 caratteri e solo lettere, numeri, underscore e punto';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'L\'email è obbligatoria';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida';
        }
        
        // Password solo per nuovo utente o se specificata
        if (!$isEdit) {
            // For new users, use default password
            $data['password'] = App::DEFAULT_PASSWORD;
        } elseif (!empty($_POST['password'])) {
            // For existing users, only change if provided
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            
            if (empty($password)) {
                $errors[] = 'La password è obbligatoria';
            } elseif (strlen($password) < 8) {
                $errors[] = 'La password deve essere di almeno 8 caratteri';
            } elseif ($password !== $passwordConfirm) {
                $errors[] = 'Le password non coincidono';
            } else {
                $data['password'] = $password;
            }
        }
        
        if (empty($errors)) {
            $currentUserId = $app->getUserId();
            
            if ($isEdit) {
                $result = $controller->update($userId, $data, $currentUserId);
                
                // Cambio password separato
                if ($result === true && !empty($data['password'])) {
                    $controller->changePassword($userId, $data['password'], $currentUserId);
                }
            } else {
                $result = $controller->create($data, $currentUserId);
                if (is_numeric($result)) {
                    $userId = $result;
                }
            }
            
            if ($result === true || is_numeric($result)) {
                // Salva i permessi specifici dell'utente
                $finalUserId = $isEdit ? $userId : $result;
                
                // Rimuovi tutti i permessi esistenti
                $stmt = $db->getConnection()->prepare("DELETE FROM user_permissions WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $finalUserId]);
                
                // Aggiungi i nuovi permessi selezionati
                if (isset($_POST['user_permissions']) && is_array($_POST['user_permissions'])) {
                    // Get all valid permission IDs to validate against (using array_flip for O(1) lookup)
                    $validPermissions = $db->fetchAll("SELECT id FROM permissions");
                    $validPermissionIds = array_flip(array_column($validPermissions, 'id'));
                    
                    foreach ($_POST['user_permissions'] as $permissionId) {
                        $permissionId = intval($permissionId);
                        // Only insert if it's a valid permission ID (O(1) lookup with isset)
                        if (isset($validPermissionIds[$permissionId])) {
                            $db->insert('user_permissions', [
                                'user_id' => $finalUserId,
                                'permission_id' => $permissionId
                            ]);
                        }
                    }
                }
                
                $success = true;
                header('Location: users.php?success=1');
                exit;
            } elseif (is_array($result) && isset($result['error'])) {
                $errors[] = $result['error'];
            } else {
                $errors[] = 'Errore durante il salvataggio dell\'utente';
            }
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Utente' : 'Nuovo Utente';
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
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($editUser['username'] ?? $_POST['username'] ?? ''); ?>" 
                                           required pattern="[a-zA-Z0-9_.]{3,}">
                                    <div class="form-text">Almeno 3 caratteri, solo lettere, numeri, underscore e punto</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($editUser['email'] ?? $_POST['email'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($editUser['full_name'] ?? $_POST['full_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="role_id" class="form-label">Ruolo</label>
                                    <select class="form-select" id="role_id" name="role_id">
                                        <option value="">Nessun ruolo</option>
                                        <?php
                                        $selectedRole = $editUser['role_id'] ?? $_POST['role_id'] ?? '';
                                        foreach ($roles as $role):
                                        ?>
                                            <option value="<?php echo $role['id']; ?>" 
                                                    <?php echo $selectedRole == $role['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <?php $selectedMember = $editUser['member_id'] ?? $_POST['member_id'] ?? ''; ?>
                                    <label for="member_search" class="form-label">Collega a Socio</label>
                                    <input type="text" class="form-control" id="member_search" 
                                           placeholder="Cerca per nome, cognome, matricola o codice fiscale..." 
                                           value="<?php 
                                           if ($selectedMember) {
                                               $member = array_filter($members, function($m) use ($selectedMember) { return $m['id'] == $selectedMember; });
                                               if (!empty($member)) {
                                                   $member = reset($member);
                                                   echo htmlspecialchars($member['registration_number'] . ' - ' . $member['last_name'] . ' ' . $member['first_name']);
                                               }
                                           }
                                           ?>" autocomplete="off">
                                    <input type="hidden" id="member_id" name="member_id" value="<?php echo htmlspecialchars($selectedMember); ?>">
                                    <div id="member_search_results" class="list-group position-absolute" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"></div>
                                    <small class="form-text text-muted">Lascia vuoto per nessun collegamento</small>
                                </div>
                            </div>
                            
                            <div class="border rounded p-3 mb-3 bg-light">
                                <h6>Password<?php echo $isEdit ? ' (lascia vuoto per non modificare)' : ''; ?></h6>
                                <?php if (!$isEdit): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-info-circle"></i> 
                                        Per i nuovi utenti verrà impostata automaticamente la password predefinita: <strong><?php echo htmlspecialchars(App::DEFAULT_PASSWORD); ?></strong><br>
                                        L'utente riceverà un'email con le credenziali e sarà obbligato a cambiarla al primo accesso.
                                    </div>
                                <?php endif; ?>
                                <?php if ($isEdit): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="password" class="form-label">Nuova Password</label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               minlength="8">
                                        <div class="form-text">Almeno 8 caratteri (lascia vuoto per non modificare)</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="password_confirm" class="form-label">Conferma Password</label>
                                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                               minlength="8">
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo (isset($editUser) ? $editUser['is_active'] : 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Utente Attivo
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="is_operations_center_user" name="is_operations_center_user" 
                                       <?php echo (isset($editUser) && isset($editUser['is_operations_center_user']) && $editUser['is_operations_center_user']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_operations_center_user">
                                    <i class="bi bi-broadcast"></i> Utente Centrale Operativa (EasyCO)
                                </label>
                                <div class="form-text">
                                    L'utente potrà accedere al sistema EasyCO tramite un login dedicato con funzionalità limitate
                                </div>
                            </div>
                            
                            <div class="border rounded p-3 mb-3 bg-light">
                                <h6 class="mb-3">
                                    <i class="bi bi-shield-check"></i> Permessi Specifici Utente
                                    <small class="text-muted">(Oltre ai permessi del ruolo)</small>
                                </h6>
                                <p class="text-muted small mb-3">
                                    Seleziona i permessi aggiuntivi specifici per questo utente. 
                                    Questi si aggiungono ai permessi già concessi dal ruolo assegnato.
                                </p>
                                
                                <?php
                                // Raggruppa i permessi per modulo
                                $permissionsByModule = [];
                                foreach ($allPermissions as $perm) {
                                    $permissionsByModule[$perm['module']][] = $perm;
                                }
                                
                                // Traduzioni moduli
                                $moduleLabels = [
                                    'members' => 'Soci',
                                    'junior_members' => 'Cadetti',
                                    'users' => 'Utenti',
                                    'meetings' => 'Riunioni',
                                    'vehicles' => 'Mezzi',
                                    'warehouse' => 'Magazzino',
                                    'training' => 'Corsi',
                                    'events' => 'Eventi',
                                    'documents' => 'Documenti',
                                    'scheduler' => 'Scadenziario',
                                    'operations_center' => 'Centrale Operativa',
                                    'gate_management' => 'Gestione Varchi',
                                    'structure_management' => 'Gestione Strutture',
                                    'applications' => 'Domande Iscrizione',
                                    'reports' => 'Report',
                                    'settings' => 'Impostazioni',
                                    'activity_logs' => 'Log Attività',
                                    'gdpr_compliance' => 'Conformità GDPR',
                                    'dashboard' => 'Dashboard'
                                ];
                                
                                $actionLabels = [
                                    'view' => 'Visualizza',
                                    'create' => 'Crea',
                                    'edit' => 'Modifica',
                                    'delete' => 'Elimina',
                                    'report' => 'Report',
                                    'manage_consents' => 'Gestione Consensi',
                                    'export_personal_data' => 'Export Dati Personali',
                                    'view_access_logs' => 'Visualizza Log Accessi',
                                    'manage_processing_registry' => 'Gestione Registro Trattamenti',
                                    'manage_appointments' => 'Gestione Nomine',
                                    'print_appointment' => 'Stampa Nomina',
                                    'view_advanced' => 'Visualizza Avanzato'
                                ];
                                ?>
                                
                                <div class="row">
                                    <?php foreach ($permissionsByModule as $module => $permissions): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-header bg-secondary text-white py-1">
                                                    <small><strong><?php echo htmlspecialchars($moduleLabels[$module] ?? $module); ?></strong></small>
                                                </div>
                                                <div class="card-body p-2">
                                                    <?php foreach ($permissions as $perm): ?>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   name="user_permissions[]" 
                                                                   value="<?php echo $perm['id']; ?>"
                                                                   id="perm_<?php echo $perm['id']; ?>"
                                                                   <?php echo in_array($perm['id'], $userPermissions) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="perm_<?php echo $perm['id']; ?>">
                                                                <small><?php echo htmlspecialchars($actionLabels[$perm['action']] ?? $perm['action']); ?></small>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva Utente
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
    <script>
        // Member search autocomplete
        let memberSearchTimeout = null;
        const memberSearchInput = document.getElementById('member_search');
        const memberIdInput = document.getElementById('member_id');
        const memberSearchResults = document.getElementById('member_search_results');
        
        if (memberSearchInput) {
            memberSearchInput.addEventListener('input', function() {
                clearTimeout(memberSearchTimeout);
                const search = this.value.trim();
                
                if (search.length < 2) {
                    memberSearchResults.style.display = 'none';
                    memberSearchResults.innerHTML = '';
                    if (search.length === 0) {
                        memberIdInput.value = '';
                    }
                    return;
                }
                
                memberSearchTimeout = setTimeout(function() {
                    fetch('members_search_ajax.php?q=' + encodeURIComponent(search))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                memberSearchResults.innerHTML = '<div class="list-group-item text-muted">Nessun socio trovato</div>';
                                memberSearchResults.style.display = 'block';
                                return;
                            }
                            
                            memberSearchResults.innerHTML = data.map(function(member) {
                                return '<button type="button" class="list-group-item list-group-item-action" data-member-id="' + member.id + '" data-member-label="' + escapeHtml(member.label) + '">' +
                                    escapeHtml(member.label) +
                                    '</button>';
                            }).join('');
                            memberSearchResults.style.display = 'block';
                            
                            // Add click handlers
                            memberSearchResults.querySelectorAll('button').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    memberIdInput.value = this.dataset.memberId;
                                    memberSearchInput.value = this.dataset.memberLabel;
                                    memberSearchResults.style.display = 'none';
                                });
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            memberSearchResults.innerHTML = '<div class="list-group-item text-danger">Errore nella ricerca</div>';
                            memberSearchResults.style.display = 'block';
                        });
                }, 300);
            });
            
            // Close results when clicking outside
            document.addEventListener('click', function(e) {
                if (!memberSearchInput.contains(e.target) && !memberSearchResults.contains(e.target)) {
                    memberSearchResults.style.display = 'none';
                }
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
