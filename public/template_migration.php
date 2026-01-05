<?php
/**
 * Template Migration Tool
 * 
 * Migrate database templates to file-based templates
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\EnhancedPrintController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi admin
if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato - Solo amministratori');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EnhancedPrintController($db, $config);

$message = '';
$error = '';
$migratedTemplates = [];

// Handle migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
    $templateIds = $_POST['templates'] ?? [];
    
    foreach ($templateIds as $templateId) {
        try {
            $filename = $controller->migrateDbTemplateToFile(intval($templateId));
            $migratedTemplates[] = [
                'id' => $templateId,
                'filename' => $filename,
                'status' => 'success'
            ];
        } catch (\Exception $e) {
            $migratedTemplates[] = [
                'id' => $templateId,
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
    
    $message = count($migratedTemplates) . ' template processati';
}

// Get all database templates
$sql = "SELECT id, name, entity_type, template_type, description, is_active 
        FROM print_templates 
        ORDER BY entity_type, name";
$dbTemplates = $db->fetchAll($sql);

$pageTitle = 'Migrazione Template';
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
                        <i class="bi bi-arrow-repeat"></i>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="enhanced_print.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Torna al Sistema di Stampa
                        </a>
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

                <!-- Migration Results -->
                <?php if (!empty($migratedTemplates)): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Risultati Migrazione</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>File Creato</th>
                                        <th>Stato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($migratedTemplates as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['id']); ?></td>
                                            <td>
                                                <?php if ($result['status'] === 'success'): ?>
                                                    <code><?php echo htmlspecialchars($result['filename']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-danger">
                                                        <?php echo htmlspecialchars($result['error']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($result['status'] === 'success'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle"></i> Successo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle"></i> Errore
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Instructions -->
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle"></i> Informazioni
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Questo strumento converte i template esistenti dal database al nuovo sistema file-based.</p>
                        <h6>Vantaggi del Sistema File-Based:</h6>
                        <ul>
                            <li><strong>Portabilità:</strong> I template sono file JSON facilmente esportabili</li>
                            <li><strong>Versionamento:</strong> Possibile usare Git per tracciare le modifiche</li>
                            <li><strong>Backup:</strong> Inclusi automaticamente nel backup del codice</li>
                            <li><strong>Performance:</strong> Caricamento più veloce rispetto al database</li>
                            <li><strong>Multi-tabella:</strong> Supporto migliorato per dati da tabelle correlate</li>
                        </ul>
                        <div class="alert alert-warning mt-3" role="alert">
                            <strong>Nota:</strong> I template database originali non verranno eliminati. 
                            Dopo la migrazione, puoi disattivarli o eliminarli manualmente dalla pagina di gestione template.
                        </div>
                    </div>
                </div>

                <!-- Database Templates List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Template Database Disponibili</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dbTemplates)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Nessun template database trovato.
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="50">
                                                    <input type="checkbox" id="selectAll" class="form-check-input" onclick="toggleAll(this)">
                                                </th>
                                                <th>ID</th>
                                                <th>Nome</th>
                                                <th>Entità</th>
                                                <th>Tipo</th>
                                                <th>Descrizione</th>
                                                <th>Stato</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dbTemplates as $template): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="templates[]" 
                                                               value="<?php echo $template['id']; ?>" 
                                                               class="form-check-input template-checkbox">
                                                    </td>
                                                    <td><?php echo $template['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($template['name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $entityLabels = [
                                                            'members' => 'Soci',
                                                            'junior_members' => 'Cadetti',
                                                            'vehicles' => 'Mezzi',
                                                            'meetings' => 'Riunioni',
                                                            'events' => 'Eventi',
                                                            'member_applications' => 'Domande'
                                                        ];
                                                        echo $entityLabels[$template['entity_type']] ?? $template['entity_type'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo htmlspecialchars($template['template_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($template['description'] ?? ''); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php if ($template['is_active']): ?>
                                                            <span class="badge bg-success">Attivo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Disattivo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" name="migrate" class="btn btn-primary btn-lg">
                                        <i class="bi bi-arrow-repeat"></i> Migra Template Selezionati
                                    </button>
                                    <small class="text-muted ms-2">
                                        Seleziona i template che vuoi convertire in formato file
                                    </small>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.template-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }
    </script>
</body>
</html>
