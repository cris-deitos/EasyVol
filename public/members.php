<?php
/**
 * Gestione Soci - Lista
 * 
 * Pagina per visualizzare e gestire l'elenco dei soci maggiorenni
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\MemberController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('members', 'view')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MemberController($db, $config);

// Gestione filtri
$filters = [
    'status' => $_GET['status'] ?? '',
    'member_type' => $_GET['member_type'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Ottieni membri
$members = $controller->index($filters, $page, $perPage);

// Conteggi per status
$statusCounts = [
    'attivo' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status = 'attivo'")['count'] ?? 0,
    'sospeso' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status = 'sospeso'")['count'] ?? 0,
    'dimesso' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status = 'dimesso'")['count'] ?? 0,
];

$pageTitle = 'Gestione Soci';
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
                        <?php if ($app->checkPermission('members', 'create')): ?>
                            <a href="member_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Socio
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Soci Attivi</h5>
                                <h2><?php echo number_format($statusCounts['attivo']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Soci Sospesi</h5>
                                <h2><?php echo number_format($statusCounts['sospeso']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Soci Dimessi</h5>
                                <h2><?php echo number_format($statusCounts['dimesso']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome, cognome, matricola...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="attivo" <?php echo $filters['status'] === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
                                    <option value="sospeso" <?php echo $filters['status'] === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                                    <option value="dimesso" <?php echo $filters['status'] === 'dimesso' ? 'selected' : ''; ?>>Dimesso</option>
                                    <option value="decaduto" <?php echo $filters['status'] === 'decaduto' ? 'selected' : ''; ?>>Decaduto</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="member_type" class="form-label">Tipo Socio</label>
                                <select class="form-select" id="member_type" name="member_type">
                                    <option value="">Tutti</option>
                                    <option value="ordinario" <?php echo ($filters['member_type'] ?? '') === 'ordinario' ? 'selected' : ''; ?>>Ordinario</option>
                                    <option value="fondatore" <?php echo ($filters['member_type'] ?? '') === 'fondatore' ? 'selected' : ''; ?>>Fondatore</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Cerca
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Soci -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Soci</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Matricola</th>
                                        <th>Cognome</th>
                                        <th>Nome</th>
                                        <th>Data Nascita</th>
                                        <th>Tipo Socio</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($members)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nessun socio trovato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($member['photo_path']) && file_exists($member['photo_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($member['photo_path']); ?>" 
                                                             alt="Foto" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['registration_number']); ?></td>
                                                <td><?php echo htmlspecialchars($member['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($member['first_name']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($member['birth_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $member['member_type'] === 'fondatore' ? 'primary' : 'info'; ?>">
                                                        <?php echo ucfirst($member['member_type'] ?? 'ordinario'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'attivo' => 'success',
                                                        'sospeso' => 'warning',
                                                        'dimesso' => 'secondary',
                                                        'decaduto' => 'dark'
                                                    ];
                                                    $color = $statusColors[$member['member_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($member['member_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="member_view.php?id=<?php echo $member['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('members', 'edit')): ?>
                                                            <a href="member_edit.php?id=<?php echo $member['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($app->checkPermission('members', 'delete')): ?>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="confirmDelete(<?php echo $member['id']; ?>)" 
                                                                    title="Elimina">
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
        function confirmDelete(memberId) {
            if (confirm('Sei sicuro di voler eliminare questo socio?')) {
                // TODO: Implement delete functionality
                window.location.href = 'member_delete.php?id=' + memberId;
            }
        }
    </script>
</body>
</html>
