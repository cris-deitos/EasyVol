<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ConventionController;
use EasyVol\Middleware\CsrfProtection;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('conventions', 'create')) {
    die('Accesso negato');
}

// Log page access
AutoLogger::logPageAccess();

$controller = new ConventionController($app->getDb(), $app->getConfig());
$csrf = new CsrfProtection();

$item = null;
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido';
    } else {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'entities' => [],
            'deadlines' => [],
            'amounts' => []
        ];
        
        // Parse amounts
        if (!empty($_POST['amount_year'])) {
            foreach ($_POST['amount_year'] as $i => $year) {
                if (empty($year)) continue;
                $data['amounts'][] = [
                    'year' => (int)$year,
                    'amount' => (float)($_POST['amount_value'][$i] ?? 0),
                    'notes' => trim($_POST['amount_notes'][$i] ?? '')
                ];
            }
        }
        
        // Parse entities
        if (!empty($_POST['entity_denomination'])) {
            foreach ($_POST['entity_denomination'] as $i => $denom) {
                if (empty(trim($denom))) continue;
                $data['entities'][] = [
                    'denomination' => trim($denom),
                    'entity_type' => trim($_POST['entity_type'][$i] ?? ''),
                    'tax_code' => trim($_POST['entity_tax_code'][$i] ?? ''),
                    'address' => trim($_POST['entity_address'][$i] ?? ''),
                    'phone' => trim($_POST['entity_phone'][$i] ?? ''),
                    'email' => trim($_POST['entity_email'][$i] ?? ''),
                    'pec' => trim($_POST['entity_pec'][$i] ?? ''),
                    'contact_person' => trim($_POST['entity_contact_person'][$i] ?? ''),
                    'notes' => trim($_POST['entity_notes'][$i] ?? '')
                ];
            }
        }
        
        // Parse deadlines
        if (!empty($_POST['deadline_day'])) {
            foreach ($_POST['deadline_day'] as $i => $day) {
                if (empty($day) || empty($_POST['deadline_month'][$i])) continue;
                $data['deadlines'][] = [
                    'day_of_month' => (int)$day,
                    'month' => (int)$_POST['deadline_month'][$i],
                    'description' => trim($_POST['deadline_description'][$i] ?? ''),
                    'notify_to' => trim($_POST['deadline_notify_to'][$i] ?? ''),
                    'advance_days' => (int)($_POST['deadline_advance_days'][$i] ?? 7)
                ];
            }
        }
        
        // Validation
        if (empty($data['name'])) {
            $errors[] = 'Il nome è obbligatorio';
        }
        if (empty($data['start_date'])) {
            $errors[] = 'La data inizio è obbligatoria';
        }
        
        if (empty($errors)) {
            $result = $controller->create($data, $_SESSION['user_id']);
            
            if ($result) {
                header('Location: convention_view.php?id=' . $result . '&saved=1');
                exit;
            } else {
                $errors[] = 'Errore durante il salvataggio';
            }
        }
    }
}

