<?php
/**
 * Upload Junior Member Attachment
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Models\JuniorMember;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('junior_members', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$memberModel = new JuniorMember($db);

$memberId = intval($_GET['member_id'] ?? 0);

if ($memberId <= 0) {
    header('Location: junior_members.php');
    exit;
}

$member = $memberModel->getById($memberId);
if (!$member) {
    header('Location: junior_members.php?error=not_found');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Nessun file selezionato';
        } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Errore durante il caricamento del file';
        } else {
            $file = $_FILES['file'];
            $description = trim($_POST['description'] ?? '');
            
            // Validate file
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                $errors[] = 'Il file supera la dimensione massima di 10MB';
            }
            
            // Allowed file types
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                           'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                           'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'Tipo di file non supportato. Sono ammessi solo immagini, PDF e documenti Office.';
            }
            
            if (empty($errors)) {
                try {
                    // Create upload directory if it doesn't exist
                    $uploadDir = __DIR__ . '/../uploads/junior_members/' . $memberId;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . '/' . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $data = [
                            'file_name' => $file['name'],
                            'file_path' => 'uploads/junior_members/' . $memberId . '/' . $filename,
                            'file_type' => $mimeType,
                            'description' => $description
                        ];
                        
                        $memberModel->addAttachment($memberId, $data);
                        
                        header('Location: junior_member_view.php?id=' . $memberId . '&tab=attachments&success=1');
                        exit;
                    } else {
                        $errors[] = 'Errore durante il salvataggio del file';
                    }
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carica Documento - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <a href="junior_member_view.php?id=<?php echo $memberId; ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        Carica Documento
                    </h1>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    Socio Minorenne: <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                </h5>
                                
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="file" class="form-label">File *</label>
                                        <input type="file" class="form-control" id="file" name="file" required>
                                        <div class="form-text">
                                            Formati supportati: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX (max 10MB)
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descrizione</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-upload"></i> Carica
                                        </button>
                                        <a href="junior_member_view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary">
                                            <i class="bi bi-x"></i> Annulla
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
