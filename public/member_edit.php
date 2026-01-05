<?php
/**
 * Gestione Soci - Modifica/Crea
 * 
 * Pagina per creare o modificare un socio
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\MemberController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $memberId > 0;

// Verifica permessi
if ($isEdit && !$app->checkPermission('members', 'edit')) {
    die('Accesso negato');
}
if (!$isEdit && !$app->checkPermission('members', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MemberController($db, $config);

$member = [];
$errors = [];
$success = false;

// Se in modifica, carica dati esistenti
if ($isEdit) {
    $member = $controller->get($memberId);
    if (!$member) {
        header('Location: members.php?error=not_found');
        exit;
    }
}

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'registration_number' => $_POST['registration_number'] ?? '',
            'member_type' => $_POST['member_type'] ?? 'ordinario',
            'member_status' => $_POST['member_status'] ?? 'attivo',
            'volunteer_status' => $_POST['volunteer_status'] ?? 'in_formazione',
            'last_name' => trim($_POST['last_name'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'birth_date' => $_POST['birth_date'] ?? '',
            'birth_place' => trim($_POST['birth_place'] ?? ''),
            'birth_province' => trim($_POST['birth_province'] ?? ''),
            'tax_code' => strtoupper(trim($_POST['tax_code'] ?? '')),
            'gender' => $_POST['gender'] ?? '',
            'nationality' => trim($_POST['nationality'] ?? 'Italiana'),
            'worker_type' => !empty($_POST['worker_type']) ? $_POST['worker_type'] : null,
            'education_level' => !empty($_POST['education_level']) ? $_POST['education_level'] : null,
            'registration_date' => $_POST['registration_date'] ?? date('Y-m-d')
        ];
        
        try {
            if ($isEdit) {
                $result = $controller->update($memberId, $data, $app->getUserId());
            } else {
                $result = $controller->create($data, $app->getUserId());
                $memberId = $result;
            }
            
            if ($result) {
                $success = true;
                // Gestione upload foto
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = $controller->uploadPhoto($memberId, $_FILES['photo'], $app->getUserId());
                    if (!$uploadResult['success']) {
                        $errors[] = 'Errore durante il caricamento della foto: ' . $uploadResult['error'];
                        $success = false;
                    }
                }
                
                // Redirect only if no errors occurred
                if ($success && empty($errors)) {
                    header('Location: member_view.php?id=' . $memberId . '&success=1');
                    exit;
                }
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Socio' : 'Nuovo Socio';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyVol</title>
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
                        <a href="<?php echo $isEdit ? 'member_view.php?id=' . $memberId : 'members.php'; ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5>Errori:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <?php echo CsrfProtection::getHiddenField(); ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <label for="registration_number" class="form-label">Matricola *</label>
                                    <input type="text" class="form-control" id="registration_number" name="registration_number" 
                                           value="<?php echo htmlspecialchars($member['registration_number'] ?? ''); ?>" 
                                           placeholder="Generata automaticamente se vuota">
                                </div>
                                <div class="col-md-4">
                                    <label for="member_type" class="form-label">Tipo Socio *</label>
                                    <select class="form-select" id="member_type" name="member_type" required>
                                        <option value="ordinario" <?php echo ($member['member_type'] ?? '') === 'ordinario' ? 'selected' : ''; ?>>Ordinario</option>
                                        <option value="fondatore" <?php echo ($member['member_type'] ?? '') === 'fondatore' ? 'selected' : ''; ?>>Fondatore</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="member_status" class="form-label">Stato *</label>
                                    <select class="form-select" id="member_status" name="member_status" required>
                                        <option value="attivo" <?php echo ($member['member_status'] ?? 'attivo') === 'attivo' ? 'selected' : ''; ?>>Attivo</option>
                                        <option value="sospeso" <?php echo ($member['member_status'] ?? '') === 'sospeso' ? 'selected' : ''; ?>>Sospeso</option>
                                        <option value="dimesso" <?php echo ($member['member_status'] ?? '') === 'dimesso' ? 'selected' : ''; ?>>Dimesso</option>
                                        <option value="decaduto" <?php echo ($member['member_status'] ?? '') === 'decaduto' ? 'selected' : ''; ?>>Decaduto</option>
                                        <option value="escluso" <?php echo ($member['member_status'] ?? '') === 'escluso' ? 'selected' : ''; ?>>Escluso</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="volunteer_status" class="form-label">Stato Volontario *</label>
                                    <select class="form-select" id="volunteer_status" name="volunteer_status" required>
                                        <option value="operativo" <?php echo ($member['volunteer_status'] ?? '') === 'operativo' ? 'selected' : ''; ?>>Operativo</option>
                                        <option value="in_formazione" <?php echo ($member['volunteer_status'] ?? 'in_formazione') === 'in_formazione' ? 'selected' : ''; ?>>In Formazione</option>
                                        <option value="non_operativo" <?php echo ($member['volunteer_status'] ?? '') === 'non_operativo' ? 'selected' : ''; ?>>Non Operativo</option>
                                    </select>
                                </div>
                            </div>
                            
                            <h5 class="mb-3">Dati Anagrafici</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Cognome *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($member['last_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">Nome *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($member['first_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="tax_code" class="form-label">Codice Fiscale</label>
                                    <input type="text" class="form-control text-uppercase" id="tax_code" name="tax_code" 
                                           value="<?php echo htmlspecialchars($member['tax_code'] ?? ''); ?>" 
                                           maxlength="16" pattern="^([A-Z0-9]{16}|)$" title="Codice fiscale deve essere esattamente 16 caratteri alfanumerici o vuoto">
                                </div>
                                <div class="col-md-3">
                                    <label for="birth_date" class="form-label">Data di Nascita *</label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                           value="<?php echo htmlspecialchars($member['birth_date'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="gender" class="form-label">Sesso</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Seleziona...</option>
                                        <option value="M" <?php echo ($member['gender'] ?? '') === 'M' ? 'selected' : ''; ?>>Maschile</option>
                                        <option value="F" <?php echo ($member['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>Femminile</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label for="birth_place" class="form-label">Luogo di Nascita</label>
                                    <input type="text" class="form-control" id="birth_place" name="birth_place" 
                                           value="<?php echo htmlspecialchars($member['birth_place'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="birth_province" class="form-label">Provincia</label>
                                    <input type="text" class="form-control text-uppercase" id="birth_province" name="birth_province" 
                                           value="<?php echo htmlspecialchars($member['birth_province'] ?? ''); ?>" 
                                           maxlength="2">
                                </div>
                                <div class="col-md-2">
                                    <label for="nationality" class="form-label">Nazionalità</label>
                                    <select class="form-select" id="nationality" name="nationality">
                                        <?php 
                                        use EasyVol\Utils\CountryList;
                                        echo CountryList::getNationalityOptions($member['nationality'] ?? 'Italiana');
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <h5 class="mb-3">Informazioni Professionali e Formative</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="worker_type" class="form-label">Tipo di Lavoratore</label>
                                    <select class="form-select" id="worker_type" name="worker_type">
                                        <option value="">Seleziona...</option>
                                        <option value="studente" <?php echo ($member['worker_type'] ?? '') === 'studente' ? 'selected' : ''; ?>>Studente</option>
                                        <option value="dipendente_privato" <?php echo ($member['worker_type'] ?? '') === 'dipendente_privato' ? 'selected' : ''; ?>>Dipendente Privato</option>
                                        <option value="dipendente_pubblico" <?php echo ($member['worker_type'] ?? '') === 'dipendente_pubblico' ? 'selected' : ''; ?>>Dipendente Pubblico</option>
                                        <option value="lavoratore_autonomo" <?php echo ($member['worker_type'] ?? '') === 'lavoratore_autonomo' ? 'selected' : ''; ?>>Lavoratore Autonomo</option>
                                        <option value="disoccupato" <?php echo ($member['worker_type'] ?? '') === 'disoccupato' ? 'selected' : ''; ?>>Disoccupato</option>
                                        <option value="pensionato" <?php echo ($member['worker_type'] ?? '') === 'pensionato' ? 'selected' : ''; ?>>Pensionato</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="education_level" class="form-label">Titolo di Studio</label>
                                    <select class="form-select" id="education_level" name="education_level">
                                        <option value="">Seleziona...</option>
                                        <option value="licenza_media" <?php echo ($member['education_level'] ?? '') === 'licenza_media' ? 'selected' : ''; ?>>Licenza Media</option>
                                        <option value="diploma_maturita" <?php echo ($member['education_level'] ?? '') === 'diploma_maturita' ? 'selected' : ''; ?>>Diploma di Maturità</option>
                                        <option value="laurea_triennale" <?php echo ($member['education_level'] ?? '') === 'laurea_triennale' ? 'selected' : ''; ?>>Laurea Triennale</option>
                                        <option value="laurea_magistrale" <?php echo ($member['education_level'] ?? '') === 'laurea_magistrale' ? 'selected' : ''; ?>>Laurea Magistrale</option>
                                        <option value="dottorato" <?php echo ($member['education_level'] ?? '') === 'dottorato' ? 'selected' : ''; ?>>Dottorato</option>
                                    </select>
                                </div>
                            </div>
                            
                            <h5 class="mb-3">Altre Informazioni</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="registration_date" class="form-label">Data Iscrizione</label>
                                    <input type="date" class="form-control" id="registration_date" name="registration_date" 
                                           value="<?php echo htmlspecialchars($member['registration_date'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="photo" class="form-label">Foto</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <?php if (!empty($member['photo_path'])): ?>
                                        <small class="text-muted">Foto attuale presente. Carica un nuovo file per sostituirla.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="<?php echo $isEdit ? 'member_view.php?id=' . $memberId : 'members.php'; ?>" 
                                   class="btn btn-secondary">
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
    <script>
        // Force uppercase on text fields
        document.addEventListener('DOMContentLoaded', function() {
            const uppercaseFields = ['last_name', 'first_name', 'birth_place', 'birth_province', 'nationality'];
            
            uppercaseFields.forEach(function(fieldName) {
                const field = document.getElementById(fieldName);
                if (field) {
                    field.addEventListener('input', function() {
                        const start = this.selectionStart;
                        const end = this.selectionEnd;
                        this.value = this.value.toUpperCase();
                        this.setSelectionRange(start, end);
                    });
                }
            });
        });
    </script>
</body>
</html>
