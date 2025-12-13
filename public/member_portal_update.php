<?php
/**
 * Member Portal - Step 3: View and Update Member Data
 * 
 * Allow verified members to view and update their data
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\MemberPortalController;
use EasyVol\Middleware\CsrfProtection;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance(); // Public page - no authentication required

// Redirect to install if not configured
if (!$app->isInstalled()) {
    header("Location: install.php");
    exit;
}

// Check if member is verified
if (!isset($_SESSION['portal_verified']) || !isset($_SESSION['portal_member_id'])) {
    header("Location: member_portal_verify.php");
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MemberPortalController($db, $config);

// Log page access
AutoLogger::logPageAccess();

$memberId = $_SESSION['portal_member_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // Verify CSRF token
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token di sicurezza non valido. Ricarica la pagina e riprova.';
    } else {
        // Prepare data for update
        $updateData = [];
        
        // Contacts
        if (isset($_POST['contacts'])) {
            $contacts = [];
            foreach ($_POST['contacts'] as $idx => $contact) {
                if (!empty($contact['value'])) {
                    $contacts[] = [
                        'type' => $contact['type'],
                        'value' => $contact['value']
                    ];
                }
            }
            $updateData['contacts'] = $contacts;
        }
        
        // Addresses
        if (isset($_POST['addresses'])) {
            $addresses = [];
            foreach ($_POST['addresses'] as $type => $address) {
                if (!empty($address['street']) || !empty($address['city'])) {
                    $addresses[] = array_merge(['type' => $type], $address);
                }
            }
            $updateData['addresses'] = $addresses;
        }
        
        // Courses
        if (isset($_POST['courses'])) {
            $courses = [];
            foreach ($_POST['courses'] as $idx => $course) {
                if (!empty($course['course_name'])) {
                    $courses[] = $course;
                }
            }
            $updateData['courses'] = $courses;
        }
        
        // Licenses
        if (isset($_POST['licenses'])) {
            $licenses = [];
            foreach ($_POST['licenses'] as $idx => $license) {
                if (!empty($license['license_type'])) {
                    $licenses[] = $license;
                }
            }
            $updateData['licenses'] = $licenses;
        }
        
        // Health
        if (isset($_POST['health'])) {
            $health = [];
            foreach ($_POST['health'] as $idx => $h) {
                if (!empty($h['type']) && !empty($h['description'])) {
                    $health[] = [
                        'type' => $h['type'],
                        'description' => $h['description']
                    ];
                }
            }
            $updateData['health'] = $health;
        }
        
        // Availability
        if (isset($_POST['availability'])) {
            $availability = [];
            foreach ($_POST['availability'] as $type => $avail) {
                if (isset($avail['enabled']) && $avail['enabled'] === '1') {
                    $availability[] = [
                        'availability_type' => $type,
                        'notes' => $avail['notes'] ?? ''
                    ];
                }
            }
            $updateData['availability'] = $availability;
        }
        
        // Update data
        if ($controller->updateMemberData($memberId, $updateData)) {
            $success = 'I tuoi dati sono stati aggiornati con successo! Riceverai una conferma via email.';
        } else {
            $error = 'Si è verificato un errore durante l\'aggiornamento. Riprova o contatta la Segreteria.';
        }
    }
}

// Get member data
$memberData = $controller->getMemberData($memberId);

if (!$memberData) {
    $error = 'Errore nel caricamento dei dati. Riprova o contatta la Segreteria.';
    $memberData = [];
}

$associationName = $config['association']['name'] ?? 'Associazione';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portale Soci - Aggiorna Dati - <?= htmlspecialchars($associationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
            padding: 20px 0;
        }
        
        .portal-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .portal-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .portal-header p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .step {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .step.completed {
            background: rgba(40, 167, 69, 0.9);
        }
        
        .step.active {
            background: white;
            color: #667eea;
        }
        
        .step-line {
            width: 50px;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
        }
        
        .step-line.completed {
            background: rgba(40, 167, 69, 0.9);
        }
        
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f4f6f9;
            padding: 20px;
            border-radius: 16px 16px 0 0 !important;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }
        
        .readonly-section {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .readonly-section h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #6c757d;
            min-width: 180px;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: #667eea;
            background: transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: #667eea;
            background: transparent;
            border-bottom: 3px solid #667eea;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn-add {
            background: #28a745;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-add:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .btn-remove {
            background: #dc3545;
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 14px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .repeater-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .logout-link {
            color: white;
            text-decoration: none;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .logout-link:hover {
            opacity: 1;
            text-decoration: underline;
            color: white;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-header">
            <div class="step-indicator">
                <div class="step completed"><i class="bi bi-check"></i></div>
                <div class="step-line completed"></div>
                <div class="step completed"><i class="bi bi-check"></i></div>
                <div class="step-line completed"></div>
                <div class="step active">3</div>
            </div>
            <h1><i class="bi bi-person-lines-fill"></i> I Tuoi Dati</h1>
            <p>Visualizza e aggiorna le tue informazioni personali</p>
            <div class="mt-3">
                <a href="member_portal_verify.php" class="logout-link">
                    <i class="bi bi-box-arrow-left"></i> Esci dal portale
                </a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="updateForm">
            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::generateToken() ?>">
            <input type="hidden" name="action" value="update">
            
            <!-- Read-only Personal Data -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-person-badge"></i> Dati Anagrafici e Societari</h5>
                </div>
                <div class="card-body">
                    <div class="readonly-section">
                        <h6><i class="bi bi-info-circle"></i> Dati Non Modificabili</h6>
                        <p class="text-muted" style="font-size: 14px; margin-bottom: 15px;">
                            I seguenti dati non possono essere modificati direttamente. In caso di errori, contatta la Segreteria dell'Associazione.
                        </p>
                        <div class="info-row">
                            <div class="info-label">Matricola:</div>
                            <div class="info-value"><strong><?= htmlspecialchars($memberData['registration_number'] ?? 'N/D') ?></strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Nome e Cognome:</div>
                            <div class="info-value"><?= htmlspecialchars(($memberData['first_name'] ?? '') . ' ' . ($memberData['last_name'] ?? '')) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Codice Fiscale:</div>
                            <div class="info-value"><?= htmlspecialchars($memberData['tax_code'] ?? 'N/D') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Data di Nascita:</div>
                            <div class="info-value"><?= $memberData['birth_date'] ? date('d/m/Y', strtotime($memberData['birth_date'])) : 'N/D' ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Luogo di Nascita:</div>
                            <div class="info-value"><?= htmlspecialchars($memberData['birth_place'] ?? 'N/D') ?> (<?= htmlspecialchars($memberData['birth_province'] ?? '') ?>)</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Stato Socio:</div>
                            <div class="info-value"><span class="badge bg-success"><?= htmlspecialchars(ucfirst($memberData['member_status'] ?? 'N/D')) ?></span></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Tipo Socio:</div>
                            <div class="info-value"><?= htmlspecialchars(ucfirst($memberData['member_type'] ?? 'N/D')) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Tipo Lavoratore:</div>
                            <div class="info-value"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($memberData['worker_type'] ?? 'N/D'))) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Titolo di Studio:</div>
                            <div class="info-value"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($memberData['education_level'] ?? 'N/D'))) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs for editable sections -->
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="memberTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button">
                                <i class="bi bi-telephone"></i> Recapiti
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" type="button">
                                <i class="bi bi-geo-alt"></i> Indirizzi
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button">
                                <i class="bi bi-mortarboard"></i> Corsi
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="licenses-tab" data-bs-toggle="tab" data-bs-target="#licenses" type="button">
                                <i class="bi bi-card-text"></i> Patenti
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button">
                                <i class="bi bi-heart-pulse"></i> Info Alimentari
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="availability-tab" data-bs-toggle="tab" data-bs-target="#availability" type="button">
                                <i class="bi bi-calendar-check"></i> Disponibilità
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="memberTabsContent">
                        <!-- Contacts Tab -->
                        <div class="tab-pane fade show active" id="contacts" role="tabpanel">
                            <h5 class="mb-3">Recapiti</h5>
                            <div id="contacts-container">
                                <?php 
                                $contacts = $memberData['contacts'] ?? [];
                                if (empty($contacts)) {
                                    $contacts = [['contact_type' => 'cellulare', 'value' => '']];
                                }
                                foreach ($contacts as $idx => $contact): 
                                ?>
                                <div class="repeater-item" data-index="<?= $idx ?>">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Tipo</label>
                                            <select name="contacts[<?= $idx ?>][type]" class="form-select">
                                                <option value="cellulare" <?= ($contact['contact_type'] ?? '') == 'cellulare' ? 'selected' : '' ?>>Cellulare</option>
                                                <option value="telefono_fisso" <?= ($contact['contact_type'] ?? '') == 'telefono_fisso' ? 'selected' : '' ?>>Telefono Fisso</option>
                                                <option value="email" <?= ($contact['contact_type'] ?? '') == 'email' ? 'selected' : '' ?>>Email</option>
                                                <option value="pec" <?= ($contact['contact_type'] ?? '') == 'pec' ? 'selected' : '' ?>>PEC</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Valore</label>
                                            <input type="text" name="contacts[<?= $idx ?>][value]" class="form-control" value="<?= htmlspecialchars($contact['value'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-add" onclick="addContact()">
                                <i class="bi bi-plus-circle"></i> Aggiungi Recapito
                            </button>
                        </div>
                        
                        <!-- Addresses Tab -->
                        <div class="tab-pane fade" id="addresses" role="tabpanel">
                            <h5 class="mb-3">Indirizzi</h5>
                            
                            <?php 
                            $addresses = $memberData['addresses'] ?? [];
                            $residenza = null;
                            $domicilio = null;
                            foreach ($addresses as $addr) {
                                if ($addr['address_type'] == 'residenza') $residenza = $addr;
                                if ($addr['address_type'] == 'domicilio') $domicilio = $addr;
                            }
                            ?>
                            
                            <div class="mb-4">
                                <h6>Residenza</h6>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Via</label>
                                        <input type="text" name="addresses[residenza][street]" class="form-control" value="<?= htmlspecialchars($residenza['street'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Numero</label>
                                        <input type="text" name="addresses[residenza][number]" class="form-control" value="<?= htmlspecialchars($residenza['number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Città</label>
                                        <input type="text" name="addresses[residenza][city]" class="form-control" value="<?= htmlspecialchars($residenza['city'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Provincia</label>
                                        <input type="text" name="addresses[residenza][province]" class="form-control" maxlength="2" value="<?= htmlspecialchars($residenza['province'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">CAP</label>
                                        <input type="text" name="addresses[residenza][cap]" class="form-control" maxlength="5" value="<?= htmlspecialchars($residenza['cap'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h6>Domicilio (se diverso dalla residenza)</h6>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Via</label>
                                        <input type="text" name="addresses[domicilio][street]" class="form-control" value="<?= htmlspecialchars($domicilio['street'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Numero</label>
                                        <input type="text" name="addresses[domicilio][number]" class="form-control" value="<?= htmlspecialchars($domicilio['number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Città</label>
                                        <input type="text" name="addresses[domicilio][city]" class="form-control" value="<?= htmlspecialchars($domicilio['city'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Provincia</label>
                                        <input type="text" name="addresses[domicilio][province]" class="form-control" maxlength="2" value="<?= htmlspecialchars($domicilio['province'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">CAP</label>
                                        <input type="text" name="addresses[domicilio][cap]" class="form-control" maxlength="5" value="<?= htmlspecialchars($domicilio['cap'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Courses Tab -->
                        <div class="tab-pane fade" id="courses" role="tabpanel">
                            <h5 class="mb-3">Corsi</h5>
                            <div id="courses-container">
                                <?php 
                                $courses = $memberData['courses'] ?? [];
                                if (empty($courses)) {
                                    $courses = [['course_name' => '', 'completion_date' => '', 'expiry_date' => '', 'notes' => '']];
                                }
                                foreach ($courses as $idx => $course): 
                                ?>
                                <div class="repeater-item" data-index="<?= $idx ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nome Corso</label>
                                            <input type="text" name="courses[<?= $idx ?>][course_name]" class="form-control" value="<?= htmlspecialchars($course['course_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Data Completamento</label>
                                            <input type="date" name="courses[<?= $idx ?>][completion_date]" class="form-control" value="<?= htmlspecialchars($course['completion_date'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Data Scadenza</label>
                                            <input type="date" name="courses[<?= $idx ?>][expiry_date]" class="form-control" value="<?= htmlspecialchars($course['expiry_date'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-10">
                                            <label class="form-label">Note</label>
                                            <input type="text" name="courses[<?= $idx ?>][notes]" class="form-control" value="<?= htmlspecialchars($course['notes'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-add" onclick="addCourse()">
                                <i class="bi bi-plus-circle"></i> Aggiungi Corso
                            </button>
                        </div>
                        
                        <!-- Licenses Tab -->
                        <div class="tab-pane fade" id="licenses" role="tabpanel">
                            <h5 class="mb-3">Patenti</h5>
                            <div id="licenses-container">
                                <?php 
                                $licenses = $memberData['licenses'] ?? [];
                                if (empty($licenses)) {
                                    $licenses = [['license_type' => '', 'license_number' => '', 'issue_date' => '', 'expiry_date' => '', 'notes' => '']];
                                }
                                foreach ($licenses as $idx => $license): 
                                ?>
                                <div class="repeater-item" data-index="<?= $idx ?>">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Tipo Patente</label>
                                            <input type="text" name="licenses[<?= $idx ?>][license_type]" class="form-control" value="<?= htmlspecialchars($license['license_type'] ?? '') ?>" placeholder="Es: B, C, Nautica">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Numero</label>
                                            <input type="text" name="licenses[<?= $idx ?>][license_number]" class="form-control" value="<?= htmlspecialchars($license['license_number'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Data Rilascio</label>
                                            <input type="date" name="licenses[<?= $idx ?>][issue_date]" class="form-control" value="<?= htmlspecialchars($license['issue_date'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Data Scadenza</label>
                                            <input type="date" name="licenses[<?= $idx ?>][expiry_date]" class="form-control" value="<?= htmlspecialchars($license['expiry_date'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-10">
                                            <label class="form-label">Note</label>
                                            <input type="text" name="licenses[<?= $idx ?>][notes]" class="form-control" value="<?= htmlspecialchars($license['notes'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-add" onclick="addLicense()">
                                <i class="bi bi-plus-circle"></i> Aggiungi Patente
                            </button>
                        </div>
                        
                        <!-- Health Tab -->
                        <div class="tab-pane fade" id="health" role="tabpanel">
                            <h5 class="mb-3">Informazioni Alimentari</h5>
                            <p class="text-muted" style="font-size: 14px;">Diete, allergie, intolleranze o altre informazioni alimentari rilevanti per l'organizzazione di eventi.</p>
                            <div id="health-container">
                                <?php 
                                $healthData = $memberData['health'] ?? [];
                                if (empty($healthData)) {
                                    $healthData = [['health_type' => '', 'description' => '']];
                                }
                                foreach ($healthData as $idx => $health): 
                                ?>
                                <div class="repeater-item" data-index="<?= $idx ?>">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Tipo</label>
                                            <select name="health[<?= $idx ?>][type]" class="form-select">
                                                <option value="">Seleziona...</option>
                                                <option value="vegano" <?= ($health['health_type'] ?? '') == 'vegano' ? 'selected' : '' ?>>Vegano</option>
                                                <option value="vegetariano" <?= ($health['health_type'] ?? '') == 'vegetariano' ? 'selected' : '' ?>>Vegetariano</option>
                                                <option value="allergie" <?= ($health['health_type'] ?? '') == 'allergie' ? 'selected' : '' ?>>Allergie</option>
                                                <option value="intolleranze" <?= ($health['health_type'] ?? '') == 'intolleranze' ? 'selected' : '' ?>>Intolleranze</option>
                                                <option value="patologie" <?= ($health['health_type'] ?? '') == 'patologie' ? 'selected' : '' ?>>Patologie</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Descrizione</label>
                                            <input type="text" name="health[<?= $idx ?>][description]" class="form-control" value="<?= htmlspecialchars($health['description'] ?? '') ?>" placeholder="Es: Celiachia, Lattosio, ecc.">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-add" onclick="addHealth()">
                                <i class="bi bi-plus-circle"></i> Aggiungi Informazione
                            </button>
                        </div>
                        
                        <!-- Availability Tab -->
                        <div class="tab-pane fade" id="availability" role="tabpanel">
                            <h5 class="mb-3">Disponibilità Territoriale</h5>
                            <p class="text-muted" style="font-size: 14px;">Indica la tua disponibilità a partecipare ad interventi su diversi livelli territoriali.</p>
                            
                            <?php 
                            $availability = $memberData['availability'] ?? [];
                            $availabilityMap = [];
                            foreach ($availability as $avail) {
                                $availabilityMap[$avail['availability_type']] = $avail;
                            }
                            
                            $availTypes = [
                                'comunale' => 'Comunale',
                                'provinciale' => 'Provinciale',
                                'regionale' => 'Regionale',
                                'nazionale' => 'Nazionale',
                                'internazionale' => 'Internazionale'
                            ];
                            ?>
                            
                            <?php foreach ($availTypes as $type => $label): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="availability[<?= $type ?>][enabled]" value="1" id="avail_<?= $type ?>" <?= isset($availabilityMap[$type]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="avail_<?= $type ?>">
                                    <strong><?= $label ?></strong>
                                </label>
                                <input type="text" name="availability[<?= $type ?>][notes]" class="form-control mt-2" placeholder="Note (opzionale)" value="<?= htmlspecialchars($availabilityMap[$type]['notes'] ?? '') ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn btn-submit">
                    <i class="bi bi-save"></i> Salva Modifiche
                </button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let contactIndex = <?= !empty($memberData['contacts']) ? count($memberData['contacts']) : 1 ?>;
        let courseIndex = <?= !empty($memberData['courses']) ? count($memberData['courses']) : 1 ?>;
        let licenseIndex = <?= !empty($memberData['licenses']) ? count($memberData['licenses']) : 1 ?>;
        let healthIndex = <?= !empty($memberData['health']) ? count($memberData['health']) : 1 ?>;
        
        function removeItem(btn) {
            btn.closest('.repeater-item').remove();
        }
        
        function addContact() {
            const container = document.getElementById('contacts-container');
            const html = `
                <div class="repeater-item" data-index="${contactIndex}">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select name="contacts[${contactIndex}][type]" class="form-select">
                                <option value="cellulare">Cellulare</option>
                                <option value="telefono_fisso">Telefono Fisso</option>
                                <option value="email">Email</option>
                                <option value="pec">PEC</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valore</label>
                            <input type="text" name="contacts[${contactIndex}][value]" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            contactIndex++;
        }
        
        function addCourse() {
            const container = document.getElementById('courses-container');
            const html = `
                <div class="repeater-item" data-index="${courseIndex}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome Corso</label>
                            <input type="text" name="courses[${courseIndex}][course_name]" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Completamento</label>
                            <input type="date" name="courses[${courseIndex}][completion_date]" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Scadenza</label>
                            <input type="date" name="courses[${courseIndex}][expiry_date]" class="form-control">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">Note</label>
                            <input type="text" name="courses[${courseIndex}][notes]" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            courseIndex++;
        }
        
        function addLicense() {
            const container = document.getElementById('licenses-container');
            const html = `
                <div class="repeater-item" data-index="${licenseIndex}">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo Patente</label>
                            <input type="text" name="licenses[${licenseIndex}][license_type]" class="form-control" placeholder="Es: B, C, Nautica">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Numero</label>
                            <input type="text" name="licenses[${licenseIndex}][license_number]" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Rilascio</label>
                            <input type="date" name="licenses[${licenseIndex}][issue_date]" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Scadenza</label>
                            <input type="date" name="licenses[${licenseIndex}][expiry_date]" class="form-control">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">Note</label>
                            <input type="text" name="licenses[${licenseIndex}][notes]" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            licenseIndex++;
        }
        
        function addHealth() {
            const container = document.getElementById('health-container');
            const html = `
                <div class="repeater-item" data-index="${healthIndex}">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select name="health[${healthIndex}][type]" class="form-select">
                                <option value="">Seleziona...</option>
                                <option value="vegano">Vegano</option>
                                <option value="vegetariano">Vegetariano</option>
                                <option value="allergie">Allergie</option>
                                <option value="intolleranze">Intolleranze</option>
                                <option value="patologie">Patologie</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descrizione</label>
                            <input type="text" name="health[${healthIndex}][description]" class="form-control" placeholder="Es: Celiachia, Lattosio, ecc.">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-remove w-100" onclick="removeItem(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            healthIndex++;
        }
        
        // Confirm before submit
        document.getElementById('updateForm').addEventListener('submit', function(e) {
            if (!confirm('Confermi di voler salvare le modifiche? Riceverai una conferma via email.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
