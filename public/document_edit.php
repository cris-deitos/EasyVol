<?php
/**
 * Gestione Documenti - Carica/Modifica
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\DocumentController;
use EasyVol\Utils\FileUploader;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new DocumentController($db, $config);
$csrf = new CsrfProtection();

$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $documentId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('documents', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('documents', 'create')) {
    die('Accesso negato');
}

$document = null;
$errors = [];
$success = false;

if ($isEdit) {
    $document = $controller->get($documentId);
    if (!$document) {
        header('Location: documents.php?error=not_found');
        exit;
    }
}

// Ottieni categorie esistenti per il dropdown
$categories = $controller->getCategories();

// Gestione form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'category' => trim($_POST['category'] ?? ''),
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'tags' => trim($_POST['tags'] ?? '')
        ];
        
        // Validazione base
        if (empty($data['category'])) {
            $errors[] = 'La categoria è obbligatoria';
        }
        
        if (empty($data['title'])) {
            $errors[] = 'Il titolo è obbligatorio';
        }
        
        // Gestione upload file (solo per nuovo documento)
        if (!$isEdit && isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            // Allowed MIME types for documents
            $allowedMimeTypes = array_merge(
                FileUploader::getDocumentMimeTypes(),
                FileUploader::getImageMimeTypes(),
                ['application/zip', 'application/x-rar-compressed', 'application/x-zip-compressed']
            );
            
            $uploader = new FileUploader(__DIR__ . '/../uploads/documents/', $allowedMimeTypes, 50 * 1024 * 1024);
            
            $uploadResult = $uploader->upload($_FILES['document_file']);
            
            if ($uploadResult['success']) {
                // Get MIME type from finfo for better reliability
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($uploadResult['path']);
                
                $data['file_name'] = basename($_FILES['document_file']['name']);
                $data['file_path'] = $uploadResult['path'];
                $data['file_size'] = $_FILES['document_file']['size'];
                $data['mime_type'] = $mimeType;
            } else {
                $errors[] = 'Errore upload file: ' . $uploadResult['error'];
            }
        } elseif (!$isEdit) {
            $errors[] = 'Il file è obbligatorio';
        }
        
        if (empty($errors)) {
            $userId = $app->getUserId();
            
            if ($isEdit) {
                // Aggiorna solo metadati
                $result = $controller->update($documentId, $data, $userId);
            } else {
                // Crea nuovo documento
                $result = $controller->create($data, $userId);
                if ($result) {
                    $documentId = $result;
                }
            }
            
            if ($result) {
                $success = true;
                header('Location: document_view.php?id=' . $documentId . '&success=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio del documento';
            }
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Documento' : 'Carica Documento';
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
                        <a href="<?php echo $isEdit ? 'document_view.php?id=' . $documentId : 'documents.php'; ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Errori:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Documento salvato con successo!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($isEdit): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Nota:</strong> In modalità modifica è possibile aggiornare solo i metadati del documento (titolo, categoria, descrizione, tag). 
                        Il file non può essere sostituito. Per caricare un nuovo file, eliminare questo documento e caricarne uno nuovo.
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf->generateToken(); ?>">
                            
                            <?php if (!$isEdit): ?>
                                <div class="mb-4 p-4 border rounded bg-light">
                                    <label for="document_file" class="form-label">
                                        <i class="bi bi-cloud-upload"></i> <strong>File Documento *</strong>
                                    </label>
                                    <input type="file" class="form-control" id="document_file" name="document_file" required>
                                    <div class="form-text">
                                        Formati supportati: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, JPG, PNG, GIF, ZIP, RAR. 
                                        Dimensione massima: 50 MB
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Titolo Documento *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($document['title'] ?? $_POST['title'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Categoria *</label>
                                    <input type="text" class="form-control" id="category" name="category" 
                                           value="<?php echo htmlspecialchars($document['category'] ?? $_POST['category'] ?? ''); ?>" 
                                           list="categoryList" required>
                                    <datalist id="categoryList">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                        <?php endforeach; ?>
                                        <option value="Normative">
                                        <option value="Procedure">
                                        <option value="Manuali">
                                        <option value="Verbali">
                                        <option value="Convenzioni">
                                        <option value="Altro">
                                    </datalist>
                                    <div class="form-text">Seleziona una categoria esistente o inseriscine una nuova</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="tags" class="form-label">Tag</label>
                                    <input type="text" class="form-control" id="tags" name="tags" 
                                           value="<?php echo htmlspecialchars($document['tags'] ?? $_POST['tags'] ?? ''); ?>" 
                                           placeholder="tag1, tag2, tag3">
                                    <div class="form-text">Inserisci i tag separati da virgola</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($document['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aggiorna Documento' : 'Carica Documento'; ?>
                                </button>
                                <a href="<?php echo $isEdit ? 'document_view.php?id=' . $documentId : 'documents.php'; ?>" 
                                   class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annulla
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
