<?php
/**
 * Gestione Soci Minorenni - Lista
 * 
 * Pagina per visualizzare e gestire l'elenco dei soci minorenni
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Utils\PathHelper;
use EasyVol\Controllers\JuniorMemberController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('junior_members', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new JuniorMemberController($db, $config);

// Gestione filtri
$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Ottieni membri minorenni
$members = $controller->index($filters, $page, $perPage);

// Conteggi per status
// Note: in_aspettativa and in_congedo are counted as sospeso
$statusCounts = [
    'attivo' => $db->fetchOne("SELECT COUNT(*) as count FROM junior_members WHERE member_status = 'attivo'")['count'] ?? 0,
    'sospeso' => $db->fetchOne("SELECT COUNT(*) as count FROM junior_members WHERE member_status IN ('sospeso', 'in_aspettativa', 'in_congedo')")['count'] ?? 0,
    'dimessi_decaduti' => $db->fetchOne("SELECT COUNT(*) as count FROM junior_members WHERE member_status IN ('dimesso', 'decaduto')")['count'] ?? 0,
];

$pageTitle = 'Gestione Soci Minorenni';
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
                        <?php if ($app->checkPermission('junior_members', 'create')): ?>
                            <a href="junior_member_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Socio Minorenne
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
                                <small>Include: In Aspettativa, In Congedo</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Dimessi/Decaduti</h5>
                                <h2><?php echo number_format($statusCounts['dimessi_decaduti']); ?></h2>
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
                                       placeholder="Nome, cognome, matricola...">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="attivo" <?php echo $filters['status'] === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
                                    <option value="sospeso" <?php echo $filters['status'] === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                                    <option value="dimesso" <?php echo $filters['status'] === 'dimesso' ? 'selected' : ''; ?>>Dimesso</option>
                                </select>
                            </div>
                            <div class="col-md-4">
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
                
                <!-- Tabella Soci Minorenni -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Soci Minorenni</h5>
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
                                        <th>Età</th>
                                        <th>Tutore</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($members)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                Nessun socio minorenne trovato
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): ?>
                                            <?php
                                            // Calcola età
                                            $birthDate = new DateTime($member['birth_date']);
                                            $today = new DateTime();
                                            $age = $today->diff($birthDate)->y;
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $hasPhoto = false;
                                                    if (!empty($member['photo_path'])) {
                                                        $absolutePath = PathHelper::relativeToAbsolute($member['photo_path']);
                                                        $hasPhoto = file_exists($absolutePath);
                                                    }
                                                    ?>
                                                    <?php if ($hasPhoto): ?>
                                                        <img src="download.php?type=junior_member_photo&id=<?php echo $member['id']; ?>" 
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
                                                <td><?php echo $age; ?> anni</td>
                                                <td>
                                                    <?php 
                                                    $guardianName = trim(($member['guardian_first_name'] ?? '') . ' ' . ($member['guardian_last_name'] ?? ''));
                                                    if (!empty($guardianName)) {
                                                        echo htmlspecialchars($guardianName);
                                                    } else {
                                                        echo '<span class="text-muted">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'attivo' => 'success',
                                                        'sospeso' => 'warning',
                                                        'dimesso' => 'secondary'
                                                    ];
                                                    $color = $statusColors[$member['member_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($member['member_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="junior_member_view.php?id=<?php echo $member['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('junior_members', 'edit')): ?>
                                                            <a href="junior_member_edit.php?id=<?php echo $member['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($app->checkPermission('junior_members', 'delete')): ?>
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
            if (confirm('Sei sicuro di voler eliminare questo socio minorenne?')) {
                window.location.href = 'junior_member_delete.php?id=' + memberId;
            }
        }
    </script>
</body>
</html>
