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
use EasyVol\Utils\AutoLogger;
use EasyVol\Utils\PathHelper;

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
    'volunteer_status' => $_GET['volunteer_status'] ?? '',
    'role' => $_GET['role'] ?? '',
    'search' => $_GET['search'] ?? '',
    'hide_dismissed' => isset($_GET['hide_dismissed']) ? $_GET['hide_dismissed'] : '1',
    'sort_by' => $_GET['sort_by'] ?? 'alphabetical'
];

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// Ottieni membri
$members = $controller->index($filters, $page, $perPage);

// Get available roles for filter dropdown
$availableRoles = $db->fetchAll("SELECT DISTINCT role_name FROM member_roles ORDER BY role_name");

// Log page access
AutoLogger::logPageAccess();
// Log search if performed
if (!empty($filters['search'])) {
    AutoLogger::logSearch('members', $filters['search'], $filters);
}

// Conteggi per status
// Note: in_aspettativa and in_congedo are counted as sospeso
$statusCounts = [
    'attivo' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status = 'attivo'")['count'] ?? 0,
    'sospeso' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status IN ('sospeso', 'in_aspettativa', 'in_congedo')")['count'] ?? 0,
    'dimessi_decaduti' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE member_status IN ('dimesso', 'decaduto', 'escluso')")['count'] ?? 0,
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
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-printer"></i> Stampa
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="printList('libro_soci'); return false;">
                                    <i class="bi bi-book"></i> Libro Soci
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="printList('elenco_telefonico'); return false;">
                                    <i class="bi bi-telephone"></i> Elenco Telefonico
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="printList('tessere_multiple'); return false;">
                                    <i class="bi bi-credit-card-2-back"></i> Tessere Multiple
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="showPrintListModal(); return false;">
                                    <i class="bi bi-gear"></i> Scegli Template...
                                </a></li>
                            </ul>
                        </div>
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
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome, cognome, matricola...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="attivo" <?php echo $filters['status'] === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
                                    <option value="sospeso" <?php echo $filters['status'] === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                                    <option value="dimesso" <?php echo $filters['status'] === 'dimesso' ? 'selected' : ''; ?>>Dimesso</option>
                                    <option value="decaduto" <?php echo $filters['status'] === 'decaduto' ? 'selected' : ''; ?>>Decaduto</option>
                                    <option value="escluso" <?php echo $filters['status'] === 'escluso' ? 'selected' : ''; ?>>Escluso</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="volunteer_status" class="form-label">Stato Volontario</label>
                                <select class="form-select" id="volunteer_status" name="volunteer_status">
                                    <option value="">Tutti</option>
                                    <option value="operativo" <?php echo $filters['volunteer_status'] === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                                    <option value="in_formazione" <?php echo $filters['volunteer_status'] === 'in_formazione' ? 'selected' : ''; ?>>In Formazione</option>
                                    <option value="non_operativo" <?php echo $filters['volunteer_status'] === 'non_operativo' ? 'selected' : ''; ?>>Non Operativo</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="role" class="form-label">Qualifica/Mansione</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">Tutte</option>
                                    <?php foreach ($availableRoles as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r['role_name']); ?>" 
                                                <?php echo $filters['role'] === $r['role_name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="sort_by" class="form-label">Ordina per</label>
                                <select class="form-select" id="sort_by" name="sort_by">
                                    <option value="alphabetical" <?php echo $filters['sort_by'] === 'alphabetical' ? 'selected' : ''; ?>>Alfabetico</option>
                                    <option value="registration_number" <?php echo $filters['sort_by'] === 'registration_number' ? 'selected' : ''; ?>>Matricola</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="hide_dismissed" 
                                           <?php echo $filters['hide_dismissed'] === '1' ? 'checked' : ''; ?> 
                                           onchange="toggleDismissed()">
                                    <label class="form-check-label" for="hide_dismissed">
                                        Nascondi dimessi/decaduti/esclusi
                                    </label>
                                </div>
                            </div>
                        </div>
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
                                        <th>Stato</th>
                                        <th>Qualifica</th>
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
                                                    <?php 
                                                    $hasPhoto = false;
                                                    if (!empty($member['photo_path'])) {
                                                        $absolutePath = PathHelper::relativeToAbsolute($member['photo_path']);
                                                        $hasPhoto = file_exists($absolutePath);
                                                    }
                                                    ?>
                                                    <?php if ($hasPhoto): ?>
                                                        <img src="download.php?type=member_photo&id=<?php echo $member['id']; ?>" 
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
                                                    <?php
                                                    $statusColors = [
                                                        'attivo' => 'success',
                                                        'sospeso' => 'warning',
                                                        'dimesso' => 'secondary',
                                                        'deceduto' => 'dark'
                                                    ];
                                                    $color = $statusColors[$member['member_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($member['member_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $volunteerStatusLabels = [
                                                        'operativo' => 'Operativo',
                                                        'in_formazione' => 'In Formazione',
                                                        'non_operativo' => 'Non Operativo'
                                                    ];
                                                    echo $volunteerStatusLabels[$member['volunteer_status']] ?? ucfirst($member['volunteer_status']);
                                                    ?>
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
        
        function toggleDismissed() {
            const urlParams = new URLSearchParams(window.location.search);
            const isChecked = document.getElementById('hide_dismissed').checked;
            
            if (isChecked) {
                urlParams.set('hide_dismissed', '1');
            } else {
                urlParams.set('hide_dismissed', '0');
            }
            
            // Reset pagination when filter changes
            urlParams.set('page', '1');
            
            window.location.search = urlParams.toString();
        }
        
        // Print list functionality
        function printList(type) {
            let templateId = null;
            let filters = getCurrentFilters();
            
            switch(type) {
                case 'libro_soci':
                    templateId = 4; // Libro Soci
                    break;
                case 'elenco_telefonico':
                    templateId = 5; // Elenco Telefonico
                    break;
                case 'tessere_multiple':
                    templateId = 6; // Tessere Multiple
                    break;
            }
            
            if (templateId) {
                const params = new URLSearchParams({
                    template_id: templateId,
                    entity: 'members',
                    ...filters
                });
                window.open('print_preview.php?' + params.toString(), '_blank');
            }
        }
        
        function getCurrentFilters() {
            const filters = {};
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('status')) filters.member_status = urlParams.get('status');
            if (urlParams.has('type')) filters.member_type = urlParams.get('type');
            if (urlParams.has('search')) filters.search = urlParams.get('search');
            if (urlParams.has('hide_dismissed')) filters.hide_dismissed = urlParams.get('hide_dismissed');
            if (urlParams.has('sort_by')) filters.sort_by = urlParams.get('sort_by');
            if (urlParams.has('volunteer_status')) filters.volunteer_status = urlParams.get('volunteer_status');
            if (urlParams.has('role')) filters.role = urlParams.get('role');
            
            return filters;
        }
        
        function showPrintListModal() {
            const modal = new bootstrap.Modal(document.getElementById('printListModal'));
            modal.show();
        }
        
        function generateListFromModal() {
            const templateId = document.getElementById('listTemplateSelect').value;
            if (templateId) {
                const filters = getCurrentFilters();
                const params = new URLSearchParams({
                    template_id: templateId,
                    entity: 'members',
                    ...filters
                });
                window.open('print_preview.php?' + params.toString(), '_blank');
                const modal = bootstrap.Modal.getInstance(document.getElementById('printListModal'));
                modal.hide();
            }
        }
    </script>

    <!-- Print List Template Selection Modal -->
    <div class="modal fade" id="printListModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleziona Template Lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select id="listTemplateSelect" class="form-select">
                            <option value="4">Libro Soci</option>
                            <option value="5">Elenco Telefonico</option>
                            <option value="6">Tessere Multiple</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <small><i class="bi bi-info-circle"></i> Verranno stampati i record secondo i filtri attualmente applicati</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="generateListFromModal()">
                        <i class="bi bi-printer"></i> Genera
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
