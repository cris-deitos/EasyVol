<?php
/**
 * Enhanced Print System
 * 
 * New file-based template system with rich editing capabilities
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

$db = $app->getDb();
$config = $app->getConfig();
$controller = new EnhancedPrintController($db, $config);

// Get entity type from parameter with validation
$allowedEntities = ['members', 'junior_members', 'vehicles', 'meetings', 'events', 'applications'];
$entityType = $_GET['entity'] ?? 'members';
if (!in_array($entityType, $allowedEntities, true)) {
    $entityType = 'members'; // Fallback to safe default
}

// Get available templates
$templates = $controller->getAvailableTemplates($entityType);

$pageTitle = 'Sistema di Stampa Avanzato';
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
        .template-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            height: 100%;
        }
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .template-card.selected {
            border: 3px solid #0d6efd;
            background: #e7f3ff;
        }
        .template-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .preview-iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #dee2e6;
            background: white;
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
                    <h1 class="h2">
                        <i class="bi bi-printer-fill"></i>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>

                <!-- Entity Type Selector -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Seleziona Tipo di Documento</h5>
                        <div class="btn-group" role="group">
                            <a href="?entity=members" class="btn btn-<?php echo $entityType === 'members' ? 'primary' : 'outline-primary'; ?>">
                                <i class="bi bi-people"></i> Soci
                            </a>
                            <a href="?entity=junior_members" class="btn btn-<?php echo $entityType === 'junior_members' ? 'primary' : 'outline-primary'; ?>">
                                <i class="bi bi-person"></i> Cadetti
                            </a>
                            <a href="?entity=vehicles" class="btn btn-<?php echo $entityType === 'vehicles' ? 'primary' : 'outline-primary'; ?>">
                                <i class="bi bi-truck"></i> Mezzi
                            </a>
                            <a href="?entity=meetings" class="btn btn-<?php echo $entityType === 'meetings' ? 'primary' : 'outline-primary'; ?>">
                                <i class="bi bi-calendar-event"></i> Riunioni
                            </a>
                            <a href="?entity=events" class="btn btn-<?php echo $entityType === 'events' ? 'primary' : 'outline-primary'; ?>">
                                <i class="bi bi-flag"></i> Eventi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Template Selection -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Seleziona Template</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($templates)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Nessun template disponibile per questo tipo di documento.
                                <a href="enhanced_print_template_editor.php?entity=<?php echo $entityType; ?>" class="alert-link">Crea il primo template</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($templates as $template): ?>
                                    <div class="col-md-4">
                                        <div class="card template-card" data-template-id="<?php echo htmlspecialchars($template['id']); ?>" onclick="selectTemplate(this)">
                                            <div class="card-body position-relative">
                                                <span class="badge template-badge bg-<?php echo $template['source'] === 'file' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $template['source'] === 'file' ? 'File' : 'DB'; ?>
                                                </span>
                                                <h6 class="card-title"><?php echo htmlspecialchars($template['name']); ?></h6>
                                                <p class="card-text small text-muted">
                                                    <?php echo htmlspecialchars($template['description']); ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($template['type']); ?></span>
                                                    <small class="text-muted">
                                                        <?php echo strtoupper($template['format']); ?>
                                                        <?php echo $template['orientation'] === 'landscape' ? '↔' : '↕'; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Generation Options -->
                <div class="card mb-3" id="optionsCard" style="display: none;">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Opzioni di Generazione</h5>
                    </div>
                    <div class="card-body">
                        <form id="generateForm">
                            <input type="hidden" id="selectedTemplate" name="template_id">
                            <input type="hidden" name="entity" value="<?php echo htmlspecialchars($entityType); ?>">
                            
                            <div id="singleOptions" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Seleziona Record</label>
                                    <input type="number" class="form-control" name="record_id" placeholder="ID del record">
                                    <div class="form-text">Inserisci l'ID del record da stampare</div>
                                </div>
                            </div>
                            
                            <div id="listOptions" style="display: none;">
                                <h6>Filtri (opzionali)</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Stato</label>
                                        <select class="form-select" name="filters[member_status]">
                                            <option value="">Tutti</option>
                                            <option value="active">Attivo</option>
                                            <option value="suspended">Sospeso</option>
                                            <option value="inactive">Non Attivo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tipo Socio</label>
                                        <select class="form-select" name="filters[member_type]">
                                            <option value="">Tutti</option>
                                            <option value="volunteer">Volontario</option>
                                            <option value="ordinary">Ordinario</option>
                                            <option value="honorary">Onorario</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Data Da</label>
                                        <input type="date" class="form-control" name="filters[date_from]">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Data A</label>
                                        <input type="date" class="form-control" name="filters[date_to]">
                                    </div>
                                </div>
                            </div>
                            
                            <div id="multiPageOptions" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">IDs dei Record (separati da virgola)</label>
                                    <input type="text" class="form-control" name="record_ids" placeholder="1,2,3,4,5">
                                    <div class="form-text">Oppure lascia vuoto e usa i filtri qui sotto</div>
                                </div>
                                <div id="multiPageFilters">
                                    <!-- Same filters as list options -->
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary" onclick="generatePreview()">
                                    <i class="bi bi-eye"></i> Anteprima
                                </button>
                                <button type="button" class="btn btn-success" onclick="generatePDF()">
                                    <i class="bi bi-file-pdf"></i> Genera PDF
                                </button>
                                <button type="button" class="btn btn-warning" onclick="openEditor()">
                                    <i class="bi bi-pencil-square"></i> Modifica e Stampa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Preview -->
                <div class="card" id="previewCard" style="display: none;">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Anteprima</h5>
                    </div>
                    <div class="card-body">
                        <iframe id="previewFrame" class="preview-iframe"></iframe>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedTemplateData = null;

        function selectTemplate(card) {
            // Remove previous selection
            document.querySelectorAll('.template-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Mark as selected
            card.classList.add('selected');
            
            // Get template data
            const templateId = card.dataset.templateId;
            document.getElementById('selectedTemplate').value = templateId;
            
            // Show options card
            document.getElementById('optionsCard').style.display = 'block';
            
            // Get template type from card
            const typeElement = card.querySelector('.badge.bg-primary');
            const templateType = typeElement ? typeElement.textContent.trim() : 'single';
            
            // Show appropriate options
            document.getElementById('singleOptions').style.display = 'none';
            document.getElementById('listOptions').style.display = 'none';
            document.getElementById('multiPageOptions').style.display = 'none';
            
            if (templateType === 'single') {
                document.getElementById('singleOptions').style.display = 'block';
            } else if (templateType === 'list') {
                document.getElementById('listOptions').style.display = 'block';
            } else if (templateType === 'multi_page') {
                document.getElementById('multiPageOptions').style.display = 'block';
            }
        }

        function generatePreview() {
            const form = document.getElementById('generateForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            
            // Show preview card
            document.getElementById('previewCard').style.display = 'block';
            
            // Load preview in iframe
            const iframe = document.getElementById('previewFrame');
            iframe.src = 'enhanced_print_generate.php?' + params.toString();
            
            // Scroll to preview
            document.getElementById('previewCard').scrollIntoView({ behavior: 'smooth' });
        }

        function generatePDF() {
            const form = document.getElementById('generateForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            params.append('output', 'pdf');
            
            // Open PDF in new window
            window.open('enhanced_print_generate.php?' + params.toString(), '_blank');
        }

        function openEditor() {
            const form = document.getElementById('generateForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            
            // Open editor in new window
            window.open('enhanced_print_editor.php?' + params.toString(), '_blank', 'width=1200,height=800');
        }
    </script>
</body>
</html>
