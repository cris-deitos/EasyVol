<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();
use EasyVol\App;
use EasyVol\Models\Member;
use EasyVol\Middleware\CsrfProtection;
$app = App::getInstance();
if (!$app->isLoggedIn()) { header('Location: login.php'); exit; }
if (!$app->checkPermission('members', 'edit')) { die('Accesso negato'); }
$db = $app->getDb();
$memberModel = new Member($db);
$memberId = intval($_GET['junior_member_id'] ?? 0);
if ($memberId <= 0) { header('Location: members.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token non valido';
    } else {
        $data = [
            'health_type' => $_POST['health_type'] ?? '',
            'description' => trim($_POST['description'] ?? '')
        ];
        try {
            $memberModel->addHealth($memberId, $data);
            header('Location: junior_member_view.php?id=' . $memberId . '&success=1');
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
    <title>Informazione Sanitaria - EasyVol</title>
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
                        <a href="junior_member_view.php?id=<?php echo $memberId; ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
                        Aggiungi Informazione Sanitaria
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
                                <label for="health_type" class="form-label">Tipo *</label>
                                <select class="form-select" id="health_type" name="health_type" required>
                                    <option value="">Seleziona...</option>
                                    <option value="allergie">Allergie</option>
                                    <option value="intolleranze">Intolleranze</option>
                                    <option value="patologie">Patologie</option>
                                    <option value="vegano">Dieta Vegana</option>
                                    <option value="vegetariano">Dieta Vegetariana</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="junior_member_view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Annulla</a>
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
