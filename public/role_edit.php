<?php
/**
 * Gestione Ruoli - Crea/Modifica
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

if (!$app->checkPermission('users', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new UserController($db, $config);
$csrf = new CsrfProtection();

$roleId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $roleId > 0;

$role = null;
$errors = [];
$success = false;

if ($isEdit) {
    $role = $controller->getRole($roleId);
    if (!$role) {
        header('Location: roles.php?error=not_found');
        exit;
    }
}

// Get all permissions
$allPermissions = $controller->getAllPermissions();

// Get role permissions if editing
$rolePermissions = [];
if ($isEdit) {
    $perms = $controller->getRolePermissions($roleId);
    $rolePermissions = array_column($perms, 'id');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? '')
        ];
        
        // Validation
        if (empty($data['name'])) {
            $errors[] = 'Il nome del ruolo è obbligatorio';
        }
        
        if (empty($errors)) {
            $currentUserId = $app->getUserId();
            
            if ($isEdit) {
                $result = $controller->updateRole($roleId, $data, $currentUserId);
            } else {
                $result = $controller->createRole($data, $currentUserId);
                if (is_numeric($result)) {
                    $roleId = $result;
                }
            }
            
            if ($result === true || is_numeric($result)) {
                $finalRoleId = $isEdit ? $roleId : $result;
                
                // Update role permissions within a transaction
                try {
                    $db->beginTransaction();
                    
                    // First, remove all existing permissions
                    $db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$finalRoleId]);
                    
                    // Add selected permissions
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        foreach ($_POST['permissions'] as $permissionId) {
                            $permissionId = intval($permissionId);
                            $db->insert('role_permissions', [
                                'role_id' => $finalRoleId,
                                'permission_id' => $permissionId
                            ]);
                        }
                    }
                    
                    $db->commit();
                    
                    header('Location: roles.php?success=1');
                    exit;
                } catch (Exception $e) {
                    $db->rollback();
                    $errors[] = 'Errore durante l\'aggiornamento dei permessi';
                    error_log("Error updating role permissions: " . $e->getMessage());
                }
            } else {
                $errors[] = 'Errore durante il salvataggio del ruolo';
            }
        }
    }
}

// Group permissions by module
$permissionsByModule = [];
foreach ($allPermissions as $perm) {
    $permissionsByModule[$perm['module']][] = $perm;
}

$pageTitle = $isEdit ? 'Modifica Ruolo' : 'Nuovo Ruolo';
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
                        <a href="roles.php" class="text-decoration-none text-muted">
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
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome Ruolo *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($role['name'] ?? $_POST['name'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3"><?php echo htmlspecialchars($role['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="border rounded p-3 mb-3 bg-light">
                                <h6 class="mb-3">
                                    <i class="bi bi-shield-check"></i> Permessi del Ruolo
                                </h6>
                                <p class="text-muted small mb-3">
                                    Seleziona i permessi che gli utenti con questo ruolo avranno.
                                </p>
                                
                                <?php
                                // Traduzioni moduli
                                $moduleLabels = [
                                    'members' => 'Soci',
                                    'junior_members' => 'Soci Minorenni',
                                    'users' => 'Utenti',
                                    'meetings' => 'Riunioni',
                                    'vehicles' => 'Mezzi',
                                    'warehouse' => 'Magazzino',
                                    'training' => 'Corsi',
                                    'events' => 'Eventi',
                                    'documents' => 'Documenti',
                                    'scheduler' => 'Scadenze',
                                    'operations_center' => 'Centrale Operativa',
                                    'applications' => 'Domande Iscrizione',
                                    'reports' => 'Report',
                                    'settings' => 'Impostazioni'
                                ];
                                
                                $actionLabels = [
                                    'view' => 'Visualizza',
                                    'create' => 'Crea',
                                    'edit' => 'Modifica',
                                    'delete' => 'Elimina',
                                    'report' => 'Report'
                                ];
                                ?>
                                
                                <div class="row">
                                    <?php foreach ($permissionsByModule as $module => $permissions): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-header bg-primary text-white py-2">
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input module-checkbox" type="checkbox" 
                                                               id="module_<?php echo htmlspecialchars($module); ?>"
                                                               data-module="<?php echo htmlspecialchars($module); ?>">
                                                        <label class="form-check-label" for="module_<?php echo htmlspecialchars($module); ?>">
                                                            <strong><?php echo htmlspecialchars($moduleLabels[$module] ?? $module); ?></strong>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="card-body p-2">
                                                    <?php foreach ($permissions as $perm): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input permission-checkbox" type="checkbox" 
                                                                   name="permissions[]" 
                                                                   value="<?php echo $perm['id']; ?>"
                                                                   id="perm_<?php echo $perm['id']; ?>"
                                                                   data-module="<?php echo htmlspecialchars($module); ?>"
                                                                   <?php echo in_array($perm['id'], $rolePermissions) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="perm_<?php echo $perm['id']; ?>">
                                                                <?php echo htmlspecialchars($actionLabels[$perm['action']] ?? $perm['action']); ?>
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
                                    <i class="bi bi-save"></i> Salva Ruolo
                                </button>
                                <a href="roles.php" class="btn btn-secondary">
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
        // Module checkbox functionality
        document.querySelectorAll('.module-checkbox').forEach(moduleCheckbox => {
            const module = moduleCheckbox.dataset.module;
            const permissionCheckboxes = document.querySelectorAll(`.permission-checkbox[data-module="${module}"]`);
            
            // Update module checkbox state based on permission checkboxes
            function updateModuleCheckbox() {
                const allChecked = Array.from(permissionCheckboxes).every(cb => cb.checked);
                const someChecked = Array.from(permissionCheckboxes).some(cb => cb.checked);
                
                moduleCheckbox.checked = allChecked;
                moduleCheckbox.indeterminate = someChecked && !allChecked;
            }
            
            // Check/uncheck all permissions when module checkbox is clicked
            moduleCheckbox.addEventListener('change', function() {
                permissionCheckboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
            });
            
            // Update module checkbox when individual permissions change
            permissionCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateModuleCheckbox);
            });
            
            // Initialize module checkbox state
            updateModuleCheckbox();
        });
    </script>
</body>
</html>
