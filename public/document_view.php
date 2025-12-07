<?php
/**
 * Gestione Documenti - Visualizzazione Dettaglio
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\DocumentController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('documents', 'view')) {
    die('Accesso negato');
}

$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($documentId <= 0) {
    header('Location: documents.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new DocumentController($db, $config);

$document = $controller->get($documentId);

if (!$document) {
    header('Location: documents.php?error=not_found');
    exit;
}

// Formatta dimensione file
$fileSize = '';
if ($document['file_size']) {
    $size = $document['file_size'];
    if ($size < 1024) {
        $fileSize = $size . ' B';
    } elseif ($size < 1048576) {
        $fileSize = round($size / 1024, 2) . ' KB';
    } else {
        $fileSize = round($size / 1048576, 2) . ' MB';
    }
}

$pageTitle = 'Documento: ' . $document['title'];
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
                        <a href="documents.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <i class="bi bi-file-earmark-text text-primary"></i>
                        <?php echo htmlspecialchars($document['title']); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="document_download.php?id=<?php echo $document['id']; ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Download
                            </a>
                            <?php if ($app->checkPermission('documents', 'edit')): ?>
                                <a href="document_edit.php?id=<?php echo $document['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <?php if ($app->checkPermission('documents', 'delete')): ?>
                                <button type="button" class="btn btn-danger" onclick="deleteDocument()">
                                    <i class="bi bi-trash"></i> Elimina
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Informazioni Documento</h5>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-3">Titolo:</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($document['title']); ?></dd>
                                    
                                    <dt class="col-sm-3">Categoria:</dt>
                                    <dd class="col-sm-9">
                                        <span class="badge bg-info"><?php echo htmlspecialchars($document['category']); ?></span>
                                    </dd>
                                    
                                    <?php if ($document['description']): ?>
                                        <dt class="col-sm-3">Descrizione:</dt>
                                        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($document['description'])); ?></dd>
                                    <?php endif; ?>
                                    
                                    <?php if ($document['tags']): ?>
                                        <dt class="col-sm-3">Tag:</dt>
                                        <dd class="col-sm-9">
                                            <?php 
                                            $tags = explode(',', $document['tags']);
                                            foreach ($tags as $tag): 
                                                $tag = trim($tag);
                                                if ($tag):
                                            ?>
                                                <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </dd>
                                    <?php endif; ?>
                                    
                                    <dt class="col-sm-3">Nome File:</dt>
                                    <dd class="col-sm-9"><code><?php echo htmlspecialchars($document['file_name']); ?></code></dd>
                                    
                                    <dt class="col-sm-3">Tipo File:</dt>
                                    <dd class="col-sm-9">
                                        <?php 
                                        $ext = strtoupper(pathinfo($document['file_name'], PATHINFO_EXTENSION));
                                        echo htmlspecialchars($ext);
                                        if ($document['mime_type']) {
                                            echo ' (' . htmlspecialchars($document['mime_type']) . ')';
                                        }
                                        ?>
                                    </dd>
                                    
                                    <dt class="col-sm-3">Dimensione:</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($fileSize); ?></dd>
                                    
                                    <dt class="col-sm-3">Caricato il:</dt>
                                    <dd class="col-sm-9"><?php echo date('d/m/Y H:i', strtotime($document['uploaded_at'])); ?></dd>
                                    
                                    <?php if ($document['uploaded_by_username']): ?>
                                        <dt class="col-sm-3">Caricato da:</dt>
                                        <dd class="col-sm-9"><?php echo htmlspecialchars($document['uploaded_by_username']); ?></dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                        
                        <!-- Anteprima (se possibile) -->
                        <?php 
                        $ext = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
                        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        $pdfExts = ['pdf'];
                        
                        if (in_array($ext, $imageExts)): 
                        ?>
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Anteprima</h5>
                                </div>
                                <div class="card-body text-center">
                                    <img src="../<?php echo htmlspecialchars($document['file_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($document['title']); ?>" 
                                         class="img-fluid" style="max-height: 600px;">
                                </div>
                            </div>
                        <?php elseif (in_array($ext, $pdfExts)): ?>
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Anteprima PDF</h5>
                                </div>
                                <div class="card-body">
                                    <iframe src="../<?php echo htmlspecialchars($document['file_path']); ?>" 
                                            width="100%" height="600px" style="border: none;">
                                    </iframe>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Azioni Rapide</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="document_download.php?id=<?php echo $document['id']; ?>" 
                                       class="btn btn-success">
                                        <i class="bi bi-download"></i> Download Documento
                                    </a>
                                    <button type="button" class="btn btn-info" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Stampa Informazioni
                                    </button>
                                    <a href="documents.php?category=<?php echo urlencode($document['category']); ?>" 
                                       class="btn btn-outline-secondary">
                                        <i class="bi bi-folder"></i> Vedi Categoria
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Informazioni Tecniche</h5>
                            </div>
                            <div class="card-body">
                                <small class="text-muted">
                                    <strong>ID Documento:</strong> <?php echo $document['id']; ?><br>
                                    <strong>Percorso:</strong> <code><?php echo htmlspecialchars($document['file_path']); ?></code><br>
                                    <?php if ($document['mime_type']): ?>
                                        <strong>MIME Type:</strong> <?php echo htmlspecialchars($document['mime_type']); ?><br>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteDocument() {
            if (confirm('Sei sicuro di voler eliminare questo documento? Il file verrà eliminato definitivamente.')) {
                window.location.href = 'document_delete.php?id=<?php echo $document['id']; ?>';
            }
        }
    </script>
</body>
</html>
