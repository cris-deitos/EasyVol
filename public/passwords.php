<?php
/**
 * Gestione Password - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\PasswordController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('password_management', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PasswordController($db, $config);
$user = $app->getCurrentUser();

$filters = [
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$passwords = $controller->getPasswordsForUser($user['id'], $filters, $page, $perPage);
$totalPasswords = $controller->countPasswordsForUser($user['id'], $filters);
$totalPages = ceil($totalPasswords / $perPage);

$pageTitle = 'Gestione Password';
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
            letter-spacing: 2px;
        }
        .password-dots {
            letter-spacing: 4px;
        }
        .password-reveal-btn {
            cursor: pointer;
            color: #6c757d;
            transition: color 0.3s;
        }
        .password-reveal-btn:hover {
            color: #0d6efd;
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
                        <?php if ($app->checkPermission('password_management', 'create')): ?>
                            <a href="password_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Password
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Nota sulla Sicurezza:</strong> 
                            Le password sono memorizzate in modo crittografato. Visualizza le password solo quando necessario 
                            e assicurati di essere in un ambiente sicuro.
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Password Accessibili</h5>
                                <h2><?php echo number_format($totalPasswords); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-10">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Titolo, link, nome utente...">
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
                
                <!-- Tabella Password -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Password</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($passwords)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Nessuna password trovata.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Titolo</th>
                                            <th>Link</th>
                                            <th>Nome Utente</th>
                                            <th>Password</th>
                                            <th>Creato da</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($passwords as $pwd): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($pwd['title']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if (!empty($pwd['link'])): ?>
                                                        <a href="<?php echo htmlspecialchars($pwd['link']); ?>" 
                                                           target="_blank" 
                                                           rel="noopener noreferrer"
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-box-arrow-up-right"></i> LINK DI ACCESSO
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo !empty($pwd['username']) ? htmlspecialchars($pwd['username']) : '<span class="text-muted">-</span>'; ?></td>
                                                <td>
                                                    <span class="password-field password-dots" id="password-<?php echo $pwd['id']; ?>">
                                                        ••••••••••••
                                                    </span>
                                                    <i class="bi bi-eye password-reveal-btn ms-2" 
                                                       data-password-id="<?php echo $pwd['id']; ?>"
                                                       onclick="togglePasswordVisibility(<?php echo $pwd['id']; ?>)"
                                                       title="Mostra/Nascondi password"></i>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($pwd['creator_name'] ?? $pwd['creator_username']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="password_edit.php?id=<?php echo $pwd['id']; ?>" 
                                                           class="btn btn-outline-primary" 
                                                           title="Visualizza dettagli">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($pwd['can_edit_permission']): ?>
                                                            <a href="password_edit.php?id=<?php echo $pwd['id']; ?>&edit=1" 
                                                               class="btn btn-outline-warning" 
                                                               title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($pwd['created_by'] == $user['id']): ?>
                                                            <button type="button" 
                                                                    class="btn btn-outline-danger" 
                                                                    onclick="deletePassword(<?php echo $pwd['id']; ?>)"
                                                                    title="Elimina">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginazione -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Navigazione pagine">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : ''; ?>">
                                                    Precedente
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : ''; ?>">
                                                    Successivo
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store revealed passwords to avoid multiple requests
        const revealedPasswords = {};
        
        function togglePasswordVisibility(passwordId) {
            const passwordField = document.getElementById('password-' + passwordId);
            const icon = document.querySelector(`[data-password-id="${passwordId}"]`);
            
            if (revealedPasswords[passwordId]) {
                // Hide password
                passwordField.textContent = '••••••••••••';
                passwordField.classList.add('password-dots');
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
                delete revealedPasswords[passwordId];
            } else {
                // Show password - fetch from server
                fetch('password_api.php?action=reveal&id=' + passwordId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            passwordField.textContent = data.password;
                            passwordField.classList.remove('password-dots');
                            icon.classList.remove('bi-eye');
                            icon.classList.add('bi-eye-slash');
                            revealedPasswords[passwordId] = data.password;
                        } else {
                            alert('Errore: ' + (data.message || 'Impossibile recuperare la password'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Errore di rete durante il recupero della password');
                    });
            }
        }
        
        function deletePassword(passwordId) {
            if (!confirm('Sei sicuro di voler eliminare questa password? Questa azione non può essere annullata.')) {
                return;
            }
            
            fetch('password_api.php?action=delete&id=' + passwordId, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Password eliminata con successo');
                    window.location.reload();
                } else {
                    alert('Errore: ' + (data.message || 'Impossibile eliminare la password'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di rete durante l\'eliminazione della password');
            });
        }
    </script>
</body>
</html>
