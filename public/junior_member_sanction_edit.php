<?php
/**
 * Add/Edit Junior Member Sanction (Provvedimento)
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
$sanctionId = intval($_GET['id'] ?? 0);

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
$sanction = ['sanction_date' => date('Y-m-d'), 'sanction_type' => '', 'reason' => ''];

// Load existing sanction if editing
if ($sanctionId > 0) {
    $sanctions = $memberModel->getSanctions($memberId);
    foreach ($sanctions as $s) {
        if ($s['id'] == $sanctionId) {
            $sanction = $s;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'sanction_date' => $_POST['sanction_date'] ?? date('Y-m-d'),
            'sanction_type' => $_POST['sanction_type'] ?? '',
            'reason' => trim($_POST['reason'] ?? ''),
            'created_by' => $app->getUserId()
        ];
        
        // Validate
        $validTypes = ['decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'operativo'];
        if (!in_array($data['sanction_type'], $validTypes)) {
            $errors[] = 'Tipo di provvedimento non valido';
        }
        
        if (empty($errors)) {
            try {
                if ($sanctionId > 0) {
                    $memberModel->updateSanction($sanctionId, $data);
                } else {
                    $memberModel->addSanction($memberId, $data);
                }
                
                // Update member status based on sanction type with new logic
                $newStatus = $data['sanction_type'];
                
                // Special handling for operativo sanction
                if ($data['sanction_type'] === 'operativo') {
                    // Get all sanctions for this member ordered by date
                    $allSanctions = $memberModel->getSanctions($memberId);
                    
                    // Check if there's a previous suspending sanction
                    $hasPreviousSuspension = false;
                    $currentDate = strtotime($data['sanction_date']);
                    
                    foreach ($allSanctions as $s) {
                        $sanctionDate = strtotime($s['sanction_date']);
                        if ($sanctionDate < $currentDate && 
                            in_array($s['sanction_type'], ['in_aspettativa', 'sospeso', 'in_congedo'])) {
                            $hasPreviousSuspension = true;
                            break;
                        }
                    }
                    
                    // If operativo comes after a suspension, return to active status
                    if ($hasPreviousSuspension) {
                        $newStatus = 'attivo';
                    }
                }
                
                // Apply status consolidation logic
                // If in_aspettativa or in_congedo -> set status to sospeso (unless already set to attivo by operativo)
                if (in_array($data['sanction_type'], ['in_aspettativa', 'in_congedo']) && $newStatus === $data['sanction_type']) {
                    $newStatus = 'sospeso';
                }
                
                $memberModel->update($memberId, ['member_status' => $newStatus]);
                
                header('Location: junior_member_view.php?id=' . $memberId . 'header('Location: junior_member_view.php?id=' . $memberId . '&success=1');tab=sanctionsheader('Location: junior_member_view.php?id=' . $memberId . '&success=1');success=1');
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
    <title><?php echo $sanctionId > 0 ? 'Modifica' : 'Aggiungi'; ?> Provvedimento - EasyVol</title>
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
                        <?php echo $sanctionId > 0 ? 'Modifica' : 'Aggiungi'; ?> Provvedimento
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
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Attenzione:</strong> L'inserimento di un provvedimento cambierà automaticamente lo stato del socio.<br>
                                    <small>
                                        - <strong>In Aspettativa/In Congedo</strong>: imposta lo stato a "Sospeso"<br>
                                        - <strong>Operativo</strong>: se inserito DOPO un provvedimento sospensivo, riporta lo stato ad "Attivo"<br>
                                        - <strong>Decaduto/Dimesso</strong>: imposta lo stato rispettivo
                                    </small>
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo CsrfProtection::generateToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="sanction_date" class="form-label">Data Provvedimento *</label>
                                        <input type="date" class="form-control" id="sanction_date" name="sanction_date" 
                                               value="<?php echo htmlspecialchars($sanction['sanction_date']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sanction_type" class="form-label">Tipo Provvedimento *</label>
                                        <select class="form-select" id="sanction_type" name="sanction_type" required>
                                            <option value="">-- Seleziona Tipo --</option>
                                            <option value="decaduto" <?php echo $sanction['sanction_type'] === 'decaduto' ? 'selected' : ''; ?>>Decaduto</option>
                                            <option value="dimesso" <?php echo $sanction['sanction_type'] === 'dimesso' ? 'selected' : ''; ?>>Dimesso</option>
                                            <option value="in_aspettativa" <?php echo $sanction['sanction_type'] === 'in_aspettativa' ? 'selected' : ''; ?>>In Aspettativa</option>
                                            <option value="sospeso" <?php echo $sanction['sanction_type'] === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                                            <option value="in_congedo" <?php echo $sanction['sanction_type'] === 'in_congedo' ? 'selected' : ''; ?>>In Congedo</option>
                                            <option value="operativo" <?php echo $sanction['sanction_type'] === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="reason" class="form-label">Motivazione (facoltativa)</label>
                                        <textarea class="form-control" id="reason" name="reason" rows="4"><?php echo htmlspecialchars($sanction['reason']); ?></textarea>
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
