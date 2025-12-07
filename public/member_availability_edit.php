<?php
/**
 * Add/Edit Member Availability
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Models\Member;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('members', 'edit')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$memberModel = new Member($db);

$memberId = intval($_GET['member_id'] ?? 0);

if ($memberId <= 0) {
    header('Location: members.php');
    exit;
}

$member = $memberModel->getById($memberId);
if (!$member) {
    header('Location: members.php?error=not_found');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'availability_type' => $_POST['availability_type'] ?? ''
        ];
        
        // Validate
        $validTypes = ['comunale', 'provinciale', 'regionale', 'nazionale', 'internazionale'];
        if (!in_array($data['availability_type'], $validTypes)) {
            $errors[] = 'Tipo di disponibilità non valido';
        }
        
        if (empty($errors)) {
            try {
                $memberModel->addAvailability($memberId, $data);
                header('Location: member_view.php?id=' . $memberId . 'header('Location: member_view.php?id=' . $memberId . '&success=1');tab=availabilityheader('Location: member_view.php?id=' . $memberId . '&success=1');success=1');
                exit;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
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
    <title>Aggiungi Disponibilità - EasyVol</title>
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
                        <a href="member_view.php?id=<?php echo $memberId; ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        Aggiungi Disponibilità Territoriale
                    </h1>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    Socio: <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
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
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="availability_type" class="form-label">Tipo Disponibilità *</label>
                                        <select class="form-select" id="availability_type" name="availability_type" required>
                                            <option value="">-- Seleziona Tipo --</option>
                                            <option value="comunale">Comunale</option>
                                            <option value="provinciale">Provinciale</option>
                                            <option value="regionale">Regionale</option>
                                            <option value="nazionale">Nazionale</option>
                                            <option value="internazionale">Internazionale</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva
                                        </button>
                                        <a href="member_view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary">
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
