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
$roleId = intval($_GET['id'] ?? 0);
if ($memberId <= 0) { header('Location: members.php'); exit; }
$errors = [];
$role = ['role_name' => '', 'assigned_date' => '', 'end_date' => ''];
// Load existing role if editing
if ($roleId > 0) {
    $roles = $memberModel->getRoles($memberId);
    foreach ($roles as $r) {
        if ($r['id'] == $roleId) {
            $role = $r;
            break;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token non valido';
    } else {
        $data = [
            'role_name' => trim($_POST['role_name'] ?? ''),
            'assigned_date' => !empty($_POST['assigned_date']) ? $_POST['assigned_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null
        ];
        try {
            if ($roleId > 0) {
                $memberModel->updateRole($roleId, $data);
            } else {
                $memberModel->addRole($memberId, $data);
            }
            header('Location: member_view.php?id=' . $memberId . '&tab=qualifications&success=1');
            exit;
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
// Load available qualifications from database
$qualifications = $db->fetchAll("SELECT name FROM member_qualification_types WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Mansione - EasyVol</title>
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
                        <?php echo $roleId > 0 ? 'Modifica' : 'Aggiungi'; ?> Mansione
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
                                <label for="role_name" class="form-label">Mansione *</label>
                                <select class="form-select" id="role_name" name="role_name" required>
                                    <option value="">-- Seleziona Mansione --</option>
                                    <?php foreach ($qualifications as $qualification): ?>
                                        <option value="<?php echo htmlspecialchars($qualification['name']); ?>" 
                                                <?php echo $role['role_name'] === $qualification['name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($qualification['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> 
                                    Puoi gestire le mansioni disponibili in <a href="settings.php?tab=qualifications" target="_blank">Impostazioni → Mansioni Soci</a>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="assigned_date" class="form-label">Data Assegnazione</label>
                                    <input type="date" class="form-control" id="assigned_date" name="assigned_date" value="<?php echo htmlspecialchars($role['assigned_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">Data Fine</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($role['end_date'] ?? ''); ?>">
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
</body>
</html>
