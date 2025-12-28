<?php
/**
 * Gestione Riunioni - Lista
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\MeetingController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('meetings', 'view')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MeetingController($db, $config);

$filters = [
    'type' => $_GET['type'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$meetings = $controller->index($filters, $page, $perPage);

$totalMeetings = $db->fetchOne("SELECT COUNT(*) as count FROM meetings")['count'] ?? 0;

$pageTitle = 'Gestione Riunioni e Assemblee';
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
                                <li><a class="dropdown-item" href="#" onclick="printList('verbale'); return false;">
                                    <i class="bi bi-file-text"></i> Verbale Riunione
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="printList('foglio_presenze'); return false;">
                                    <i class="bi bi-clipboard-check"></i> Foglio Presenze
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="showPrintListModal(); return false;">
                                    <i class="bi bi-gear"></i> Scegli Template...
                                </a></li>
                            </ul>
                        </div>
                        <?php if ($app->checkPermission('meetings', 'create')): ?>
                            <a href="meeting_edit.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuova Riunione
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Totale Riunioni</h5>
                                <h2><?php echo number_format($totalMeetings); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Luogo, data (01/12/2025, 01.12.2025, 01-12-2025)...">
                            </div>
                            <div class="col-md-5">
                                <label for="type" class="form-label">Tipo</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Tutti</option>
                                    <option value="assemblea_ordinaria" <?php echo $filters['type'] === 'assemblea_ordinaria' ? 'selected' : ''; ?>>Assemblea dei Soci Ordinaria</option>
                                    <option value="assemblea_straordinaria" <?php echo $filters['type'] === 'assemblea_straordinaria' ? 'selected' : ''; ?>>Assemblea dei Soci Straordinaria</option>
                                    <option value="consiglio_direttivo" <?php echo $filters['type'] === 'consiglio_direttivo' ? 'selected' : ''; ?>>Consiglio Direttivo</option>
                                    <option value="riunione_capisquadra" <?php echo $filters['type'] === 'riunione_capisquadra' ? 'selected' : ''; ?>>Riunione dei Capisquadra</option>
                                    <option value="riunione_nucleo" <?php echo $filters['type'] === 'riunione_nucleo' ? 'selected' : ''; ?>>Riunione di Nucleo</option>
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
                
                <!-- Tabella Riunioni -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Elenco Riunioni</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Data</th>
                                        <th>Luogo</th>
                                        <th>Convocatore</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($meetings)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                Nessuna riunione trovata
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($meetings as $meeting): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-people"></i>
                                                    <?php 
                                                    echo MeetingController::MEETING_TYPE_NAMES[$meeting['meeting_type']] ?? ucfirst(str_replace('_', ' ', $meeting['meeting_type']));
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    echo date('d/m/Y', strtotime($meeting['meeting_date']));
                                                    if (!empty($meeting['start_time'])) {
                                                        echo ' ' . date('H:i', strtotime($meeting['start_time']));
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($meeting['location'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($meeting['convocator'] ?? '-'); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="meeting_view.php?id=<?php echo $meeting['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="Visualizza">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($app->checkPermission('meetings', 'edit')): ?>
                                                            <a href="meeting_edit.php?id=<?php echo $meeting['id']; ?>" 
                                                               class="btn btn-sm btn-warning" title="Modifica">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="meeting_participants.php?id=<?php echo $meeting['id']; ?>" 
                                                               class="btn btn-sm btn-success" title="Gestisci Partecipanti">
                                                                <i class="bi bi-people-fill"></i>
                                                            </a>
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
        // Print list functionality
        function printList(type) {
            let templateId = null;
            let filters = getCurrentFilters();
            
            switch(type) {
                case 'verbale':
                    templateId = 8; // Verbale di Riunione
                    break;
                case 'foglio_presenze':
                    templateId = 9; // Foglio Presenze Riunione
                    break;
            }
            
            if (templateId) {
                const params = new URLSearchParams({
                    template_id: templateId,
                    entity: 'meetings',
                    ...filters
                });
                window.open('print_preview.php?' + params.toString(), '_blank');
            }
        }
        
        function getCurrentFilters() {
            const filters = {};
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('type')) filters.meeting_type = urlParams.get('type');
            if (urlParams.has('search')) filters.search = urlParams.get('search');
            
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
                    entity: 'meetings',
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
                        <label for="listTemplateSelect" class="form-label">Template Disponibili</label>
                        <select class="form-select" id="listTemplateSelect">
                            <option value="">Seleziona un template...</option>
                            <?php
                            // Fetch available list templates for meetings
                            $templateSql = "SELECT id, name FROM print_templates 
                                           WHERE entity_type = 'meetings' 
                                           AND is_active = 1 
                                           ORDER BY name";
                            $templates = $db->fetchAll($templateSql);
                            foreach ($templates as $template) {
                                echo '<option value="' . $template['id'] . '">' . 
                                     htmlspecialchars($template['name']) . '</option>';
                            }
                            ?>
                        </select>
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
