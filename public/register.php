<?php
/**
 * Registrazione Pubblica
 * 
 * Pagina pubblica per la registrazione di nuovi soci maggiorenni
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\ApplicationController;
use EasyVol\Middleware\CsrfProtection;

$app = new App(false); // No authentication required

$db = $app->getDatabase();
$config = $app->getConfig();
$controller = new ApplicationController($db, $config);

$errors = [];
$success = false;
$applicationCode = '';

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        // Valida accettazione clausole
        if (empty($_POST['privacy_accepted'])) {
            $errors[] = 'Devi accettare l\'informativa privacy';
        }
        if (empty($_POST['terms_accepted'])) {
            $errors[] = 'Devi accettare il regolamento associativo';
        }
        
        // Verifica CAPTCHA (se abilitato)
        if (!empty($config['recaptcha']['enabled'])) {
            $recaptchaSecret = $config['recaptcha']['secret_key'] ?? '';
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            
            if (empty($recaptchaResponse)) {
                $errors[] = 'Completa la verifica CAPTCHA';
            } else {
                $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}");
                $captchaSuccess = json_decode($verify);
                if (!$captchaSuccess->success) {
                    $errors[] = 'Verifica CAPTCHA fallita';
                }
            }
        }
        
        if (empty($errors)) {
            $data = [
                'last_name' => trim($_POST['last_name'] ?? ''),
                'first_name' => trim($_POST['first_name'] ?? ''),
                'birth_date' => $_POST['birth_date'] ?? '',
                'birth_place' => trim($_POST['birth_place'] ?? ''),
                'birth_province' => trim($_POST['birth_province'] ?? ''),
                'tax_code' => strtoupper(trim($_POST['tax_code'] ?? '')),
                'gender' => $_POST['gender'] ?? '',
                'nationality' => trim($_POST['nationality'] ?? 'Italiana'),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'privacy_accepted' => !empty($_POST['privacy_accepted']),
                'terms_accepted' => !empty($_POST['terms_accepted']),
                'photo_release_accepted' => !empty($_POST['photo_release_accepted'])
            ];
            
            $result = $controller->create($data, false);
            
            if ($result['success']) {
                $success = true;
                $applicationCode = $result['code'];
            } else {
                $errors[] = $result['error'] ?? 'Errore durante l\'invio della domanda';
            }
        }
    }
}

$pageTitle = 'Domanda di Iscrizione';
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
    <?php if (!empty($config['recaptcha']['enabled'])): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?php echo htmlspecialchars($config['association']['name'] ?? 'EasyVol'); ?>
            </a>
        </div>
    </nav>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($success): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-check-circle text-success" style="font-size: 80px;"></i>
                            <h2 class="mt-3">Domanda Inviata!</h2>
                            <p class="lead">La tua domanda di iscrizione è stata ricevuta con successo.</p>
                            <div class="alert alert-info">
                                <strong>Codice domanda:</strong> <?php echo htmlspecialchars($applicationCode); ?><br>
                                <small>Conserva questo codice per future comunicazioni</small>
                            </div>
                            <p>Riceverai un'email di conferma all'indirizzo fornito.</p>
                            <p>Il nostro team esaminerà la tua domanda e ti contatterà a breve.</p>
                            <a href="index.php" class="btn btn-primary mt-3">Torna alla Home</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h3>
                        </div>
                        <div class="card-body">
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
                            
                            <form method="POST" action="">
                                <?php echo CsrfProtection::getHiddenField(); ?>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Compila il modulo per richiedere l'iscrizione come socio. Verrai contattato dopo la valutazione della domanda.
                                </div>
                                
                                <h5 class="mb-3">Dati Anagrafici</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Cognome *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">Nome *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="tax_code" class="form-label">Codice Fiscale *</label>
                                        <input type="text" class="form-control text-uppercase" id="tax_code" name="tax_code" 
                                               value="<?php echo htmlspecialchars($_POST['tax_code'] ?? ''); ?>" 
                                               maxlength="16" pattern="[A-Z0-9]{16}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="birth_date" class="form-label">Data di Nascita *</label>
                                        <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                               value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="gender" class="form-label">Sesso *</label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Seleziona...</option>
                                            <option value="M" <?php echo ($_POST['gender'] ?? '') === 'M' ? 'selected' : ''; ?>>Maschile</option>
                                            <option value="F" <?php echo ($_POST['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>Femminile</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="birth_place" class="form-label">Luogo di Nascita *</label>
                                        <input type="text" class="form-control" id="birth_place" name="birth_place" 
                                               value="<?php echo htmlspecialchars($_POST['birth_place'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="birth_province" class="form-label">Provincia *</label>
                                        <input type="text" class="form-control text-uppercase" id="birth_province" name="birth_province" 
                                               value="<?php echo htmlspecialchars($_POST['birth_province'] ?? ''); ?>" 
                                               maxlength="2" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="nationality" class="form-label">Nazionalità</label>
                                        <input type="text" class="form-control" id="nationality" name="nationality" 
                                               value="<?php echo htmlspecialchars($_POST['nationality'] ?? 'Italiana'); ?>">
                                    </div>
                                </div>
                                
                                <h5 class="mb-3 mt-4">Contatti</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Telefono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <h5 class="mb-3 mt-4">Clausole</h5>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="privacy_accepted" name="privacy_accepted" required>
                                        <label class="form-check-label" for="privacy_accepted">
                                            Ho letto e accetto l'<a href="#" target="_blank">informativa sulla privacy</a> *
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" required>
                                        <label class="form-check-label" for="terms_accepted">
                                            Ho letto e accetto il <a href="#" target="_blank">regolamento associativo</a> *
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="photo_release_accepted" name="photo_release_accepted">
                                        <label class="form-check-label" for="photo_release_accepted">
                                            Autorizzo l'uso di foto e video per attività dell'associazione
                                        </label>
                                    </div>
                                </div>
                                
                                <?php if (!empty($config['recaptcha']['enabled'])): ?>
                                    <div class="mb-3">
                                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha']['site_key']); ?>"></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-send"></i> Invia Domanda
                                    </button>
                                </div>
                                
                                <p class="text-muted small mt-3">* Campi obbligatori</p>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <footer class="bg-light py-4 mt-5">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['association']['name'] ?? 'EasyVol'); ?>. Tutti i diritti riservati.</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