$pageTitle = 'Nuova Convenzione';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include '../src/Views/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-file-earmark-text"></i> <?= $pageTitle ?></h1>
                <a href="conventions.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Torna alla lista</a>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="post" id="conventionForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf->getToken() ?>">
                
                <!-- Basic info -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Dati Convenzione</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($item['name'] ?? $_POST['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Data Inizio *</label>
                                <input type="date" class="form-control" name="start_date" required value="<?= htmlspecialchars($item['start_date'] ?? $_POST['start_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Data Fine</label>
                                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($item['end_date'] ?? $_POST['end_date'] ?? '') ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Descrizione</label>
                                <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($item['description'] ?? $_POST['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Amounts -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Importo Convenzione</strong>
                        <button type="button" class="btn btn-sm btn-success" onclick="addAmount()"><i class="bi bi-plus"></i> Aggiungi Anno</button>
                    </div>
                    <div class="card-body" id="amounts-container">
                        <?php 
                        $amounts = $item['amounts'] ?? [];
                        if (empty($amounts)) $amounts = [['year' => date('Y'), 'amount' => '']];
                        foreach ($amounts as $idx => $amount): 
                        ?>
                        <div class="amount-row border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Anno #<?= $idx + 1 ?></strong>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.amount-row').remove()"><i class="bi bi-trash"></i></button>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Anno</label>
                                    <input type="number" class="form-control" name="amount_year[]" min="2000" max="2099" value="<?= htmlspecialchars($amount['year'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Importo (€)</label>
                                    <input type="number" class="form-control" name="amount_value[]" step="0.01" min="0" value="<?= htmlspecialchars($amount['amount'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Note</label>
                                    <input type="text" class="form-control" name="amount_notes[]" value="<?= htmlspecialchars($amount['notes'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Entities -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Enti Convenzionati</strong>
                        <button type="button" class="btn btn-sm btn-success" onclick="addEntity()"><i class="bi bi-plus"></i> Aggiungi Ente</button>
                    </div>
                    <div class="card-body" id="entities-container">
                        <?php 
                        $entities = $item['entities'] ?? [];
                        if (empty($entities)) $entities = [['denomination' => '', 'entity_type' => '', 'tax_code' => '', 'address' => '', 'phone' => '', 'email' => '', 'pec' => '', 'contact_person' => '', 'notes' => '']];
                        foreach ($entities as $idx => $entity): 
                        ?>
                        <div class="entity-row border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Ente #<?= $idx + 1 ?></strong>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.entity-row').remove()"><i class="bi bi-trash"></i></button>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Denominazione *</label>
                                    <input type="text" class="form-control" name="entity_denomination[]" value="<?= htmlspecialchars($entity['denomination'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Tipo Ente</label>
                                    <select class="form-select" name="entity_type[]">
                                        <option value="">-- Seleziona --</option>
                                        <option value="Comune" <?= ($entity['entity_type'] ?? '') === 'Comune' ? 'selected' : '' ?>>Comune</option>
                                        <option value="Associazione" <?= ($entity['entity_type'] ?? '') === 'Associazione' ? 'selected' : '' ?>>Associazione</option>
                                        <option value="Ente Pubblico" <?= ($entity['entity_type'] ?? '') === 'Ente Pubblico' ? 'selected' : '' ?>>Ente Pubblico</option>
                                        <option value="Azienda" <?= ($entity['entity_type'] ?? '') === 'Azienda' ? 'selected' : '' ?>>Azienda</option>
                                        <option value="Altro" <?= ($entity['entity_type'] ?? '') === 'Altro' ? 'selected' : '' ?>>Altro</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Codice Fiscale</label>
                                    <input type="text" class="form-control" name="entity_tax_code[]" value="<?= htmlspecialchars($entity['tax_code'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Indirizzo</label>
                                    <input type="text" class="form-control" name="entity_address[]" value="<?= htmlspecialchars($entity['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Telefono</label>
                                    <input type="text" class="form-control" name="entity_phone[]" value="<?= htmlspecialchars($entity['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="entity_email[]" value="<?= htmlspecialchars($entity['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">PEC</label>
                                    <input type="email" class="form-control" name="entity_pec[]" value="<?= htmlspecialchars($entity['pec'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Persona di contatto</label>
                                    <input type="text" class="form-control" name="entity_contact_person[]" value="<?= htmlspecialchars($entity['contact_person'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Note</label>
                                    <input type="text" class="form-control" name="entity_notes[]" value="<?= htmlspecialchars($entity['notes'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Deadlines -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Scadenze Annuali</strong>
                        <button type="button" class="btn btn-sm btn-success" onclick="addDeadline()"><i class="bi bi-plus"></i> Aggiungi Scadenza</button>
                    </div>
                    <div class="card-body" id="deadlines-container">
                        <?php 
                        $deadlines = $item['deadlines'] ?? [];
                        if (empty($deadlines)) $deadlines = [['day_of_month' => '', 'month' => '', 'description' => '', 'notify_to' => '', 'advance_days' => 7]];
                        foreach ($deadlines as $idx => $deadline): 
                        ?>
                        <div class="deadline-row border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Scadenza #<?= $idx + 1 ?></strong>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.deadline-row').remove()"><i class="bi bi-trash"></i></button>
                            </div>
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Giorno</label>
                                    <input type="number" class="form-control" name="deadline_day[]" min="1" max="31" value="<?= htmlspecialchars($deadline['day_of_month'] ?? '') ?>">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Mese</label>
                                    <select class="form-select" name="deadline_month[]">
                                        <option value="">--</option>
                                        <?php 
                                        $months = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
                                        for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= (int)($deadline['month'] ?? 0) === $m ? 'selected' : '' ?>><?= $months[$m-1] ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Descrizione</label>
                                    <input type="text" class="form-control" name="deadline_description[]" value="<?= htmlspecialchars($deadline['description'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Avviso a</label>
                                    <input type="text" class="form-control" name="deadline_notify_to[]" placeholder="es. segretario@..." value="<?= htmlspecialchars($deadline['notify_to'] ?? '') ?>">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Giorni preavviso</label>
                                    <input type="number" class="form-control" name="deadline_advance_days[]" min="0" value="<?= htmlspecialchars($deadline['advance_days'] ?? 7) ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Salva</button>
                    <a href="conventions.php" class="btn btn-secondary">Annulla</a>
                </div>
            </form>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function addAmount() {
    const container = document.getElementById('amounts-container');
    const count = container.querySelectorAll('.amount-row').length + 1;
    const currentYear = new Date().getFullYear();
    const html = `<div class="amount-row border rounded p-3 mb-3">
        <div class="d-flex justify-content-between mb-2">
            <strong>Anno #${count}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.amount-row').remove()"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label">Anno</label><input type="number" class="form-control" name="amount_year[]" min="2000" max="2099" value="${currentYear}"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Importo (€)</label><input type="number" class="form-control" name="amount_value[]" step="0.01" min="0"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Note</label><input type="text" class="form-control" name="amount_notes[]"></div>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function addEntity() {
    const container = document.getElementById('entities-container');
    const count = container.querySelectorAll('.entity-row').length + 1;
    const html = `<div class="entity-row border rounded p-3 mb-3">
        <div class="d-flex justify-content-between mb-2">
            <strong>Ente #${count}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.entity-row').remove()"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2"><label class="form-label">Denominazione *</label><input type="text" class="form-control" name="entity_denomination[]"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Tipo Ente</label><select class="form-select" name="entity_type[]"><option value="">-- Seleziona --</option><option value="Comune">Comune</option><option value="Associazione">Associazione</option><option value="Ente Pubblico">Ente Pubblico</option><option value="Azienda">Azienda</option><option value="Altro">Altro</option></select></div>
            <div class="col-md-4 mb-2"><label class="form-label">Codice Fiscale</label><input type="text" class="form-control" name="entity_tax_code[]"></div>
            <div class="col-md-6 mb-2"><label class="form-label">Indirizzo</label><input type="text" class="form-control" name="entity_address[]"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Telefono</label><input type="text" class="form-control" name="entity_phone[]"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="entity_email[]"></div>
            <div class="col-md-4 mb-2"><label class="form-label">PEC</label><input type="email" class="form-control" name="entity_pec[]"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Persona di contatto</label><input type="text" class="form-control" name="entity_contact_person[]"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Note</label><input type="text" class="form-control" name="entity_notes[]"></div>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function addDeadline() {
    const container = document.getElementById('deadlines-container');
    const count = container.querySelectorAll('.deadline-row').length + 1;
    const html = `<div class="deadline-row border rounded p-3 mb-3">
        <div class="d-flex justify-content-between mb-2">
            <strong>Scadenza #${count}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.deadline-row').remove()"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row">
            <div class="col-md-2 mb-2"><label class="form-label">Giorno</label><input type="number" class="form-control" name="deadline_day[]" min="1" max="31"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Mese</label><select class="form-select" name="deadline_month[]"><option value="">--</option><option value="1">Gennaio</option><option value="2">Febbraio</option><option value="3">Marzo</option><option value="4">Aprile</option><option value="5">Maggio</option><option value="6">Giugno</option><option value="7">Luglio</option><option value="8">Agosto</option><option value="9">Settembre</option><option value="10">Ottobre</option><option value="11">Novembre</option><option value="12">Dicembre</option></select></div>
            <div class="col-md-3 mb-2"><label class="form-label">Descrizione</label><input type="text" class="form-control" name="deadline_description[]"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Avviso a</label><input type="text" class="form-control" name="deadline_notify_to[]" placeholder="es. segretario@..."></div>
            <div class="col-md-2 mb-2"><label class="form-label">Giorni preavviso</label><input type="number" class="form-control" name="deadline_advance_days[]" min="0" value="7"></div>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}
</script>
</body>
</html>
