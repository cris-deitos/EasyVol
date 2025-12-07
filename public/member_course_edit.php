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
$memberId = intval($_GET['member_id'] ?? 0);
if ($memberId <= 0) { header('Location: members.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token non valido';
    } else {
        $data = [
            'course_name' => trim($_POST['course_name'] ?? ''),
            'course_type' => trim($_POST['course_type'] ?? ''),
            'completion_date' => $_POST['completion_date'] ?? null,
            'expiry_date' => $_POST['expiry_date'] ?? null
        ];
        try {
            $memberModel->addCourse($memberId, $data);
            header('Location: member_view.php?id=' . $memberId . '&success=1');
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
    <title>Corso - EasyVol</title>
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
                        Aggiungi Corso
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
                                <label for="course_name" class="form-label">Nome Corso *</label>
                                <input type="text" class="form-control" id="course_name" name="course_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="course_type" class="form-label">Tipo Corso</label>
                                <input type="text" class="form-control" id="course_type" name="course_type" placeholder="es: base, DGR 1190/2019, altro">
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="completion_date" class="form-label">Data Completamento</label>
                                    <input type="date" class="form-control" id="completion_date" name="completion_date">
                                </div>
                                <div class="col-md-6">
                                    <label for="expiry_date" class="form-label">Data Scadenza</label>
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
</body>
</html>
