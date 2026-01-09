<?php
require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();
use EasyVol\App;
use EasyVol\Utils\AutoLogger;
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

$memberId = intval($_GET['member_id'] ?? 0);
$surveillanceId = intval($_GET['id'] ?? 0);

if ($memberId <= 0) { 
    header('Location: members.php'); 
    exit; 
}

$errors = [];
$surveillance = [
    'visit_date' => date('Y-m-d'),
    'result' => '',
    'notes' => '',
    'expiry_date' => ''
];

// Load existing surveillance record if editing
if ($surveillanceId > 0) {
    $sql = "SELECT * FROM member_health_surveillance WHERE id = :id AND member_id = :member_id";
    $existing = $db->fetch($sql, ['id' => $surveillanceId, 'member_id' => $memberId]);
    
    if ($existing) {
        $surveillance = $existing;
    } else {
        header('Location: member_view.php?id=' . $memberId);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token non valido';
    } else {
        $visitDate = $_POST['visit_date'] ?? '';
        $result = $_POST['result'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $expiryDate = trim($_POST['expiry_date'] ?? '');
        
        // Validate required fields
        if (empty($visitDate)) {
            $errors[] = 'La data della visita è obbligatoria';
        }
        if (empty($result)) {
            $errors[] = 'L\'esito della visita è obbligatorio';
        }
        
        // Calculate expiry date if not provided (2 years from visit date)
        if (empty($expiryDate) && !empty($visitDate)) {
            $expiryDate = date('Y-m-d', strtotime($visitDate . ' + 2 years'));
        }
        
        if (empty($errors)) {
            try {
                $data = [
                    'visit_date' => $visitDate,
                    'result' => $result,
                    'notes' => $notes,
                    'expiry_date' => $expiryDate
                ];
                
                if ($surveillanceId > 0) {
                    // Update existing record
                    $data['updated_by'] = $app->getUserId();
                    $db->update('member_health_surveillance', $data, ['id' => $surveillanceId]);
                } else {
                    // Insert new record
                    $data['member_id'] = $memberId;
                    $data['created_by'] = $app->getUserId();
                    $db->insert('member_health_surveillance', $data);
                }
                
                header('Location: member_view.php?id=' . $memberId . '&tab=health-surveillance&success=1');
                exit;
            } catch (\Exception $e) {
                $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
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
    <title><?php echo $surveillanceId > 0 ? 'Modifica' : 'Aggiungi'; ?> Visita Sorveglianza Sanitaria - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
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
                        <?php echo $surveillanceId > 0 ? 'Modifica' : 'Aggiungi'; ?> Visita Sorveglianza Sanitaria
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
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="visit_date" class="form-label">Data Visita *</label>
                                    <input type="date" class="form-control" id="visit_date" name="visit_date" 
                                           value="<?php echo htmlspecialchars($surveillance['visit_date']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="result" class="form-label">Esito *</label>
                                    <select class="form-select" id="result" name="result" required>
                                        <option value="">Seleziona...</option>
                                        <option value="Regolare" <?php echo $surveillance['result'] === 'Regolare' ? 'selected' : ''; ?>>Regolare</option>
                                        <option value="Con Limitazioni" <?php echo $surveillance['result'] === 'Con Limitazioni' ? 'selected' : ''; ?>>Con Limitazioni</option>
                                        <option value="Da Ripetere" <?php echo $surveillance['result'] === 'Da Ripetere' ? 'selected' : ''; ?>>Da Ripetere</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="expiry_date" class="form-label">Data Scadenza</label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                           value="<?php echo htmlspecialchars($surveillance['expiry_date']); ?>">
                                    <small class="form-text text-muted">Se lasciata vuota, sarà calcolata automaticamente a 2 anni dalla data della visita</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Note</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($surveillance['notes']); ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="member_view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary">
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
