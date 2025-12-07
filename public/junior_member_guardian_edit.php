<?php
/**
 * Add/Edit Junior Member Guardian
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
        $data = [
            'guardian_type' => $_POST['guardian_type'] ?? 'padre',
            'last_name' => trim($_POST['last_name'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'tax_code' => strtoupper(trim($_POST['tax_code'] ?? '')),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? '')
        ];
        
        try {
            $memberModel->addGuardian($memberId, $data);
            header('Location: junior_member_view.php?id=' . $memberId . '&tab=guardians&success=1');
            exit;
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Tutore/Genitore - EasyVol</title>
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
                        Aggiungi Tutore/Genitore
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <?php echo CsrfProtection::getHiddenField(); ?>
                            
                            <div class="mb-3">
                                <label for="guardian_type" class="form-label">Tipo *</label>
                                <select class="form-select" id="guardian_type" name="guardian_type" required>
                                    <option value="padre">Padre</option>
                                    <option value="madre">Madre</option>
                                    <option value="tutore">Tutore</option>
                                </select>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Cognome *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">Nome *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tax_code" class="form-label">Codice Fiscale</label>
                                <input type="text" class="form-control text-uppercase" id="tax_code" name="tax_code" maxlength="16">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Telefono</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="junior_member_view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annulla
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salva
                                </button>
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
