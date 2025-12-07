<?php
/**
 * Gestione Soci - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un socio
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Controllers\MemberController;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->checkPermission('members', 'view')) {
    die('Accesso negato');
}

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($memberId <= 0) {
    header('Location: members.php');
    exit;
}

$db = $app->getDb();
$config = $app->getConfig();
$controller = new MemberController($db, $config);

$member = $controller->get($memberId);

if (!$member) {
    header('Location: members.php?error=not_found');
    exit;
}

$pageTitle = 'Dettaglio Socio: ' . $member['first_name'] . ' ' . $member['last_name'];
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
                        <a href="members.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                <a href="member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="printCard()">
                                <i class="bi bi-printer"></i> Stampa Tesserino
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Dati Principali -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <?php if (!empty($member['photo_path']) && file_exists($member['photo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($member['photo_path']); ?>" 
                                         alt="Foto socio" class="img-fluid rounded mb-3" style="max-width: 200px;">
                                <?php else: ?>
                                    <div class="bg-secondary text-white rounded mb-3 d-inline-flex align-items-center justify-content-center" 
                                         style="width: 200px; height: 250px;">
                                        <i class="bi bi-person" style="font-size: 80px;"></i>
                                    </div>
                                <?php endif; ?>
                                <h5><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h5>
                                <p class="text-muted mb-1">Matricola: <?php echo htmlspecialchars($member['registration_number']); ?></p>
                                <?php
                                $statusColors = [
                                    'attivo' => 'success',
                                    'sospeso' => 'warning',
                                    'dimesso' => 'secondary',
                                    'deceduto' => 'dark'
                                ];
                                $color = $statusColors[$member['member_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?> mb-2">
                                    <?php echo ucfirst($member['member_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-9">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-3" id="memberTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                                    <i class="bi bi-person"></i> Dati Anagrafici
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab">
                                    <i class="bi bi-telephone"></i> Contatti
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab">
                                    <i class="bi bi-house"></i> Indirizzi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
                                    <i class="bi bi-briefcase"></i> Datore di Lavoro
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="qualifications-tab" data-bs-toggle="tab" data-bs-target="#qualifications" type="button" role="tab">
                                    <i class="bi bi-award"></i> Qualifiche
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab">
                                    <i class="bi bi-book"></i> Corsi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="licenses-tab" data-bs-toggle="tab" data-bs-target="#licenses" type="button" role="tab">
                                    <i class="bi bi-card-list"></i> Patenti
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button" role="tab">
                                    <i class="bi bi-heart-pulse"></i> Allergie/Salute
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="memberTabsContent">
                            <!-- Dati Anagrafici -->
                            <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Informazioni Personali</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Cognome</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($member['last_name']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Nome</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($member['first_name']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Codice Fiscale</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($member['tax_code'] ?? 'N/D'); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Data di Nascita</label>
                                                <p class="mb-0"><?php echo date('d/m/Y', strtotime($member['birth_date'])); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Luogo di Nascita</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($member['birth_place'] ?? 'N/D'); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Provincia di Nascita</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($member['birth_province'] ?? 'N/D'); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Sesso</label>
                                                <p class="mb-0"><?php echo $member['gender'] === 'M' ? 'Maschile' : ($member['gender'] === 'F' ? 'Femminile' : 'N/D'); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Nazionalità</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($member['nationality'] ?? 'N/D'); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Tipo Socio</label>
                                                <p class="mb-0"><?php echo ucfirst($member['member_type']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Qualifica Volontario</label>
                                                <p class="mb-0"><?php echo ucfirst($member['volunteer_status']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Data Iscrizione</label>
                                                <p class="mb-0"><?php echo date('d/m/Y', strtotime($member['registration_date'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contatti -->
                            <div class="tab-pane fade" id="contacts" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Contatti</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addContact()">
                                                    <i class="bi bi-plus"></i> Aggiungi Contatto
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['contacts'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Tipo</th>
                                                            <th>Valore</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['contacts'] as $contact): ?>
                                                            <tr>
                                                                <td><?php echo ucfirst(str_replace('_', ' ', $contact['contact_type'])); ?></td>
                                                                <td><?php echo htmlspecialchars($contact['value']); ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteContact(<?php echo $contact['id']; ?>)">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nessun contatto inserito</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Indirizzi -->
                            <div class="tab-pane fade" id="address" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Indirizzi</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addAddress()">
                                                    <i class="bi bi-plus"></i> Aggiungi Indirizzo
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['addresses'])): ?>
                                            <?php foreach ($member['addresses'] as $address): ?>
                                                <div class="border rounded p-3 mb-2">
                                                    <h6><?php echo ucfirst($address['address_type']); ?></h6>
                                                    <p class="mb-1">
                                                        <?php echo htmlspecialchars($address['street'] ?? ''); ?> 
                                                        <?php echo htmlspecialchars($address['number'] ?? ''); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <?php echo htmlspecialchars($address['cap'] ?? ''); ?> 
                                                        <?php echo htmlspecialchars($address['city'] ?? ''); ?> 
                                                        (<?php echo htmlspecialchars($address['province'] ?? ''); ?>)
                                                    </p>
                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteAddress(<?php echo $address['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Elimina
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Nessun indirizzo inserito</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Datore di Lavoro -->
                            <div class="tab-pane fade" id="employment" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Datore di Lavoro</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addEmployment()">
                                                    <i class="bi bi-plus"></i> Aggiungi Datore
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['employment'])): ?>
                                            <?php foreach ($member['employment'] as $emp): ?>
                                                <div class="border rounded p-3 mb-2">
                                                    <h6><?php echo htmlspecialchars($emp['employer_name'] ?? 'N/D'); ?></h6>
                                                    <p class="mb-1"><strong>Indirizzo:</strong> <?php echo htmlspecialchars($emp['employer_address'] ?? 'N/D'); ?></p>
                                                    <p class="mb-1"><strong>Città:</strong> <?php echo htmlspecialchars($emp['employer_city'] ?? 'N/D'); ?></p>
                                                    <p class="mb-1"><strong>Telefono:</strong> <?php echo htmlspecialchars($emp['employer_phone'] ?? 'N/D'); ?></p>
                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteEmployment(<?php echo $emp['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Elimina
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Nessun datore di lavoro inserito</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Qualifiche -->
                            <div class="tab-pane fade" id="qualifications" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Qualifiche e Ruoli</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addRole()">
                                                    <i class="bi bi-plus"></i> Aggiungi Qualifica
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['roles'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Qualifica</th>
                                                            <th>Data Assegnazione</th>
                                                            <th>Data Fine</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['roles'] as $role): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                                                <td><?php echo $role['assigned_date'] ? date('d/m/Y', strtotime($role['assigned_date'])) : 'N/D'; ?></td>
                                                                <td><?php echo $role['end_date'] ? date('d/m/Y', strtotime($role['end_date'])) : 'Attiva'; ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteRole(<?php echo $role['id']; ?>)">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nessuna qualifica inserita</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Corsi -->
                            <div class="tab-pane fade" id="courses" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Corsi e Formazione</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addCourse()">
                                                    <i class="bi bi-plus"></i> Aggiungi Corso
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['courses'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Nome Corso</th>
                                                            <th>Tipo</th>
                                                            <th>Data Completamento</th>
                                                            <th>Data Scadenza</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['courses'] as $course): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($course['course_type'] ?? 'N/D'); ?></td>
                                                                <td><?php echo $course['completion_date'] ? date('d/m/Y', strtotime($course['completion_date'])) : 'N/D'; ?></td>
                                                                <td><?php echo $course['expiry_date'] ? date('d/m/Y', strtotime($course['expiry_date'])) : 'N/D'; ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteCourse(<?php echo $course['id']; ?>)">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nessun corso inserito</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Patenti -->
                            <div class="tab-pane fade" id="licenses" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Patenti e Abilitazioni</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addLicense()">
                                                    <i class="bi bi-plus"></i> Aggiungi Patente
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['licenses'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Tipo Patente</th>
                                                            <th>Numero</th>
                                                            <th>Data Rilascio</th>
                                                            <th>Data Scadenza</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['licenses'] as $license): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($license['license_type']); ?></td>
                                                                <td><?php echo htmlspecialchars($license['license_number'] ?? 'N/D'); ?></td>
                                                                <td><?php echo $license['issue_date'] ? date('d/m/Y', strtotime($license['issue_date'])) : 'N/D'; ?></td>
                                                                <td><?php echo $license['expiry_date'] ? date('d/m/Y', strtotime($license['expiry_date'])) : 'N/D'; ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteLicense(<?php echo $license['id']; ?>)">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nessuna patente inserita</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Allergie/Salute -->
                            <div class="tab-pane fade" id="health" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Allergie e Informazioni Sanitarie</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addHealth()">
                                                    <i class="bi bi-plus"></i> Aggiungi Informazione
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['health'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Tipo</th>
                                                            <th>Descrizione</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['health'] as $health): ?>
                                                            <tr>
                                                                <td><?php echo ucfirst($health['health_type']); ?></td>
                                                                <td><?php echo htmlspecialchars($health['description'] ?? 'N/D'); ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteHealth(<?php echo $health['id']; ?>)">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nessuna informazione sanitaria inserita</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const memberId = <?php echo $member['id']; ?>;
        
        function printCard() {
            window.open('member_card.php?id=' + memberId, '_blank');
        }
        
        function addContact() {
            const type = prompt('Tipo di contatto (telefono_fisso, cellulare, email, pec):');
            if (!type) return;
            const value = prompt('Valore:');
            if (!value) return;
            
            window.location.href = 'member_data.php?action=add_contact&member_id=' + memberId + 
                                   '&type=' + encodeURIComponent(type) + 
                                   '&value=' + encodeURIComponent(value);
        }
        
        function deleteContact(id) {
            if (confirm('Sei sicuro di voler eliminare questo contatto?')) {
                window.location.href = 'member_data.php?action=delete_contact&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function addAddress() {
            window.location.href = 'member_address_edit.php?member_id=' + memberId;
        }
        
        function deleteAddress(id) {
            if (confirm('Sei sicuro di voler eliminare questo indirizzo?')) {
                window.location.href = 'member_data.php?action=delete_address&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function addEmployment() {
            window.location.href = 'member_employment_edit.php?member_id=' + memberId;
        }
        
        function deleteEmployment(id) {
            if (confirm('Sei sicuro di voler eliminare questo datore di lavoro?')) {
                window.location.href = 'member_data.php?action=delete_employment&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function addRole() {
            window.location.href = 'member_role_edit.php?member_id=' + memberId;
        }
        
        function deleteRole(id) {
            if (confirm('Sei sicuro di voler eliminare questa qualifica?')) {
                window.location.href = 'member_data.php?action=delete_role&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function addCourse() {
            window.location.href = 'member_course_edit.php?member_id=' + memberId;
        }
        
        function deleteCourse(id) {
            if (confirm('Sei sicuro di voler eliminare questo corso?')) {
                window.location.href = 'member_data.php?action=delete_course&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function addLicense() {
            window.location.href = 'member_license_edit.php?member_id=' + memberId;
        }
        
        function deleteLicense(id) {
            if (confirm('Sei sicuro di voler eliminare questa patente?')) {
                window.location.href = 'member_data.php?action=delete_license&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function addHealth() {
            window.location.href = 'member_health_edit.php?member_id=' + memberId;
        }
        
        function deleteHealth(id) {
            if (confirm('Sei sicuro di voler eliminare questa informazione sanitaria?')) {
                window.location.href = 'member_data.php?action=delete_health&id=' + id + '&member_id=' + memberId;
            }
        }
    </script>
</body>
</html>
