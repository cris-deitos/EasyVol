<?php
/**
 * Print Preview
 * 
 * Anteprima documento prima della stampa
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

$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';

if ($isPreview) {
    // Preview from editor (using session storage)
    $pageTitle = 'Anteprima Template';
} else {
    // Preview from generation
    $templateId = $_GET['template_id'] ?? null;
    $recordId = $_GET['record_id'] ?? null;
    
    if (!$templateId) {
        die('Template ID richiesto');
    }
    
    $pageTitle = 'Anteprima Stampa';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f5f5f5; }
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .preview-container {
            margin-top: 80px;
            padding: 2rem;
        }
        .preview-page {
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin: 0 auto 2rem;
            padding: 2cm;
            max-width: 21cm; /* A4 width */
        }
        .preview-page.landscape {
            max-width: 29.7cm; /* A4 landscape width */
        }
        .document-header {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        .document-footer {
            border-top: 2px solid #dee2e6;
            padding-top: 1rem;
            margin-top: 2rem;
        }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .preview-container { margin-top: 0; padding: 0; }
            .preview-page { 
                box-shadow: none; 
                margin: 0; 
                padding: 0;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h5>
                <div class="btn-group">
                    <?php if (!$isPreview): ?>
                        <button type="button" class="btn btn-warning" onclick="editBeforePrint()">
                            <i class="bi bi-pencil"></i> Modifica
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Stampa
                    </button>
                    <button type="button" class="btn btn-success" onclick="downloadPDF()">
                        <i class="bi bi-file-pdf"></i> Download PDF
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <i class="bi bi-x-circle"></i> Chiudi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="preview-container">
        <div id="previewContent" class="preview-page">
            <?php if ($isPreview): ?>
                <!-- Content will be loaded from sessionStorage -->
                <div class="text-center text-muted p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-3">Caricamento anteprima...</p>
                </div>
            <?php else: ?>
                <!-- Content will be loaded via AJAX -->
                <div class="text-center text-muted p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-3">Generazione documento...</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        <?php if ($isPreview): ?>
            // Load preview from sessionStorage
            document.addEventListener('DOMContentLoaded', function() {
                const html = sessionStorage.getItem('preview_html');
                const css = sessionStorage.getItem('preview_css');
                const header = sessionStorage.getItem('preview_header');
                const footer = sessionStorage.getItem('preview_footer');
                
                if (html) {
                    const previewContent = document.getElementById('previewContent');
                    
                    // Build full content with header and footer
                    // Note: Content is already coming from sessionStorage which was set by the editor
                    // and will be rendered in the DOM. The browser will handle HTML parsing.
                    let fullContent = '';
                    if (header) {
                        const headerDiv = document.createElement('div');
                        headerDiv.className = 'document-header';
                        headerDiv.innerHTML = header;
                        previewContent.appendChild(headerDiv);
                    }
                    
                    const contentDiv = document.createElement('div');
                    contentDiv.innerHTML = html;
                    previewContent.appendChild(contentDiv);
                    
                    if (footer) {
                        const footerDiv = document.createElement('div');
                        footerDiv.className = 'document-footer';
                        footerDiv.innerHTML = footer;
                        previewContent.appendChild(footerDiv);
                    }
                    
                    if (css) {
                        const style = document.createElement('style');
                        style.textContent = css;
                        document.head.appendChild(style);
                    }
                } else {
                    document.getElementById('previewContent').innerHTML = 
                        '<div class="alert alert-warning">Nessun contenuto da visualizzare in anteprima</div>';
                }
            });
        <?php else: ?>
            // Load preview via AJAX
            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const generateUrl = 'print_generate.php?' + urlParams.toString();
                
                fetch(generateUrl)
                    .then(response => response.text())
                    .then(html => {
                        // Extract entire document content from response
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Get all content from body (including header and footer)
                        const bodyContent = doc.body.innerHTML;
                        
                        // Get styles from head
                        const styles = doc.querySelectorAll('style');
                        styles.forEach(style => {
                            document.head.appendChild(style.cloneNode(true));
                        });
                        
                        // Set content
                        document.getElementById('previewContent').innerHTML = bodyContent;
                    })
                    .catch(error => {
                        console.error('Error loading preview:', error);
                        document.getElementById('previewContent').innerHTML = 
                            '<div class="alert alert-danger">Errore nel caricamento dell\'anteprima: ' + error.message + '</div>';
                    });
            });
        <?php endif; ?>

        function editBeforePrint() {
            const urlParams = new URLSearchParams(window.location.search);
            const editUrl = 'print_edit.php?' + urlParams.toString();
            window.location.href = editUrl;
        }

        function downloadPDF() {
            const element = document.getElementById('previewContent');
            const opt = {
                margin: [1, 1, 1, 1],
                filename: 'documento_' + new Date().getTime() + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: false
                },
                jsPDF: { unit: 'cm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };
            
            html2pdf().set(opt).from(element).save().catch(error => {
                console.error('Error generating PDF:', error);
                alert('Errore durante la generazione del PDF: ' + error.message);
            });
        }
    </script>
</body>
</html>
