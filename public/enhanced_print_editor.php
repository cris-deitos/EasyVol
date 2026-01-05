<?php
/**
 * Enhanced Print Editor
 * 
 * WYSIWYG editor for document modification before printing
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

$pageTitle = 'Editor Documento';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body {
            background: #f5f5f5;
        }
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 15px;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .editor-container {
            margin-top: 80px;
            padding: 20px;
        }
        .editor-wrapper {
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 21cm;
            margin: 0 auto;
        }
        @media print {
            .toolbar {
                display: none;
            }
            .editor-container {
                margin-top: 0;
                padding: 0;
            }
            .editor-wrapper {
                box-shadow: none;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-pencil-square"></i>
                    <?php echo htmlspecialchars($pageTitle); ?>
                </h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" onclick="saveToPDF()">
                        <i class="bi bi-file-pdf"></i> Salva come PDF
                    </button>
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="bi bi-printer"></i> Stampa
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <i class="bi bi-x-circle"></i> Chiudi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="editor-container">
        <div class="editor-wrapper">
            <div id="loadingMessage" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Caricamento...</span>
                </div>
                <p class="mt-3">Caricamento documento...</p>
            </div>
            <textarea id="editor"></textarea>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Load document content first
        const urlParams = new URLSearchParams(window.location.search);
        const generateUrl = 'enhanced_print_generate.php?' + urlParams.toString();
        
        fetch(generateUrl)
            .then(response => response.text())
            .then(html => {
                // Extract body content
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const bodyContent = doc.body.innerHTML;
                
                // Hide loading message
                document.getElementById('loadingMessage').style.display = 'none';
                
                // Initialize TinyMCE with the content
                tinymce.init({
                    selector: '#editor',
                    height: 800,
                    menubar: true,
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                        'insertdatetime', 'media', 'table', 'help', 'wordcount', 'print'
                    ],
                    toolbar: 'undo redo | formatselect | bold italic underline strikethrough | ' +
                             'alignleft aligncenter alignright alignjustify | ' +
                             'bullist numlist outdent indent | removeformat | table | ' +
                             'forecolor backcolor | fontsize | print preview',
                    content_style: 'body { font-family: Arial, sans-serif; font-size: 10pt; margin: 1cm; }',
                    init_instance_callback: function(editor) {
                        editor.setContent(bodyContent);
                    },
                    setup: function(editor) {
                        editor.on('init', function() {
                            console.log('Editor initialized');
                        });
                    }
                });
            })
            .catch(error => {
                console.error('Error loading document:', error);
                document.getElementById('loadingMessage').innerHTML = 
                    '<div class="alert alert-danger">Errore nel caricamento del documento: ' + error.message + '</div>';
            });

        function saveToPDF() {
            // Get content from TinyMCE
            const content = tinymce.get('editor').getContent();
            
            // Create a temporary container
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            tempDiv.style.position = 'absolute';
            tempDiv.style.left = '-9999px';
            tempDiv.style.width = '21cm';
            tempDiv.style.padding = '1cm';
            tempDiv.style.background = 'white';
            document.body.appendChild(tempDiv);
            
            // Generate PDF using jsPDF
            html2canvas(tempDiv, {
                scale: 2,
                useCORS: true,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                
                // A4 size in mm
                const pdfWidth = 210;
                const pdfHeight = 297;
                
                const imgWidth = pdfWidth;
                const imgHeight = (canvas.height * pdfWidth) / canvas.width;
                
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                let heightLeft = imgHeight;
                let position = 0;
                
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pdfHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pdfHeight;
                }
                
                pdf.save('documento_' + new Date().getTime() + '.pdf');
                
                // Remove temporary container
                document.body.removeChild(tempDiv);
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('Errore durante la generazione del PDF');
                document.body.removeChild(tempDiv);
            });
        }
    </script>
</body>
</html>
