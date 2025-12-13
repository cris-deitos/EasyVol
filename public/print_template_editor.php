<?php
/**
 * Print Template Editor
 * 
 * Editor WYSIWYG per template stampe con TinyMCE
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

// Verifica permessi
if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PrintTemplateController($db, $config);

$templateId = $_GET['id'] ?? null;
$template = null;
$isEdit = false;

if ($templateId) {
    $template = $controller->getById($templateId);
    if ($template) {
        $isEdit = true;
    } else {
        header('Location: print_templates.php?error=not_found');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    
    try {
        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description'] ?? null,
            'template_type' => $_POST['template_type'],
            'data_scope' => $_POST['data_scope'],
            'entity_type' => $_POST['entity_type'],
            'html_content' => $_POST['html_content'],
            'css_content' => $_POST['css_content'] ?? null,
            'relations' => !empty($_POST['relations']) ? json_encode($_POST['relations']) : null,
            'filter_config' => !empty($_POST['filter_config']) ? json_encode($_POST['filter_config']) : null,
            'variables' => !empty($_POST['variables']) ? json_encode($_POST['variables']) : null,
            'page_format' => $_POST['page_format'] ?? 'A4',
            'page_orientation' => $_POST['page_orientation'] ?? 'portrait',
            'show_header' => isset($_POST['show_header']) ? 1 : 0,
            'show_footer' => isset($_POST['show_footer']) ? 1 : 0,
            'header_content' => $_POST['header_content'] ?? null,
            'footer_content' => $_POST['footer_content'] ?? null,
            'watermark' => $_POST['watermark'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
        ];
        
        if ($isEdit) {
            $controller->update($templateId, $data, $userId);
            $message = 'Template aggiornato con successo';
        } else {
            $templateId = $controller->create($data, $userId);
            $message = 'Template creato con successo';
            $isEdit = true;
            $template = $controller->getById($templateId);
        }
        
        $_SESSION['success_message'] = $message;
        header('Location: print_template_editor.php?id=' . $templateId);
        exit;
        
    } catch (\Exception $e) {
        $error = 'Errore durante il salvataggio: ' . $e->getMessage();
    }
}

// Get available variables based on entity type
$entityType = $template['entity_type'] ?? ($_GET['entity'] ?? 'members');
$availableVariables = $controller->getAvailableVariables($entityType);
$availableRelations = $controller->getAvailableRelations($entityType);

$pageTitle = $isEdit ? 'Modifica Template' : 'Nuovo Template';
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
        .variables-panel {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
        }
        .variable-item {
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.15s;
        }
        .variable-item:hover {
            background-color: #f8f9fa;
        }
        .variable-code {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #0066cc;
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
                        <a href="print_templates.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-info" onclick="previewTemplate()">
                                <i class="bi bi-eye"></i> Anteprima
                            </button>
                            <button type="submit" form="templateForm" class="btn btn-primary">
                                <i class="bi bi-save"></i> Salva
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo htmlspecialchars($_SESSION['success_message']); 
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form id="templateForm" method="POST">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Basic Info -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Informazioni Base</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome Template *</label>
                                        <input type="text" name="name" class="form-control" 
                                               value="<?php echo htmlspecialchars($template['name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Descrizione</label>
                                        <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($template['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tipo Entità *</label>
                                            <select name="entity_type" id="entityType" class="form-select" required>
                                                <option value="members" <?php echo ($template['entity_type'] ?? $entityType) === 'members' ? 'selected' : ''; ?>>Soci</option>
                                                <option value="junior_members" <?php echo ($template['entity_type'] ?? '') === 'junior_members' ? 'selected' : ''; ?>>Soci Minorenni</option>
                                                <option value="vehicles" <?php echo ($template['entity_type'] ?? '') === 'vehicles' ? 'selected' : ''; ?>>Mezzi</option>
                                                <option value="meetings" <?php echo ($template['entity_type'] ?? '') === 'meetings' ? 'selected' : ''; ?>>Riunioni</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tipo Template *</label>
                                            <select name="template_type" id="templateType" class="form-select" required>
                                                <option value="single" <?php echo ($template['template_type'] ?? 'single') === 'single' ? 'selected' : ''; ?>>Singolo</option>
                                                <option value="list" <?php echo ($template['template_type'] ?? '') === 'list' ? 'selected' : ''; ?>>Lista</option>
                                                <option value="multi_page" <?php echo ($template['template_type'] ?? '') === 'multi_page' ? 'selected' : ''; ?>>Multi-pagina</option>
                                                <option value="relational" <?php echo ($template['template_type'] ?? '') === 'relational' ? 'selected' : ''; ?>>Relazionale</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Scope Dati *</label>
                                        <select name="data_scope" class="form-select" required>
                                            <option value="single" <?php echo ($template['data_scope'] ?? 'single') === 'single' ? 'selected' : ''; ?>>Singolo Record</option>
                                            <option value="filtered" <?php echo ($template['data_scope'] ?? '') === 'filtered' ? 'selected' : ''; ?>>Record Filtrati</option>
                                            <option value="all" <?php echo ($template['data_scope'] ?? '') === 'all' ? 'selected' : ''; ?>>Tutti i Record</option>
                                            <option value="custom" <?php echo ($template['data_scope'] ?? '') === 'custom' ? 'selected' : ''; ?>>Personalizzato</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- HTML Content -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Contenuto HTML</h5>
                                </div>
                                <div class="card-body">
                                    <textarea id="htmlContent" name="html_content" class="form-control"><?php echo htmlspecialchars($template['html_content'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- CSS Content -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">CSS Personalizzato</h5>
                                </div>
                                <div class="card-body">
                                    <textarea name="css_content" class="form-control" rows="10" style="font-family: monospace;"><?php echo htmlspecialchars($template['css_content'] ?? ''); ?></textarea>
                                    <div class="form-text">CSS per personalizzare l'aspetto del documento stampato</div>
                                </div>
                            </div>

                            <!-- Relations (for relational templates) -->
                            <div class="card mb-3" id="relationsCard" style="display: none;">
                                <div class="card-header">
                                    <h5 class="mb-0">Tabelle Relazionali</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $selectedRelations = [];
                                    if ($template && $template['relations']) {
                                        $selectedRelations = json_decode($template['relations'], true) ?? [];
                                    }
                                    ?>
                                    <?php foreach ($availableRelations as $table => $label): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="relations[]" 
                                                   value="<?php echo $table; ?>" id="rel_<?php echo $table; ?>"
                                                   <?php echo in_array($table, $selectedRelations) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="rel_<?php echo $table; ?>">
                                                <?php echo htmlspecialchars($label); ?> 
                                                <code class="text-muted">({{#each <?php echo $table; ?>}})</code>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Header/Footer -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Header e Footer</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="show_header" id="showHeader"
                                               <?php echo ($template['show_header'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="showHeader">Mostra Header</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Contenuto Header</label>
                                        <textarea name="header_content" class="form-control" rows="3"><?php echo htmlspecialchars($template['header_content'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="show_footer" id="showFooter"
                                               <?php echo ($template['show_footer'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="showFooter">Mostra Footer</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Contenuto Footer</label>
                                        <textarea name="footer_content" class="form-control" rows="3"><?php echo htmlspecialchars($template['footer_content'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Page Settings -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Impostazioni Pagina</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Formato Pagina</label>
                                        <select name="page_format" class="form-select">
                                            <option value="A4" <?php echo ($template['page_format'] ?? 'A4') === 'A4' ? 'selected' : ''; ?>>A4</option>
                                            <option value="A3" <?php echo ($template['page_format'] ?? '') === 'A3' ? 'selected' : ''; ?>>A3</option>
                                            <option value="Letter" <?php echo ($template['page_format'] ?? '') === 'Letter' ? 'selected' : ''; ?>>Letter</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Orientamento</label>
                                        <select name="page_orientation" class="form-select">
                                            <option value="portrait" <?php echo ($template['page_orientation'] ?? 'portrait') === 'portrait' ? 'selected' : ''; ?>>Verticale</option>
                                            <option value="landscape" <?php echo ($template['page_orientation'] ?? '') === 'landscape' ? 'selected' : ''; ?>>Orizzontale</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Watermark (opzionale)</label>
                                        <input type="text" name="watermark" class="form-control" 
                                               value="<?php echo htmlspecialchars($template['watermark'] ?? ''); ?>"
                                               placeholder="es: BOZZA, CONFIDENZIALE">
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                               <?php echo ($template['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="isActive">Template Attivo</label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_default" id="isDefault"
                                               <?php echo ($template['is_default'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="isDefault">Template di Default</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Variables Panel -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Variabili Disponibili</h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="bi bi-info-circle"></i>
                                            Clicca su una variabile per copiarla negli appunti
                                        </small>
                                    </div>
                                    <div class="variables-panel">
                                        <?php foreach ($availableVariables as $var): ?>
                                            <div class="variable-item" onclick="insertVariable('<?php echo $var['name']; ?>')" title="<?php echo htmlspecialchars($var['description']); ?>">
                                                <div class="variable-code">{{<?php echo $var['name']; ?>}}</div>
                                                <small class="text-muted"><?php echo htmlspecialchars($var['description']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Handlebars Help -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Sintassi Loop</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Per iterare su array:</strong></p>
                                    <pre style="background: #f8f9fa; padding: 1rem; border-radius: 0.25rem;"><code>{{#each nome_array}}
  {{campo1}}
  {{campo2}}
{{/each}}</code></pre>
                                    
                                    <p class="mt-3"><strong>Esempio per contatti:</strong></p>
                                    <pre style="background: #f8f9fa; padding: 1rem; border-radius: 0.25rem;"><code>{{#each member_contacts}}
  {{contact_type}}: {{value}}
{{/each}}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tiny.cloud/1/svhvbvqwcchk5enuxule1zzpw3zpm3rvldernny7t3vwh22j/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#htmlContent',
            height: 600,
            menubar: true,
            plugins: [
                'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography uploadcare | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 12pt; }',
          
            setup: function (editor) {
                // Add custom button for inserting variables
                editor.ui.registry.addButton('insertVariable', {
                    text: 'Inserisci Variabile',
                    onAction: function () {
                        showVariableModal(editor);
                    }
                });
            }
        });

        function insertVariable(varName) {
            const variable = '{{' + varName + '}}';
            
            // Copy to clipboard
            navigator.clipboard.writeText(variable).then(function() {
                // Show toast notification
                const toast = document.createElement('div');
                toast.className = 'position-fixed bottom-0 end-0 p-3';
                toast.style.zIndex = '11';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <strong class="me-auto">Copiato!</strong>
                            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
                        </div>
                        <div class="toast-body">
                            Variabile ${variable} copiata negli appunti
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            });
        }

        function showVariableModal(editor) {
            // Get all variables from the panel
            const variables = <?php echo json_encode(array_map(function($v) { return $v['name']; }, $availableVariables)); ?>;
            
            editor.windowManager.open({
                title: 'Inserisci Variabile',
                body: {
                    type: 'panel',
                    items: [{
                        type: 'selectbox',
                        name: 'variable',
                        label: 'Seleziona Variabile',
                        items: variables.map(v => ({value: v, text: v}))
                    }]
                },
                buttons: [
                    {
                        type: 'cancel',
                        text: 'Annulla'
                    },
                    {
                        type: 'submit',
                        text: 'Inserisci',
                        primary: true
                    }
                ],
                onSubmit: function (api) {
                    const data = api.getData();
                    editor.insertContent('{{' + data.variable + '}}');
                    api.close();
                }
            });
        }

        function previewTemplate() {
            const form = document.getElementById('templateForm');
            const formData = new FormData(form);
            
            // Save to session storage for preview
            const htmlContent = tinymce.get('htmlContent').getContent();
            sessionStorage.setItem('preview_html', htmlContent);
            sessionStorage.setItem('preview_css', formData.get('css_content'));
            
            window.open('print_preview.php?preview=1', '_blank');
        }

        // Show/hide relations card based on template type
        document.getElementById('templateType').addEventListener('change', function() {
            const relationsCard = document.getElementById('relationsCard');
            if (this.value === 'relational') {
                relationsCard.style.display = 'block';
            } else {
                relationsCard.style.display = 'none';
            }
        });

        // Trigger on page load
        document.addEventListener('DOMContentLoaded', function() {
            const templateType = document.getElementById('templateType').value;
            const relationsCard = document.getElementById('relationsCard');
            if (templateType === 'relational') {
                relationsCard.style.display = 'block';
            }
        });

        // Reload page when entity type changes to get new variables
        document.getElementById('entityType').addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('entity', this.value);
            if (confirm('Cambiare il tipo di entità ricaricherà la pagina. I campi non salvati andranno persi. Continuare?')) {
                window.location.href = currentUrl.toString();
            } else {
                // Restore previous value
                this.value = '<?php echo $entityType; ?>';
            }
        });
    </script>
</body>
</html>
