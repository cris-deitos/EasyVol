<?php
/**
 * Print Edit
 * 
 * Modifica documento prima della stampa
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$templateId = $_GET['template_id'] ?? null;
$recordId = $_GET['record_id'] ?? null;

if (!$templateId) {
    die('Template ID richiesto');
}

$pageTitle = 'Modifica Documento';
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
        .editor-container {
            margin-top: 80px;
            padding: 2rem;
        }
        .editor-page {
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin: 0 auto;
            padding: 2cm;
            max-width: 21cm; /* A4 width */
            min-height: 29.7cm; /* A4 height */
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" onclick="saveAndPreview()">
                        <i class="bi bi-eye"></i> Anteprima
                    </button>
                    <button type="button" class="btn btn-success" onclick="saveAndPrint()">
                        <i class="bi bi-printer"></i> Stampa
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <i class="bi bi-x-circle"></i> Annulla
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="editor-container">
        <div class="editor-page">
            <div id="editableContent" contenteditable="true">
                <div class="text-center text-muted p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-3">Caricamento documento...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load document content
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const generateUrl = 'print_generate.php?' + urlParams.toString();
            
            fetch(generateUrl)
                .then(response => response.text())
                .then(html => {
                    // Extract body content from response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const body = doc.body;
                    
                    // Get styles from head
                    const styles = doc.querySelectorAll('style');
                    styles.forEach(style => {
                        document.head.appendChild(style.cloneNode(true));
                    });
                    
                    // Set editable content
                    document.getElementById('editableContent').innerHTML = body.innerHTML;
                })
                .catch(error => {
                    console.error('Error loading document:', error);
                    document.getElementById('editableContent').innerHTML = 
                        '<div class="alert alert-danger">Errore nel caricamento del documento: ' + error.message + '</div>';
                });
        });

        function saveAndPreview() {
            const content = document.getElementById('editableContent').innerHTML;
            sessionStorage.setItem('edited_content', content);
            
            const urlParams = new URLSearchParams(window.location.search);
            window.open('print_preview.php?edited=1&' + urlParams.toString(), '_blank');
        }

        function saveAndPrint() {
            const content = document.getElementById('editableContent').innerHTML;
            
            // Create temporary print window
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html lang="it">');
            printWindow.document.write('<head>');
            printWindow.document.write('<meta charset="UTF-8">');
            printWindow.document.write('<title>Stampa</title>');
            
            // Copy styles
            const styles = document.querySelectorAll('style, link[rel="stylesheet"]');
            styles.forEach(style => {
                printWindow.document.write(style.outerHTML);
            });
            
            printWindow.document.write('</head>');
            printWindow.document.write('<body>');
            printWindow.document.write(content);
            printWindow.document.write('</body>');
            printWindow.document.write('</html>');
            printWindow.document.close();
            
            // Print after content is loaded
            printWindow.onload = function() {
                printWindow.print();
                // Optionally close after printing
                // printWindow.close();
            };
        }
    </script>
</body>
</html>
