<?php
/**
 * XML Print Template Visual Editor
 * 
 * Advanced XML template editor with:
 * - Split view: XML code editor + Live preview
 * - Visual/Graphical editor that updates XML
 * - Syntax highlighting
 * - Template validation
 * - Real-time preview
 */

// Configuration constants
define('MAX_XML_SIZE', 1048576); // 1MB limit for XML content

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\PrintTemplateController;
use EasyVol\Utils\XmlTemplateProcessor;

$app = App::getInstance();

// Check authentication
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permissions
if (!$app->checkPermission('settings', 'edit')) {
    die('Accesso negato');
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new PrintTemplateController($db, $config);
$xmlProcessor = new XmlTemplateProcessor($config);

$templateId = $_GET['id'] ?? null;
$template = null;
$isEdit = false;
$xmlContent = '';

// Load existing template
if ($templateId) {
    $template = $controller->getById($templateId);
    if ($template) {
        $isEdit = true;
        $xmlContent = $template['xml_content'] ?? '';
        
        // If XML is empty but HTML exists, offer to convert
        if (empty($xmlContent) && !empty($template['html_content'])) {
            $xmlContent = $controller->htmlToXml($template);
        }
    } else {
        header('Location: print_templates.php?error=not_found');
        exit;
    }
} else {
    // Load default template structure
    $exampleFile = __DIR__ . '/../templates/example_xml_member_card.xml';
    if (file_exists($exampleFile)) {
        $xmlContent = file_get_contents($exampleFile);
    }
}

// Handle AJAX preview request
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    header('Content-Type: application/json');
    
    // Verify this is an AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    try {
        // Sanitize and validate XML content
        $xmlContent = $_POST['xml_content'] ?? '';
        
        // Basic validation - check if it looks like XML
        if (empty($xmlContent) || strpos(trim($xmlContent), '<?xml') !== 0) {
            throw new \Exception('Invalid XML content');
        }
        
        // Validate max size (prevent DoS)
        if (strlen($xmlContent) > MAX_XML_SIZE) {
            throw new \Exception('XML content too large');
        }
        
        $entityType = filter_input(INPUT_POST, 'entity_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'members';
        
        // Validate entity type
        $validEntityTypes = ['members', 'junior_members', 'vehicles', 'meetings'];
        if (!in_array($entityType, $validEntityTypes)) {
            throw new \Exception('Invalid entity type');
        }
        
        // Get sample data for preview
        $sampleData = $controller->getSampleData($entityType);
        
        // Process XML with validation
        $validation = $xmlProcessor->validate($xmlContent);
        if (!$validation['valid']) {
            throw new \Exception('XML validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $result = $xmlProcessor->process($xmlContent, $sampleData);
        
        echo json_encode([
            'success' => true,
            'html' => $result['html'],
            'css' => $result['css']
        ]);
    } catch (\Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX validation request
if (isset($_POST['action']) && $_POST['action'] === 'validate') {
    header('Content-Type: application/json');
    
    // Verify this is an AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['valid' => false, 'errors' => ['Invalid request']]);
        exit;
    }
    
    try {
        $xmlContent = $_POST['xml_content'] ?? '';
        
        // Validate max size
        if (strlen($xmlContent) > MAX_XML_SIZE) {
            throw new \Exception('XML content too large');
        }
        
        $validation = $xmlProcessor->validate($xmlContent);
        
        echo json_encode($validation);
    } catch (\Exception $e) {
        echo json_encode([
            'valid' => false,
            'errors' => [$e->getMessage()]
        ]);
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $userId = $_SESSION['user_id'];
    
    try {
        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description'] ?? null,
            'template_type' => $_POST['template_type'],
            'template_format' => 'xml',
            'data_scope' => $_POST['data_scope'],
            'entity_type' => $_POST['entity_type'],
            'xml_content' => $_POST['xml_content'],
            'xml_schema_version' => '1.0',
            'page_format' => $_POST['page_format'] ?? 'A4',
            'page_orientation' => $_POST['page_orientation'] ?? 'portrait',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
        ];
        
        // Validate XML before saving
        $validation = $xmlProcessor->validate($data['xml_content']);
        if (!$validation['valid']) {
            throw new \Exception('XML non valido: ' . implode(', ', $validation['errors']));
        }
        
        if ($isEdit) {
            $controller->update($templateId, $data, $userId);
            $message = 'Template XML aggiornato con successo';
        } else {
            $templateId = $controller->create($data, $userId);
            $message = 'Template XML creato con successo';
            $isEdit = true;
            $template = $controller->getById($templateId);
        }
        
        $_SESSION['success_message'] = $message;
        header('Location: xml_template_editor.php?id=' . $templateId);
        exit;
        
    } catch (\Exception $e) {
        $error = 'Errore durante il salvataggio: ' . $e->getMessage();
    }
}

$entityType = $template['entity_type'] ?? ($_GET['entity'] ?? 'members');
$pageTitle = $isEdit ? 'Modifica Template XML' : 'Nuovo Template XML';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- CodeMirror for XML editing -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/theme/monokai.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .editor-container {
            display: flex;
            height: calc(100vh - 200px);
            gap: 10px;
            margin-top: 20px;
        }
        
        .editor-panel {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .panel-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .panel-body {
            flex: 1;
            overflow: auto;
            padding: 0;
        }
        
        .CodeMirror {
            height: 100%;
            font-size: 13px;
        }
        
        .preview-content {
            padding: 20px;
        }
        
        .preview-frame {
            border: 1px solid #dee2e6;
            background: white;
            padding: 20px;
            min-height: 400px;
        }
        
        .toolbar {
            display: flex;
            gap: 5px;
        }
        
        .visual-editor {
            padding: 20px;
            display: none;
        }
        
        .visual-editor.active {
            display: block;
        }
        
        .element-palette {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .element-btn {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .element-btn:hover {
            background: #f8f9fa;
            border-color: #0d6efd;
        }
        
        .validation-errors {
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .validation-success {
            background: #d1e7dd;
            border: 1px solid #badbcc;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3><?php echo htmlspecialchars($pageTitle); ?></h3>
                    <a href="print_templates.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Torna ai Template
                    </a>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="templateForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome Template *</label>
                            <input type="text" name="name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($template['name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo Entità *</label>
                            <select name="entity_type" class="form-select" required id="entityTypeSelect">
                                <option value="members" <?php echo $entityType === 'members' ? 'selected' : ''; ?>>Soci</option>
                                <option value="junior_members" <?php echo $entityType === 'junior_members' ? 'selected' : ''; ?>>Cadetti</option>
                                <option value="vehicles" <?php echo $entityType === 'vehicles' ? 'selected' : ''; ?>>Mezzi</option>
                                <option value="meetings" <?php echo $entityType === 'meetings' ? 'selected' : ''; ?>>Riunioni</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Tipo Template *</label>
                            <select name="template_type" class="form-select" required>
                                <option value="single" <?php echo ($template['template_type'] ?? '') === 'single' ? 'selected' : ''; ?>>Singolo</option>
                                <option value="list" <?php echo ($template['template_type'] ?? '') === 'list' ? 'selected' : ''; ?>>Lista</option>
                                <option value="multi_page" <?php echo ($template['template_type'] ?? '') === 'multi_page' ? 'selected' : ''; ?>>Multi-pagina</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Scope *</label>
                            <select name="data_scope" class="form-select" required>
                                <option value="single" <?php echo ($template['data_scope'] ?? '') === 'single' ? 'selected' : ''; ?>>Singolo</option>
                                <option value="filtered" <?php echo ($template['data_scope'] ?? '') === 'filtered' ? 'selected' : ''; ?>>Filtrati</option>
                                <option value="all" <?php echo ($template['data_scope'] ?? '') === 'all' ? 'selected' : ''; ?>>Tutti</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Formato Pagina</label>
                            <select name="page_format" class="form-select">
                                <option value="A4" <?php echo ($template['page_format'] ?? 'A4') === 'A4' ? 'selected' : ''; ?>>A4</option>
                                <option value="A3" <?php echo ($template['page_format'] ?? '') === 'A3' ? 'selected' : ''; ?>>A3</option>
                                <option value="Letter" <?php echo ($template['page_format'] ?? '') === 'Letter' ? 'selected' : ''; ?>>Letter</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Orientamento</label>
                            <select name="page_orientation" class="form-select">
                                <option value="portrait" <?php echo ($template['page_orientation'] ?? 'portrait') === 'portrait' ? 'selected' : ''; ?>>Verticale</option>
                                <option value="landscape" <?php echo ($template['page_orientation'] ?? '') === 'landscape' ? 'selected' : ''; ?>>Orizzontale</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrizione</label>
                        <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($template['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="editor-container">
                        <!-- XML Code Editor -->
                        <div class="editor-panel">
                            <div class="panel-header">
                                <h5><i class="bi bi-code-slash"></i> Editor XML</h5>
                                <div class="toolbar">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="validateBtn">
                                        <i class="bi bi-check-circle"></i> Valida
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="formatBtn">
                                        <i class="bi bi-indent"></i> Formatta
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info" id="helpBtn">
                                        <i class="bi bi-question-circle"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="panel-body">
                                <textarea id="xmlEditor" name="xml_content"><?php echo htmlspecialchars($xmlContent); ?></textarea>
                            </div>
                            <div id="validationResult" style="padding: 10px; display: none;"></div>
                        </div>
                        
                        <!-- Live Preview -->
                        <div class="editor-panel">
                            <div class="panel-header">
                                <h5><i class="bi bi-eye"></i> Anteprima</h5>
                                <div class="toolbar">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="refreshPreviewBtn">
                                        <i class="bi bi-arrow-clockwise"></i> Aggiorna
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleViewBtn">
                                        <i class="bi bi-layout-sidebar"></i> Vista
                                    </button>
                                </div>
                            </div>
                            <div class="panel-body">
                                <div class="preview-content">
                                    <div id="previewFrame" class="preview-frame">
                                        <div class="text-center text-muted">
                                            <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                                            <p>Clicca "Aggiorna" per vedere l'anteprima</p>
                                        </div>
                                    </div>
                                    <div id="previewError" class="alert alert-danger mt-3" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive" 
                                   <?php echo ($template['is_active'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isActive">Attivo</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="is_default" class="form-check-input" id="isDefault" 
                                   <?php echo ($template['is_default'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isDefault">Predefinito</label>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salva Template
                        </button>
                        <a href="print_templates.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Annulla
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Guida Editor XML</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Struttura Template XML</h6>
                    <pre><code>&lt;template version="1.0"&gt;
    &lt;metadata&gt;...&lt;/metadata&gt;
    &lt;page format="A4" orientation="portrait"&gt;...&lt;/page&gt;
    &lt;styles&gt;&lt;![CDATA[...]]&gt;&lt;/styles&gt;
    &lt;body&gt;...&lt;/body&gt;
&lt;/template&gt;</code></pre>
                    
                    <h6 class="mt-3">Elementi Speciali</h6>
                    <ul>
                        <li><code>&lt;variable name="campo" /&gt;</code> - Inserisce un campo dati</li>
                        <li><code>&lt;loop source="array"&gt;...&lt;/loop&gt;</code> - Cicla su un array</li>
                        <li><code>&lt;condition test="campo"&gt;...&lt;/condition&gt;</code> - Contenuto condizionale</li>
                        <li><code>&lt;section class="..."&gt;...&lt;/section&gt;</code> - Contenitore con stile</li>
                        <li><code>&lt;table&gt;...&lt;/table&gt;</code> - Tabella HTML</li>
                    </ul>
                    
                    <h6 class="mt-3">Formattazione Variabili</h6>
                    <p>Usa l'attributo <code>format</code>:</p>
                    <ul>
                        <li><code>format="date"</code> - Formato data italiana</li>
                        <li><code>format="datetime"</code> - Data e ora</li>
                        <li><code>format="currency"</code> - Formato valuta</li>
                        <li><code>format="uppercase"</code> - Maiuscolo</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CodeMirror -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/edit/closetag.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/fold/xml-fold.min.js"></script>
    
    <script>
        // Initialize CodeMirror
        const editor = CodeMirror.fromTextArea(document.getElementById('xmlEditor'), {
            mode: 'xml',
            theme: 'monokai',
            lineNumbers: true,
            autoCloseTags: true,
            indentUnit: 4,
            lineWrapping: true
        });
        
        // Validate XML
        document.getElementById('validateBtn').addEventListener('click', async function() {
            const xmlContent = editor.getValue();
            const resultDiv = document.getElementById('validationResult');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=validate&xml_content=' + encodeURIComponent(xmlContent)
                });
                
                const result = await response.json();
                
                if (result.valid) {
                    resultDiv.className = 'validation-success';
                    resultDiv.innerHTML = '<i class="bi bi-check-circle"></i> XML valido!';
                } else {
                    resultDiv.className = 'validation-errors';
                    resultDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Errori:<br>' + 
                        result.errors.join('<br>');
                }
                
                resultDiv.style.display = 'block';
                setTimeout(() => {
                    resultDiv.style.display = 'none';
                }, 5000);
                
            } catch (error) {
                alert('Errore durante la validazione: ' + error.message);
            }
        });
        
        // Format XML
        document.getElementById('formatBtn').addEventListener('click', function() {
            const formatted = formatXml(editor.getValue());
            editor.setValue(formatted);
        });
        
        // Refresh preview
        document.getElementById('refreshPreviewBtn').addEventListener('click', async function() {
            const xmlContent = editor.getValue();
            const entityType = document.getElementById('entityTypeSelect').value;
            const previewFrame = document.getElementById('previewFrame');
            const previewError = document.getElementById('previewError');
            
            previewFrame.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Generazione anteprima...</p></div>';
            previewError.style.display = 'none';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=preview&xml_content=' + encodeURIComponent(xmlContent) + 
                          '&entity_type=' + encodeURIComponent(entityType)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Add CSS to preview
                    const styleTag = result.css ? '<style>' + result.css + '</style>' : '';
                    previewFrame.innerHTML = styleTag + result.html;
                } else {
                    previewError.textContent = 'Errore: ' + result.error;
                    previewError.style.display = 'block';
                    previewFrame.innerHTML = '<div class="text-center text-danger"><i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i><p>Impossibile generare anteprima</p></div>';
                }
                
            } catch (error) {
                previewError.textContent = 'Errore: ' + error.message;
                previewError.style.display = 'block';
                previewFrame.innerHTML = '<div class="text-center text-danger"><i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i><p>Errore di connessione</p></div>';
            }
        });
        
        // Show help
        document.getElementById('helpBtn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('helpModal'));
            modal.show();
        });
        
        // XML formatter
        function formatXml(xml) {
            const PADDING = ' '.repeat(4);
            const reg = /(>)(<)(\/*)/g;
            let formatted = '';
            let pad = 0;
            
            xml = xml.replace(reg, '$1\r\n$2$3');
            const lines = xml.split('\r\n');
            
            for (let line of lines) {
                let indent = 0;
                if (line.match(/.+<\/\w[^>]*>$/)) {
                    indent = 0;
                } else if (line.match(/^<\/\w/)) {
                    if (pad !== 0) {
                        pad -= 1;
                    }
                } else if (line.match(/^<\w([^>]*[^\/])?>.*$/)) {
                    indent = 1;
                } else {
                    indent = 0;
                }
                
                formatted += PADDING.repeat(pad) + line + '\r\n';
                pad += indent;
            }
            
            return formatted;
        }
        
        // Auto-refresh on entity type change
        document.getElementById('entityTypeSelect').addEventListener('change', function() {
            // Could trigger auto-refresh here
        });
    </script>
</body>
</html>
