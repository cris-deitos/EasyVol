<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();
use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Models\Member;
use EasyVol\Middleware\CsrfProtection;
$app = App::getInstance();
if (!$app->isLoggedIn()) { header('Location: login.php'); exit; }
if (!$app->checkPermission('members', 'edit')) { die('Accesso negato'); }

// Log page access
AutoLogger::logPageAccess();
$db = $app->getDb();
$memberModel = new Member($db);
$memberId = intval($_GET['member_id'] ?? 0);
if ($memberId <= 0) { header('Location: members.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token non valido';
    } else {
        // Convert empty dates to null
        $issueDate = !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        $data = [
            'license_type' => trim($_POST['license_type'] ?? ''),
            'license_number' => trim($_POST['license_number'] ?? ''),
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate
        ];
        try {
            $memberModel->addLicense($memberId, $data);
            header('Location: member_view.php?id=' . $memberId . '&tab=licenses&success=1');
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
    <title>Patente - EasyVol</title>
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
                        <a href="member_view.php?id=<?php echo $memberId; ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
                        Aggiungi Patente
                    </h1>
                </div>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <?php echo CsrfProtection::getHiddenField(); ?>
                            <div class="mb-3">
                                <label for="license_type" class="form-label">Tipo Patente *</label>
                                <input type="text" class="form-control" id="license_type" name="license_type" placeholder="es: A, B, C, D, E, nautica, muletto" required>
                            </div>
                            <div class="mb-3">
                                <label for="license_number" class="form-label">Numero Patente</label>
                                <input type="text" class="form-control" id="license_number" name="license_number">
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="issue_date" class="form-label">Data Rilascio (opzionale)</label>
                                    <input type="date" class="form-control" id="issue_date" name="issue_date">
                                </div>
                                <div class="col-md-6">
                                    <label for="expiry_date" class="form-label">Data Scadenza (opzionale)</label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                </div>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="member_view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Annulla</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Salva</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Force uppercase on text fields
        document.addEventListener('DOMContentLoaded', function() {
            const uppercaseFields = ['license_type'];
            
            uppercaseFields.forEach(function(fieldName) {
                const field = document.getElementById(fieldName);
                if (field) {
                    field.addEventListener('input', function() {
                        const start = this.selectionStart;
                        const end = this.selectionEnd;
                        this.value = this.value.toUpperCase();
                        this.setSelectionRange(start, end);
                    });
                }
            });
        });
    </script>
</body>
</html>
