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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Qualifica - EasyVol</title>
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
                        <?php echo $roleId > 0 ? 'Modifica' : 'Aggiungi'; ?> Mansione/Qualifica
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
                                <label for="role_name" class="form-label">Mansione/Qualifica *</label>
                                <select class="form-select" id="role_name" name="role_name" required>
                                    <option value="">-- Seleziona Mansione --</option>
                                    <option value="OPERATORE GENERICO" <?php echo $role['role_name'] === 'OPERATORE GENERICO' ? 'selected' : ''; ?>>OPERATORE GENERICO</option>
                                    <option value="PRESIDENTE" <?php echo $role['role_name'] === 'PRESIDENTE' ? 'selected' : ''; ?>>PRESIDENTE</option>
                                    <option value="VICE PRESIDENTE" <?php echo $role['role_name'] === 'VICE PRESIDENTE' ? 'selected' : ''; ?>>VICE PRESIDENTE</option>
                                    <option value="CAPOSQUADRA" <?php echo $role['role_name'] === 'CAPOSQUADRA' ? 'selected' : ''; ?>>CAPOSQUADRA</option>
                                    <option value="RESPONSABILE RAPPORTI ISTITUZIONALI E STAMPA" <?php echo $role['role_name'] === 'RESPONSABILE RAPPORTI ISTITUZIONALI E STAMPA' ? 'selected' : ''; ?>>RESPONSABILE RAPPORTI ISTITUZIONALI E STAMPA</option>
                                    <option value="RESPONSABILE NUCLEO TLC RADIO" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO TLC RADIO' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO TLC RADIO</option>
                                    <option value="RESPONSABILE NUCLEO GIS/GPS" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO GIS/GPS' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO GIS/GPS</option>
                                    <option value="RESPONSABILE NUCLEO SEGRETERIA OPERATIVA" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO SEGRETERIA OPERATIVA' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO SEGRETERIA OPERATIVA</option>
                                    <option value="RESPONSABILE NUCLEO DRONE" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO DRONE' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO DRONE</option>
                                    <option value="RESPONSABILE NUCLEO RICERCA E SOCCORSO" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO RICERCA E SOCCORSO' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO RICERCA E SOCCORSO</option>
                                    <option value="RESPONSABILE NUCLEO NAUTICO" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO NAUTICO' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO NAUTICO</option>
                                    <option value="RESPONSABILE NUCLEO SOMMOZZATORI" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO SOMMOZZATORI' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO SOMMOZZATORI</option>
                                    <option value="RESPONSABILE NUCLEO IDROGEOLOGICO" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO IDROGEOLOGICO' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO IDROGEOLOGICO</option>
                                    <option value="RESPONSABILE NUCLEO LOGISTICO" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO LOGISTICO' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO LOGISTICO</option>
                                    <option value="RESPONSABILE NUCLEO CINOFILI" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO CINOFILI' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO CINOFILI</option>
                                    <option value="RESPONSABILE NUCLEO A CAVALLO" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO A CAVALLO' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO A CAVALLO</option>
                                    <option value="RESPONSABILE NUCLEO CUCINA E MENSA" <?php echo $role['role_name'] === 'RESPONSABILE NUCLEO CUCINA E MENSA' ? 'selected' : ''; ?>>RESPONSABILE NUCLEO CUCINA E MENSA</option>
                                    <option value="OPERATORE SEGRETERIA" <?php echo $role['role_name'] === 'OPERATORE SEGRETERIA' ? 'selected' : ''; ?>>OPERATORE SEGRETERIA</option>
                                    <option value="OPERATORE TLC RADIO" <?php echo $role['role_name'] === 'OPERATORE TLC RADIO' ? 'selected' : ''; ?>>OPERATORE TLC RADIO</option>
                                    <option value="OPERATORE GIS/GPS" <?php echo $role['role_name'] === 'OPERATORE GIS/GPS' ? 'selected' : ''; ?>>OPERATORE GIS/GPS</option>
                                    <option value="OPERATORE DRONE" <?php echo $role['role_name'] === 'OPERATORE DRONE' ? 'selected' : ''; ?>>OPERATORE DRONE</option>
                                    <option value="OPERATORE CUCINA" <?php echo $role['role_name'] === 'OPERATORE CUCINA' ? 'selected' : ''; ?>>OPERATORE CUCINA</option>
                                    <option value="OPERATORE LOGISTICO" <?php echo $role['role_name'] === 'OPERATORE LOGISTICO' ? 'selected' : ''; ?>>OPERATORE LOGISTICO</option>
                                    <option value="OPERATORE IDROGEOLOGICO" <?php echo $role['role_name'] === 'OPERATORE IDROGEOLOGICO' ? 'selected' : ''; ?>>OPERATORE IDROGEOLOGICO</option>
                                    <option value="OPERATORE MENSA" <?php echo $role['role_name'] === 'OPERATORE MENSA' ? 'selected' : ''; ?>>OPERATORE MENSA</option>
                                    <option value="OPERATORE SOMMOZZATORE" <?php echo $role['role_name'] === 'OPERATORE SOMMOZZATORE' ? 'selected' : ''; ?>>OPERATORE SOMMOZZATORE</option>
                                    <option value="OPERATORE NAUTICO" <?php echo $role['role_name'] === 'OPERATORE NAUTICO' ? 'selected' : ''; ?>>OPERATORE NAUTICO</option>
                                    <option value="OPERATORE CINOFILO" <?php echo $role['role_name'] === 'OPERATORE CINOFILO' ? 'selected' : ''; ?>>OPERATORE CINOFILO</option>
                                    <option value="OPERATORE A CAVALLO" <?php echo $role['role_name'] === 'OPERATORE A CAVALLO' ? 'selected' : ''; ?>>OPERATORE A CAVALLO</option>
                                    <option value="OPERATORE FOTO REPORTER" <?php echo $role['role_name'] === 'OPERATORE FOTO REPORTER' ? 'selected' : ''; ?>>OPERATORE FOTO REPORTER</option>
                                    <option value="AUTISTA A" <?php echo $role['role_name'] === 'AUTISTA A' ? 'selected' : ''; ?>>AUTISTA A</option>
                                    <option value="AUTISTA B" <?php echo $role['role_name'] === 'AUTISTA B' ? 'selected' : ''; ?>>AUTISTA B</option>
                                    <option value="AUTISTA C" <?php echo $role['role_name'] === 'AUTISTA C' ? 'selected' : ''; ?>>AUTISTA C</option>
                                    <option value="AUTISTA D" <?php echo $role['role_name'] === 'AUTISTA D' ? 'selected' : ''; ?>>AUTISTA D</option>
                                    <option value="AUTISTA E" <?php echo $role['role_name'] === 'AUTISTA E' ? 'selected' : ''; ?>>AUTISTA E</option>
                                    <option value="PILOTA NATANTE" <?php echo $role['role_name'] === 'PILOTA NATANTE' ? 'selected' : ''; ?>>PILOTA NATANTE</option>
                                    <option value="NON OPERATIVO" <?php echo $role['role_name'] === 'NON OPERATIVO' ? 'selected' : ''; ?>>NON OPERATIVO</option>
                                </select>
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
