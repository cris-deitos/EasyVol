<?php
/**
 * Gestione Utenti - Lista
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

$filters = [
    'role_id' => $_GET['role_id'] ?? '',
    'is_active' => isset($_GET['is_active']) ? intval($_GET['is_active']) : '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$users = $controller->index($filters, $page, $perPage);
$stats = $controller->getStats();
$roles = $controller->getRoles();

$pageTitle = 'Gestione Utenti';
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
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->hasPermission('users', 'create')): ?>
                            <a href="user_edit.php" class="btn btn-primary me-2">
                                <i class="bi bi-plus-circle"></i> Nuovo Utente
                            </a>
                            <a href="roles.php" class="btn btn-outline-secondary">
                                <i class="bi bi-shield-lock"></i> Gestione Ruoli
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Totale Utenti</h5>
                                <h2><?php echo number_format($stats['total'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Attivi</h5>
                                <h2><?php echo number_format($stats['active'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Disattivati</h5>
                                <h2><?php echo number_format($stats['inactive'] ?? 0); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Username, nome, email...">
                            </div>
                            <div class="col-md-3">
                                <label for="role_id" class="form-label">Ruolo</label>
                                <select class="form-select" id="role_id" name="role_id">
                                    <option value="">Tutti</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                                <?php echo $filters['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="is_active" class="form-label">Stato</label>
                                <select class="form-select" id="is_active" name="is_active">
                                    <option value="">Tutti</option>
                                    <option value="1" <?php echo $filters['is_active'] === 1 ? 'selected' : ''; ?>>Attivi</option>
                                    <option value="0" <?php echo $filters['is_active'] === 0 ? 'selected' : ''; ?>>Disattivati</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Cerca
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Utenti -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Utenti</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Nome Completo</th>
                                        <th>Email</th>
                                        <th>Ruolo</th>
                                        <th>Ultimo Accesso</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Nessun utente trovato</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-person-circle text-primary"></i>
                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php if ($user['role_name']): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($user['role_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Mai'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['is_active']): ?>
                                                        <span class="badge bg-success">Attivo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Disattivato</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php if ($app->hasPermission('users', 'edit')): ?>
                                                            <a href="user_edit.php?id=<?php echo $user['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($app->hasPermission('users', 'delete')): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteUser(<?php echo $user['id']; ?>)" title="Elimina">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteUser(id) {
            if (confirm('Sei sicuro di voler eliminare questo utente?')) {
                window.location.href = 'user_delete.php?id=' + id;
            }
        }
    </script>
</body>
</html>
