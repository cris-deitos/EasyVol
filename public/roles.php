<?php
/**
 * Gestione Ruoli e Permessi
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\UserController;

$app = new App();

if (!$app->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!$app->hasPermission('users', 'view')) {
    die('Accesso negato');
}

$db = $app->getDatabase();
$config = $app->getConfig();
$controller = new UserController($db, $config);

$roles = $controller->getRoles();
$allPermissions = $controller->getAllPermissions();

// Raggruppa permessi per modulo
$permissionsByModule = [];
foreach ($allPermissions as $perm) {
    $permissionsByModule[$perm['module']][] = $perm;
}

$pageTitle = 'Gestione Ruoli e Permessi';
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
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Nota:</strong> I ruoli e i permessi definiscono cosa possono fare gli utenti nel sistema. 
                    Modificare i permessi con attenzione per evitare problemi di sicurezza.
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ruoli Disponibili</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Ruolo</th>
                                        <th>Descrizione</th>
                                        <th>Numero Utenti</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($roles)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Nessun ruolo configurato</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($roles as $role): 
                                            $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role_id = ?", [$role['id']]);
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($role['description'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo $userCount['count'] ?? 0; ?> utenti
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#permissionsModal<?php echo $role['id']; ?>">
                                                        <i class="bi bi-shield-lock"></i> Visualizza Permessi
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Permessi disponibili per modulo -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Permessi per Modulo</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="permissionsAccordion">
                            <?php foreach ($permissionsByModule as $module => $perms): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#module<?php echo htmlspecialchars($module); ?>">
                                            <strong><?php echo ucfirst(htmlspecialchars($module)); ?></strong>
                                            <span class="badge bg-secondary ms-2"><?php echo count($perms); ?> permessi</span>
                                        </button>
                                    </h2>
                                    <div id="module<?php echo htmlspecialchars($module); ?>" 
                                         class="accordion-collapse collapse" 
                                         data-bs-parent="#permissionsAccordion">
                                        <div class="accordion-body">
                                            <ul class="list-group">
                                                <?php foreach ($perms as $perm): ?>
                                                    <li class="list-group-item">
                                                        <strong><?php echo htmlspecialchars($perm['action']); ?></strong>
                                                        <?php if ($perm['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($perm['description']); ?></small>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal per visualizzare permessi di ogni ruolo -->
    <?php foreach ($roles as $role): 
        $rolePermissions = $controller->getRolePermissions($role['id']);
        $rolePermsByModule = [];
        foreach ($rolePermissions as $perm) {
            $rolePermsByModule[$perm['module']][] = $perm;
        }
    ?>
        <div class="modal fade" id="permissionsModal<?php echo $role['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Permessi: <?php echo htmlspecialchars($role['name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($rolePermissions)): ?>
                            <p class="text-muted">Nessun permesso assegnato a questo ruolo.</p>
                        <?php else: ?>
                            <?php foreach ($rolePermsByModule as $module => $perms): ?>
                                <h6 class="mt-3"><?php echo ucfirst(htmlspecialchars($module)); ?></h6>
                                <ul>
                                    <?php foreach ($perms as $perm): ?>
                                        <li><?php echo htmlspecialchars($perm['action']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
