<?php
/**
 * Add/Edit Junior Member Address
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
$addressId = intval($_GET['id'] ?? 0);

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
            'address_type' => $_POST['address_type'] ?? 'residenza',
            'street' => trim($_POST['street'] ?? ''),
            'number' => trim($_POST['number'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'province' => trim($_POST['province'] ?? ''),
            'cap' => trim($_POST['cap'] ?? '')
        ];
        
        try {
            if ($addressId > 0) {
                $memberModel->updateAddress($addressId, $data);
            } else {
                $memberModel->addAddress($memberId, $data);
            }
            
            header('Location: junior_member_view.php?id=' . $memberId . '&tab=address&success=1');
            exit;
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$address = [];
if ($addressId > 0) {
    $addresses = $memberModel->getAddresses($memberId);
    foreach ($addresses as $addr) {
        if ($addr['id'] == $addressId) {
            $address = $addr;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indirizzo Socio Minorenne - EasyVol</title>
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
                        <?php echo $addressId > 0 ? 'Modifica' : 'Aggiungi'; ?> Indirizzo
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
                                <label for="address_type" class="form-label">Tipo Indirizzo *</label>
                                <select class="form-select" id="address_type" name="address_type" required>
                                    <option value="residenza" <?php echo ($address['address_type'] ?? '') === 'residenza' ? 'selected' : ''; ?>>Residenza</option>
                                    <option value="domicilio" <?php echo ($address['address_type'] ?? '') === 'domicilio' ? 'selected' : ''; ?>>Domicilio</option>
                                </select>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="street" class="form-label">Via *</label>
                                    <input type="text" class="form-control" id="street" name="street" 
                                           value="<?php echo htmlspecialchars($address['street'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="number" class="form-label">Numero *</label>
                                    <input type="text" class="form-control" id="number" name="number" 
                                           value="<?php echo htmlspecialchars($address['number'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="city" class="form-label">Città *</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($address['city'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="province" class="form-label">Provincia *</label>
                                    <input type="text" class="form-control text-uppercase" id="province" name="province" 
                                           value="<?php echo htmlspecialchars($address['province'] ?? ''); ?>" 
                                           maxlength="2" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="cap" class="form-label">CAP *</label>
                                    <input type="text" class="form-control" id="cap" name="cap" 
                                           value="<?php echo htmlspecialchars($address['cap'] ?? ''); ?>" 
                                           maxlength="5" required>
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
