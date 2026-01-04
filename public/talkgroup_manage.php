<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Controllers\DispatchController;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('operations_center', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$controller = new DispatchController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create' && $app->checkPermission('operations_center', 'create')) {
                $controller->createTalkGroup([
                    'talkgroup_id' => trim($_POST['talkgroup_id']),
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description'] ?? '')
                ]);
                $success = true;
            } elseif ($action === 'update' && $app->checkPermission('operations_center', 'edit')) {
                $controller->updateTalkGroup($_POST['id'], [
                    'talkgroup_id' => trim($_POST['talkgroup_id']),
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description'] ?? '')
                ]);
                $success = true;
            } elseif ($action === 'delete' && $app->checkPermission('operations_center', 'delete')) {
                $controller->deleteTalkGroup($_POST['id']);
                $success = true;
            }
        } catch (Exception $e) {
            $errors[] = 'Errore: ' . $e->getMessage();
        }
    }
}

$talkgroups = $controller->getTalkGroups();
$user = $app->getCurrentUser();
$isCoUser = isset($user['is_operations_center_user']) && $user['is_operations_center_user'];

$pageTitle = 'Gestione TalkGroup';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo $isCoUser ? 'EasyCO' : 'EasyVol'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <?php if ($isCoUser): ?>
        <link rel="stylesheet" href="../assets/css/easyco.css">
    <?php endif; ?>
</head>
<body>
    <?php 
    if ($isCoUser) {
        include '../src/Views/includes/navbar_operations.php';
    } else {
        include '../src/Views/includes/navbar.php';
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            if ($isCoUser) {
                include '../src/Views/includes/sidebar_operations.php';
            } else {
                include '../src/Views/includes/sidebar.php';
            }
            ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-collection"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($app->checkPermission('operations_center', 'create')): ?>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTalkGroupModal">
                                <i class="bi bi-plus-circle"></i> Nuovo TalkGroup
                            </button>
                        <?php endif; ?>
                        <a href="dispatch.php" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Torna al Dispatch
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Operazione completata con successo!
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID TalkGroup</th>
                                    <th>Nome</th>
                                    <th>Descrizione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($talkgroups)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Nessun TalkGroup configurato</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($talkgroups as $tg): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($tg['talkgroup_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($tg['name']); ?></td>
                                            <td><?php echo htmlspecialchars($tg['description'] ?? ''); ?></td>
                                            <td>
                                                <?php if ($app->checkPermission('operations_center', 'edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="editTalkGroup(<?php echo htmlspecialchars(json_encode($tg), ENT_QUOTES); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($app->checkPermission('operations_center', 'delete')): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="deleteTalkGroup(<?php echo $tg['id']; ?>, '<?php echo htmlspecialchars($tg['name']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add TalkGroup Modal -->
    <div class="modal fade" id="addTalkGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Nuovo TalkGroup</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">ID TalkGroup <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="talkgroup_id" required 
                                   placeholder="Es: 9, 99, 9990">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="Es: Nazionale, Locale, Emergenze">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrizione</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Descrizione del TalkGroup..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Crea TalkGroup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit TalkGroup Modal -->
    <div class="modal fade" id="editTalkGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Modifica TalkGroup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">ID TalkGroup <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="talkgroup_id" id="edit_talkgroup_id" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrizione</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save"></i> Salva Modifiche
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form (hidden) -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = new bootstrap.Modal(document.getElementById('editTalkGroupModal'));
        
        function editTalkGroup(tg) {
            document.getElementById('edit_id').value = tg.id;
            document.getElementById('edit_talkgroup_id').value = tg.talkgroup_id;
            document.getElementById('edit_name').value = tg.name;
            document.getElementById('edit_description').value = tg.description || '';
            editModal.show();
        }
        
        function deleteTalkGroup(id, name) {
            if (confirm('Sei sicuro di voler eliminare il TalkGroup "' + name + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
