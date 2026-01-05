<?php
/**
 * Add/Edit Member Sanction (Provvedimento)
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Models\Member;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Services\SanctionService;

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
$sanctionId = intval($_GET['id'] ?? 0);

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
        
        // Validate sanction type using SanctionService
        if (!SanctionService::isValidType($data['sanction_type'])) {
            $errors[] = 'Tipo di provvedimento non valido';
        }
        
        // Validate sanction date
        if (!SanctionService::isValidDate($data['sanction_date'])) {
            $errors[] = 'Data provvedimento non valida';
        }
        
        if (empty($errors)) {
            // Log sanction operation (non-sensitive data only)
            error_log("Adding/Updating sanction for member $memberId - Type: " . $data['sanction_type'] . ", Date: " . $data['sanction_date']);
            
            // Process sanction using SanctionService
            $result = SanctionService::processSanction($memberModel, $memberId, $sanctionId, $data);
            
            if ($result['success']) {
                error_log("Sanction processed successfully. New status: " . $result['new_status']);
                header('Location: member_view.php?id=' . $memberId . '&tab=sanctions&success=1');
                exit;
            } else {
                error_log("Error in member sanction save: " . $result['error']);
                $errors[] = $result['error'];
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
                        <a href="member_view.php?id=<?php echo $memberId; ?>" class="text-decoration-none text-muted">
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
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Attenzione:</strong> L'inserimento di un provvedimento cambierà automaticamente lo stato del socio.<br>
                                    <small>
                                        - <strong>In Aspettativa/In Congedo</strong>: imposta lo stato a "Sospeso"<br>
                                        - <strong>Attivo</strong>: se inserito DOPO un provvedimento sospensivo, riporta lo stato ad "Attivo"<br>
                                        - <strong>Decaduto/Dimesso/Escluso</strong>: imposta lo stato rispettivo
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
                                            <option value="approvazione_consiglio_direttivo" <?php echo $sanction['sanction_type'] === 'approvazione_consiglio_direttivo' ? 'selected' : ''; ?>>Approvazione del Consiglio Direttivo</option>
                                            <option value="decaduto" <?php echo $sanction['sanction_type'] === 'decaduto' ? 'selected' : ''; ?>>Decadenza</option>
                                            <option value="dimesso" <?php echo $sanction['sanction_type'] === 'dimesso' ? 'selected' : ''; ?>>Dimissioni</option>
                                            <option value="escluso" <?php echo $sanction['sanction_type'] === 'escluso' ? 'selected' : ''; ?>>Esclusione</option>
                                            <option value="in_aspettativa" <?php echo $sanction['sanction_type'] === 'in_aspettativa' ? 'selected' : ''; ?>>In Aspettativa</option>
                                            <option value="sospeso" <?php echo $sanction['sanction_type'] === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                                            <option value="in_congedo" <?php echo $sanction['sanction_type'] === 'in_congedo' ? 'selected' : ''; ?>>In Congedo</option>
                                            <option value="attivo" <?php echo $sanction['sanction_type'] === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
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
