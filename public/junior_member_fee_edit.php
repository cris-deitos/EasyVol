<?php
/**
 * Add/Edit Junior Member Fee
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
$feeId = intval($_GET['id'] ?? 0);

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
$fee = ['year' => date('Y'), 'payment_date' => '', 'amount' => ''];

// Load existing fee if editing
if ($feeId > 0) {
    $fees = $memberModel->getFees($memberId);
    foreach ($fees as $f) {
        if ($f['id'] == $feeId) {
            $fee = $f;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'year' => intval($_POST['year'] ?? 0),
            'payment_date' => $_POST['payment_date'] ?? null,
            'amount' => !empty($_POST['amount']) ? floatval($_POST['amount']) : null
        ];
        
        // Validate
        if ($data['year'] < 1900 || $data['year'] > 2100) {
            $errors[] = 'Anno non valido';
        }
        
        if (empty($errors)) {
            try {
                if ($feeId > 0) {
                    $memberModel->updateFee($feeId, $data);
                } else {
                    $memberModel->addFee($memberId, $data);
                }
                
                header('Location: junior_member_view.php?id=' . $memberId . '&tab=fees&success=1');
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
    <title><?php echo $feeId > 0 ? 'Modifica' : 'Aggiungi'; ?> Quota Associativa - EasyVol</title>
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
                        <?php echo $feeId > 0 ? 'Modifica' : 'Aggiungi'; ?> Quota Associativa
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
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="year" class="form-label">Anno *</label>
                                        <input type="number" class="form-control" id="year" name="year" 
                                               value="<?php echo htmlspecialchars($fee['year']); ?>" 
                                               min="1900" max="2100" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="payment_date" class="form-label">Data Pagamento</label>
                                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                               value="<?php echo htmlspecialchars($fee['payment_date'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Importo (€)</label>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               value="<?php echo htmlspecialchars($fee['amount'] ?? ''); ?>" 
                                               step="0.01" min="0">
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Salva
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
