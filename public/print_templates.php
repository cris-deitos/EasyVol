<?php
/**
 * Print Templates Management
 * 
 * Gestione template per stampe e PDF
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\PrintTemplateController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi (solo admin possono gestire template)
if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PrintTemplateController($db, $config);

// Handle actions
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    
    if ($action === 'delete' && isset($_POST['id'])) {
        try {
            $controller->delete($_POST['id']);
            $message = 'Template eliminato con successo';
            $action = 'list';
        } catch (\Exception $e) {
            $error = 'Errore durante l\'eliminazione: ' . $e->getMessage();
        }
    }
    
    if ($action === 'toggle_active' && isset($_POST['id'])) {
        try {
            $template = $controller->getById($_POST['id']);
            if ($template) {
                $controller->update($_POST['id'], array_merge($template, [
                    'is_active' => !$template['is_active']
                ]), $userId);
                $message = 'Stato template aggiornato';
            }
        } catch (\Exception $e) {
            $error = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        }
    }
    
    if ($action === 'export' && isset($_POST['id'])) {
        try {
            $templateData = $controller->exportTemplate($_POST['id']);
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="template_' . $templateData['name'] . '.json"');
            echo json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Exception $e) {
            $error = 'Errore durante l\'esportazione: ' . $e->getMessage();
        }
    }
    
    if ($action === 'import' && isset($_FILES['template_file'])) {
        try {
            $jsonContent = file_get_contents($_FILES['template_file']['tmp_name']);
            $templateData = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('File JSON non valido');
            }
            
            $controller->importTemplate($templateData, $userId);
            $message = 'Template importato con successo';
        } catch (\Exception $e) {
            $error = 'Errore durante l\'importazione: ' . $e->getMessage();
        }
    }
}

// Get filters
$filters = [];
if (!empty($_GET['entity_type'])) {
    $filters['entity_type'] = $_GET['entity_type'];
}
if (!empty($_GET['template_type'])) {
    $filters['template_type'] = $_GET['template_type'];
}

$templates = $controller->getAll($filters);

$pageTitle = 'Gestione Template Stampe';
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
                        <i class="bi bi-printer"></i>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="print_template_editor.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nuovo Template
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="bi bi-upload"></i> Importa
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo Entità</label>
                                <select name="entity_type" class="form-select">
                                    <option value="">Tutti</option>
                                    <option value="members" <?php echo ($_GET['entity_type'] ?? '') === 'members' ? 'selected' : ''; ?>>Soci</option>
                                    <option value="junior_members" <?php echo ($_GET['entity_type'] ?? '') === 'junior_members' ? 'selected' : ''; ?>>Soci Minorenni</option>
                                    <option value="vehicles" <?php echo ($_GET['entity_type'] ?? '') === 'vehicles' ? 'selected' : ''; ?>>Mezzi</option>
                                    <option value="meetings" <?php echo ($_GET['entity_type'] ?? '') === 'meetings' ? 'selected' : ''; ?>>Riunioni</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo Template</label>
                                <select name="template_type" class="form-select">
                                    <option value="">Tutti</option>
                                    <option value="single" <?php echo ($_GET['template_type'] ?? '') === 'single' ? 'selected' : ''; ?>>Singolo</option>
                                    <option value="list" <?php echo ($_GET['template_type'] ?? '') === 'list' ? 'selected' : ''; ?>>Lista</option>
                                    <option value="multi_page" <?php echo ($_GET['template_type'] ?? '') === 'multi_page' ? 'selected' : ''; ?>>Multi-pagina</option>
                                    <option value="relational" <?php echo ($_GET['template_type'] ?? '') === 'relational' ? 'selected' : ''; ?>>Relazionale</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-funnel"></i> Filtra
                                </button>
                                <a href="print_templates.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Templates List -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($templates)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Nessun template trovato. Crea il tuo primo template!
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Tipo</th>
                                            <th>Entità</th>
                                            <th>Formato</th>
                                            <th>Stato</th>
                                            <th>Default</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($templates as $template): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($template['name']); ?></strong>
                                                    <?php if ($template['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($template['description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $typeLabels = [
                                                        'single' => '<span class="badge bg-primary">Singolo</span>',
                                                        'list' => '<span class="badge bg-info">Lista</span>',
                                                        'multi_page' => '<span class="badge bg-warning">Multi-pagina</span>',
                                                        'relational' => '<span class="badge bg-success">Relazionale</span>',
                                                    ];
                                                    echo $typeLabels[$template['template_type']] ?? $template['template_type'];
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $entityLabels = [
                                                        'members' => 'Soci',
                                                        'junior_members' => 'Soci Minorenni',
                                                        'vehicles' => 'Mezzi',
                                                        'meetings' => 'Riunioni',
                                                    ];
                                                    echo $entityLabels[$template['entity_type']] ?? $template['entity_type'];
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo strtoupper($template['page_format']); ?>
                                                    <?php echo $template['page_orientation'] === 'landscape' ? '(Orizzontale)' : '(Verticale)'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($template['is_active']): ?>
                                                        <span class="badge bg-success">Attivo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Disattivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($template['is_default']): ?>
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="print_template_editor.php?id=<?php echo $template['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Modifica">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="previewTemplate(<?php echo $template['id']; ?>)" title="Anteprima">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                            <button type="submit" name="action" value="export" 
                                                                    class="btn btn-outline-secondary" title="Esporta">
                                                                <i class="bi bi-download"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Sei sicuro di voler eliminare questo template?');">
                                                            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                            <button type="submit" name="action" value="delete" 
                                                                    class="btn btn-outline-danger" title="Elimina">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Importa Template</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">File JSON Template</label>
                            <input type="file" name="template_file" class="form-control" accept=".json" required>
                            <div class="form-text">Seleziona un file JSON esportato da un template</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" name="action" value="import" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Importa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewTemplate(templateId) {
            window.open('print_preview.php?template_id=' + templateId, '_blank');
        }
    </script>
</body>
</html>
