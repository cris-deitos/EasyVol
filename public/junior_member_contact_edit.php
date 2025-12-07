<?php
/**
 * Add/Edit Junior Member Contact
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
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

$db = $app->getDb();
$memberModel = new JuniorMember($db);

$memberId = intval($_GET['member_id'] ?? 0);
$contactId = intval($_GET['id'] ?? 0);

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
$contact = ['contact_type' => 'cellulare', 'value' => ''];

// Load existing contact if editing
if ($contactId > 0) {
    $contacts = $memberModel->getContacts($memberId);
    foreach ($contacts as $c) {
        if ($c['id'] == $contactId) {
            $contact = $c;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'contact_type' => $_POST['contact_type'] ?? '',
            'value' => trim($_POST['value'] ?? '')
        ];
        
        // Validate
        $validTypes = ['telefono_fisso', 'cellulare', 'email'];
        if (!in_array($data['contact_type'], $validTypes)) {
            $errors[] = 'Tipo di contatto non valido';
        }
        
        if (empty($data['value'])) {
            $errors[] = 'Valore del contatto obbligatorio';
        }
        
        // Validate email format
        if ($data['contact_type'] === 'email' && !filter_var($data['value'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Formato email non valido';
        }
        
        if (empty($errors)) {
            try {
                if ($contactId > 0) {
                    $memberModel->updateContact($contactId, $data);
                } else {
                    $memberModel->addContact($memberId, $data);
                }
                
                header('Location: junior_member_view.php?id=' . $memberId . '&success=1');
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
    <title><?php echo $contactId > 0 ? 'Modifica' : 'Aggiungi'; ?> Contatto - EasyVol</title>
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
                        <?php echo $contactId > 0 ? 'Modifica' : 'Aggiungi'; ?> Contatto
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
                                        <label for="contact_type" class="form-label">Tipo Contatto *</label>
                                        <select class="form-select" id="contact_type" name="contact_type" required>
                                            <option value="telefono_fisso" <?php echo $contact['contact_type'] === 'telefono_fisso' ? 'selected' : ''; ?>>Telefono Fisso</option>
                                            <option value="cellulare" <?php echo $contact['contact_type'] === 'cellulare' ? 'selected' : ''; ?>>Cellulare</option>
                                            <option value="email" <?php echo $contact['contact_type'] === 'email' ? 'selected' : ''; ?>>Email</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="value" class="form-label">Valore *</label>
                                        <input type="text" class="form-control" id="value" name="value" 
                                               value="<?php echo htmlspecialchars($contact['value']); ?>" required>
                                        <div class="form-text">Inserire numero di telefono o email</div>
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
