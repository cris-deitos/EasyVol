<?php
/**
 * Gestione Soci - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un socio
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Utils\PathHelper;
use EasyVol\Controllers\MemberController;
use EasyVol\Controllers\PrintTemplateController;

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

// Load print templates for members
$printController = new PrintTemplateController($db, $config);
$printTemplates = $printController->getAll([
    'entity_type' => 'members',
    'is_active' => 1
]);

// Get filter parameters for navigation
$filters = [
    'status' => $_GET['status'] ?? '',
    'volunteer_status' => $_GET['volunteer_status'] ?? '',
    'role' => $_GET['role'] ?? '',
    'search' => $_GET['search'] ?? '',
    'hide_dismissed' => $_GET['hide_dismissed'] ?? '1',
    'sort_by' => $_GET['sort_by'] ?? 'registration_number'
];

// Get next and previous member IDs
$nextMemberId = $controller->getNextMemberId($memberId, $filters);
$previousMemberId = $controller->getPreviousMemberId($memberId, $filters);

// Determine which sensitive data fields are being accessed based on active tab
// Whitelist of allowed tab values
$allowedTabs = ['personal', 'contacts', 'address', 'qualifications', 'courses', 'licenses', 
                'health', 'health-surveillance', 'availability', 'fees', 'sanctions', 'notes', 'attachments'];
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], $allowedTabs) ? $_GET['tab'] : 'personal';

// Map tabs to sensitive data categories
$tabDataMap = [
    'personal' => ['personal_data', 'birth_info', 'tax_code'],
    'contacts' => ['personal_data', 'contacts', 'email', 'phone'],
    'address' => ['personal_data', 'addresses', 'residence'],
    'qualifications' => ['personal_data', 'qualifications'],
    'courses' => ['personal_data', 'courses', 'training'],
    'licenses' => ['personal_data', 'licenses', 'driving_licenses'],
    'health' => ['personal_data', 'health_info', 'allergies', 'intolerances'],
    'health-surveillance' => ['personal_data', 'health_surveillance'],
    'availability' => ['personal_data', 'availability'],
    'fees' => ['personal_data', 'fees'],
    'sanctions' => ['personal_data', 'sanctions'],
    'notes' => ['personal_data', 'notes'],
    'attachments' => ['personal_data', 'attachments']
];

$dataFields = $tabDataMap[$activeTab];

// Log sensitive data access
$app->logSensitiveDataAccess(
    'member',
    $memberId,
    'view',
    'members',
    $dataFields,
    'Visualizzazione scheda socio'
);

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
                        <?php
                        // Build back URL preserving filter parameters
                        $backParams = $_GET;
                        unset($backParams['id']);
                        unset($backParams['tab']);
                        $backQueryString = !empty($backParams) ? '?' . http_build_query($backParams) : '';
                        ?>
                        <a href="members.php<?php echo $backQueryString; ?>" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <!-- Navigation buttons -->
                        <div class="btn-group me-2">
                            <?php
                            // Build navigation query string (preserve all filters, change only ID)
                            $navParams = $_GET;
                            unset($navParams['tab']); // Don't preserve tab when navigating
                            ?>
                            <?php if ($previousMemberId): ?>
                                <?php
                                $navParams['id'] = $previousMemberId;
                                $previousUrl = 'member_view.php?' . http_build_query($navParams);
                                ?>
                                <a href="<?php echo $previousUrl; ?>" class="btn btn-outline-secondary" title="Socio Precedente">
                                    <i class="bi bi-chevron-left"></i> Precedente
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled title="Nessun socio precedente">
                                    <i class="bi bi-chevron-left"></i> Precedente
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($nextMemberId): ?>
                                <?php
                                $navParams['id'] = $nextMemberId;
                                $nextUrl = 'member_view.php?' . http_build_query($navParams);
                                ?>
                                <a href="<?php echo $nextUrl; ?>" class="btn btn-outline-secondary" title="Socio Successivo">
                                    Successivo <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled title="Nessun socio successivo">
                                    Successivo <i class="bi bi-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="btn-group me-2">
                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                <a href="member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-printer"></i> Stampa
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if (!empty($printTemplates)): ?>
                                        <?php 
                                        $displayedTemplates = array_slice($printTemplates, 0, 3); 
                                        foreach ($displayedTemplates as $template): 
                                        ?>
                                            <li><a class="dropdown-item" href="#" onclick="printById(<?php echo $template['id']; ?>); return false;">
                                                <i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars($template['name']); ?>
                                            </a></li>
                                        <?php endforeach; ?>
                                        <?php if (count($printTemplates) > 3): ?>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="#" onclick="showPrintModal(); return false;">
                                        <i class="bi bi-gear"></i> Scegli Template...
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dati Principali -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <?php 
                                // Check if photo exists by converting relative path to absolute
                                $hasPhoto = false;
                                if (!empty($member['photo_path'])) {
                                    $absolutePath = PathHelper::relativeToAbsolute($member['photo_path']);
                                    $hasPhoto = file_exists($absolutePath);
                                }
                                ?>
                                <?php if ($hasPhoto): ?>
                                    <img src="download.php?type=member_photo&id=<?php echo $member['id']; ?>" 
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
                                    'decaduto' => 'dark',
                                    'escluso' => 'danger'
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
                                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">
                                    <i class="bi bi-person"></i> Dati Anagrafici
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab" aria-controls="contacts" aria-selected="false">
                                    <i class="bi bi-telephone"></i> Contatti
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab" aria-controls="address" aria-selected="false">
                                    <i class="bi bi-house"></i> Indirizzi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="qualifications-tab" data-bs-toggle="tab" data-bs-target="#qualifications" type="button" role="tab" aria-controls="qualifications" aria-selected="false">
                                    <i class="bi bi-award"></i> Qualifiche
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab" aria-controls="courses" aria-selected="false">
                                    <i class="bi bi-book"></i> Corsi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="licenses-tab" data-bs-toggle="tab" data-bs-target="#licenses" type="button" role="tab" aria-controls="licenses" aria-selected="false">
                                    <i class="bi bi-card-list"></i> Patenti
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button" role="tab" aria-controls="health" aria-selected="false">
                                    <i class="bi bi-heart-pulse"></i> Info Alimentari
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="health-surveillance-tab" data-bs-toggle="tab" data-bs-target="#health-surveillance" type="button" role="tab" aria-controls="health-surveillance" aria-selected="false">
                                    <i class="bi bi-clipboard2-pulse"></i> Sorveglianza Sanitaria
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="availability-tab" data-bs-toggle="tab" data-bs-target="#availability" type="button" role="tab" aria-controls="availability" aria-selected="false">
                                    <i class="bi bi-geo-alt"></i> Disponibilità
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="fees-tab" data-bs-toggle="tab" data-bs-target="#fees" type="button" role="tab" aria-controls="fees" aria-selected="false">
                                    <i class="bi bi-cash"></i> Quote Sociali
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sanctions-tab" data-bs-toggle="tab" data-bs-target="#sanctions" type="button" role="tab" aria-controls="sanctions" aria-selected="false">
                                    <i class="bi bi-exclamation-triangle"></i> Provvedimenti
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
                                    <i class="bi bi-chat-left-text"></i> Note
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="attachments-tab" data-bs-toggle="tab" data-bs-target="#attachments" type="button" role="tab" aria-controls="attachments" aria-selected="false">
                                    <i class="bi bi-paperclip"></i> Allegati
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
                                                <p class="mb-0">
                                                    <?php
                                                    $volunteerStatusLabels = [
                                                        'operativo' => 'Operativo',
                                                        'in_formazione' => 'In Formazione',
                                                        'non_operativo' => 'Non Operativo'
                                                    ];
                                                    echo $volunteerStatusLabels[$member['volunteer_status']] ?? ucfirst($member['volunteer_status']);
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Data Iscrizione</label>
                                                <p class="mb-0"><?php echo date('d/m/Y', strtotime($member['registration_date'])); ?></p>
                                            </div>
                                        </div>
                                        
                                        <h5 class="card-title mt-4">Informazioni Professionali e Formative</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Tipo di Lavoratore</label>
                                                <p class="mb-0">
                                                    <?php 
                                                    $workerTypes = [
                                                        'studente' => 'Studente',
                                                        'dipendente_privato' => 'Dipendente Privato',
                                                        'dipendente_pubblico' => 'Dipendente Pubblico',
                                                        'lavoratore_autonomo' => 'Lavoratore Autonomo',
                                                        'disoccupato' => 'Disoccupato',
                                                        'pensionato' => 'Pensionato'
                                                    ];
                                                    echo !empty($member['worker_type']) ? ($workerTypes[$member['worker_type']] ?? 'N/D') : 'N/D';
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Titolo di Studio</label>
                                                <p class="mb-0">
                                                    <?php 
                                                    $educationLevels = [
                                                        'licenza_media' => 'Licenza Media',
                                                        'diploma_maturita' => 'Diploma di Maturità',
                                                        'laurea_triennale' => 'Laurea Triennale',
                                                        'laurea_magistrale' => 'Laurea Magistrale',
                                                        'dottorato' => 'Dottorato'
                                                    ];
                                                    echo !empty($member['education_level']) ? ($educationLevels[$member['education_level']] ?? 'N/D') : 'N/D';
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Corso Base Protezione Civile</label>
                                                <p class="mb-0">
                                                    <?php 
                                                    echo !empty($member['corso_base_completato']) ? 'Completato' : 'Non Completato';
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="text-muted small">Anno Corso Base</label>
                                                <p class="mb-0">
                                                    <?php 
                                                    echo !empty($member['corso_base_anno']) ? htmlspecialchars($member['corso_base_anno']) : 'N/D';
                                                    ?>
                                                </p>
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
                                                <a href="member_contact_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Contatto
                                                </a>
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
                                                                        <a href="member_contact_edit.php?member_id=<?php echo $member['id']; ?>&id=<?php echo $contact['id']; ?>" 
                                                                           class="btn btn-sm btn-warning">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </a>
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
                            
                            <!-- Qualifiche -->
                            <div class="tab-pane fade" id="qualifications" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Mansioni e Qualifiche</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_role_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Mansione
                                                </a>
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
                                                                        <a href="member_role_edit.php?member_id=<?php echo $member['id']; ?>&id=<?php echo $role['id']; ?>" 
                                                                           class="btn btn-sm btn-warning">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </a>
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
                                        <div class="alert alert-info small mb-3">
                                            <i class="bi bi-info-circle"></i> 
                                            I corsi completati con successo nella sezione <strong>Formazione</strong> 
                                            vengono automaticamente aggiunti qui. I corsi provenienti da Formazione 
                                            hanno l'etichetta <span class="badge bg-success"><i class="bi bi-building"></i> Formazione</span> 
                                            e non possono essere eliminati manualmente.
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
                                                            <th>Fonte</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['courses'] as $course): ?>
                                                            <tr>
                                                                <td>
                                                                    <?php if (!empty($course['training_course_id'])): ?>
                                                                        <a href="training_view.php?id=<?php echo intval($course['training_course_id']); ?>" 
                                                                           class="text-decoration-none" title="Visualizza corso formazione">
                                                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($course['course_type'] ?? 'N/D'); ?></td>
                                                                <td><?php echo $course['completion_date'] ? date('d/m/Y', strtotime($course['completion_date'])) : 'N/D'; ?></td>
                                                                <td><?php echo $course['expiry_date'] ? date('d/m/Y', strtotime($course['expiry_date'])) : 'N/D'; ?></td>
                                                                <td>
                                                                    <?php if (!empty($course['training_course_id'])): ?>
                                                                        <span class="badge bg-success" title="Corso organizzato dall'associazione">
                                                                            <i class="bi bi-building"></i> Formazione
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary" title="Corso esterno">
                                                                            <i class="bi bi-file-earmark"></i> Manuale
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <?php if (empty($course['training_course_id'])): ?>
                                                                            <button class="btn btn-sm btn-danger" onclick="deleteCourse(<?php echo $course['id']; ?>)" 
                                                                                    title="Elimina corso">
                                                                                <i class="bi bi-trash"></i>
                                                                            </button>
                                                                        <?php else: ?>
                                                                            <span class="text-muted small" title="I corsi dalla Formazione non possono essere eliminati da qui">
                                                                                <i class="bi bi-lock"></i>
                                                                            </span>
                                                                        <?php endif; ?>
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
                                            <h5 class="card-title mb-0">Informazioni Alimentari</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_health_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Informazione
                                                </a>
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
                                                                        <a href="member_health_edit.php?member_id=<?php echo $member['id']; ?>&id=<?php echo $health['id']; ?>" 
                                                                           class="btn btn-sm btn-warning">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </a>
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
                            
                            <!-- Sorveglianza Sanitaria -->
                            <div class="tab-pane fade" id="health-surveillance" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Sorveglianza Sanitaria</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_health_surveillance_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Visita
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                        // Fetch health surveillance records
                                        $sql = "SELECT * FROM member_health_surveillance 
                                                WHERE member_id = :member_id 
                                                ORDER BY visit_date DESC";
                                        $healthSurveillance = $db->fetchAll($sql, ['member_id' => $member['id']]);
                                        ?>
                                        <?php if (!empty($healthSurveillance)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Data Visita</th>
                                                            <th>Esito</th>
                                                            <th>Data Scadenza</th>
                                                            <th>Note</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($healthSurveillance as $surveillance): ?>
                                                            <?php
                                                            $resultColors = [
                                                                'Regolare' => 'success',
                                                                'Con Limitazioni' => 'warning',
                                                                'Da Ripetere' => 'danger'
                                                            ];
                                                            $color = $resultColors[$surveillance['result']] ?? 'secondary';
                                                            
                                                            // Check if expiring soon (within 30 days)
                                                            $expiryDate = strtotime($surveillance['expiry_date']);
                                                            $today = strtotime(date('Y-m-d'));
                                                            $daysUntilExpiry = ($expiryDate - $today) / (60 * 60 * 24);
                                                            $isExpiring = $daysUntilExpiry <= 30 && $daysUntilExpiry > 0;
                                                            $isExpired = $daysUntilExpiry <= 0;
                                                            ?>
                                                            <tr class="<?php echo $isExpired ? 'table-danger' : ($isExpiring ? 'table-warning' : ''); ?>">
                                                                <td><?php echo date('d/m/Y', strtotime($surveillance['visit_date'])); ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $color; ?>">
                                                                        <?php echo htmlspecialchars($surveillance['result']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php echo date('d/m/Y', strtotime($surveillance['expiry_date'])); ?>
                                                                    <?php if ($isExpired): ?>
                                                                        <span class="badge bg-danger ms-1">Scaduta</span>
                                                                    <?php elseif ($isExpiring): ?>
                                                                        <span class="badge bg-warning ms-1">In scadenza</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($surveillance['notes'] ?? 'N/D'); ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <a href="member_health_surveillance_edit.php?member_id=<?php echo $member['id']; ?>&id=<?php echo $surveillance['id']; ?>" 
                                                                           class="btn btn-sm btn-warning">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </a>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteHealthSurveillance(<?php echo $surveillance['id']; ?>)">
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
                                            <p class="text-muted">Nessuna visita di sorveglianza sanitaria registrata</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Disponibilità Territoriale -->
                            <div class="tab-pane fade" id="availability" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Disponibilità Territoriale</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_availability_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Disponibilità
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['availability'])): ?>
                                            <div class="list-group">
                                                <?php foreach ($member['availability'] as $avail): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-primary rounded-pill"><?php echo ucfirst($avail['availability_type']); ?></span>
                                                        <?php if ($app->checkPermission('members', 'edit')): ?>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteAvailability(<?php echo $avail['id']; ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nessuna disponibilità territoriale inserita</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quote Sociali -->
                            <div class="tab-pane fade" id="fees" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Quote Sociali Pagate</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_fee_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Anno
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['fees'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Anno</th>
                                                            <th>Data Pagamento</th>
                                                            <th>Importo</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['fees'] as $fee): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($fee['year']); ?></td>
                                                                <td><?php echo $fee['payment_date'] ? date('d/m/Y', strtotime($fee['payment_date'])) : 'N/D'; ?></td>
                                                                <td><?php echo $fee['amount'] ? '€ ' . number_format($fee['amount'], 2) : 'N/D'; ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <a href="member_fee_edit.php?member_id=<?php echo $member['id']; ?>&id=<?php echo $fee['id']; ?>" 
                                                                           class="btn btn-sm btn-warning">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </a>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteFee(<?php echo $fee['id']; ?>)">
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
                                            <p class="text-muted">Nessuna quota sociale registrata</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Provvedimenti -->
                            <div class="tab-pane fade" id="sanctions" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Provvedimenti a Carico</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_sanction_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Provvedimento
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['sanctions'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Data</th>
                                                            <th>Tipo</th>
                                                            <th>Motivazione</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['sanctions'] as $sanction): ?>
                                                            <tr>
                                                                <td><?php echo date('d/m/Y', strtotime($sanction['sanction_date'])); ?></td>
                                                                <td>
                                                                    <?php
                                                                    $sanctionLabels = [
                                                                        'approvazione_consiglio_direttivo' => 'Approvazione del Consiglio Direttivo',
                                                                        'decaduto' => 'Decaduto',
                                                                        'dimesso' => 'Dimesso',
                                                                        'in_aspettativa' => 'In Aspettativa',
                                                                        'sospeso' => 'Sospeso',
                                                                        'in_congedo' => 'In Congedo',
                                                                        'attivo' => 'Attivo'
                                                                    ];
                                                                    echo '<span class="badge bg-warning">' . ($sanctionLabels[$sanction['sanction_type']] ?? ucfirst($sanction['sanction_type'])) . '</span>';
                                                                    ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($sanction['reason'] ?? 'N/D'); ?></td>
                                                                <td>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <a href="member_sanction_edit.php?member_id=<?php echo $member['id']; ?>&id=<?php echo $sanction['id']; ?>" 
                                                                           class="btn btn-sm btn-warning">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </a>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteSanction(<?php echo $sanction['id']; ?>)">
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
                                            <p class="text-muted">Nessun provvedimento registrato</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Note -->
                            <div class="tab-pane fade" id="notes" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Note sul Socio</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_note_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Aggiungi Nota
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['notes'])): ?>
                                            <div class="list-group">
                                                <?php foreach ($member['notes'] as $note): ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?></small>
                                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                <div>
                                                                    <a href="member_note_edit.php?member_id=<?php echo $member['id']; ?>&id=<?php echo $note['id']; ?>" 
                                                                       class="btn btn-sm btn-warning">
                                                                        <i class="bi bi-pencil"></i>
                                                                    </a>
                                                                    <button class="btn btn-sm btn-danger" onclick="deleteNote(<?php echo $note['id']; ?>)">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Nessuna nota inserita</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Allegati -->
                            <div class="tab-pane fade" id="attachments" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Allegati Documentali</h5>
                                            <?php if ($app->checkPermission('members', 'edit')): ?>
                                                <a href="member_attachment_edit.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus"></i> Carica Documento
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($member['attachments'])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Nome File</th>
                                                            <th>Tipo</th>
                                                            <th>Descrizione</th>
                                                            <th>Data Caricamento</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($member['attachments'] as $attachment): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($attachment['file_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($attachment['file_type'] ?? 'N/D'); ?></td>
                                                                <td><?php echo htmlspecialchars($attachment['description'] ?? 'N/D'); ?></td>
                                                                <td><?php echo date('d/m/Y', strtotime($attachment['uploaded_at'])); ?></td>
                                                                <td>
                                                                    <a href="download.php?type=member_attachment&id=<?php echo $attachment['id']; ?>" 
                                                                       class="btn btn-sm btn-info" target="_blank">
                                                                        <i class="bi bi-download"></i>
                                                                    </a>
                                                                    <?php if ($app->checkPermission('members', 'edit')): ?>
                                                                        <button class="btn btn-sm btn-danger" onclick="deleteAttachment(<?php echo $attachment['id']; ?>)">
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
                                            <p class="text-muted">Nessun allegato caricato</p>
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
        
        function deleteHealth(id) {
            if (confirm('Sei sicuro di voler eliminare questa informazione sanitaria?')) {
                window.location.href = 'member_data.php?action=delete_health&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function deleteAvailability(id) {
            if (confirm('Sei sicuro di voler eliminare questa disponibilità?')) {
                window.location.href = 'member_data.php?action=delete_availability&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function deleteFee(id) {
            if (confirm('Sei sicuro di voler eliminare questa quota?')) {
                window.location.href = 'member_data.php?action=delete_fee&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function deleteSanction(id) {
            if (confirm('Sei sicuro di voler eliminare questo provvedimento?')) {
                window.location.href = 'member_data.php?action=delete_sanction&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function deleteNote(id) {
            if (confirm('Sei sicuro di voler eliminare questa nota?')) {
                window.location.href = 'member_data.php?action=delete_note&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function deleteHealthSurveillance(id) {
            if (confirm('Sei sicuro di voler eliminare questa visita di sorveglianza sanitaria?')) {
                window.location.href = 'member_data.php?action=delete_health_surveillance&id=' + id + '&member_id=' + memberId;
            }
        }
        
        function deleteAttachment(id) {
            if (confirm('Sei sicuro di voler eliminare questo allegato?')) {
                window.location.href = 'member_data.php?action=delete_attachment&id=' + id + '&member_id=' + memberId;
            }
        }
        
        // Print functionality
        function printById(templateId) {
            const url = 'print_preview.php?template_id=' + templateId + '&record_id=<?php echo $member['id']; ?>&entity=members';
            window.open(url, '_blank');
        }
        
        function showPrintModal() {
            const modal = new bootstrap.Modal(document.getElementById('printModal'));
            modal.show();
        }
        
        function generateFromModal() {
            const templateId = document.getElementById('templateSelect').value;
            if (templateId && templateId !== '') {
                printById(templateId);
                const modal = bootstrap.Modal.getInstance(document.getElementById('printModal'));
                modal.hide();
            }
        }
        
        // Handle tab activation from URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            
            if (activeTab) {
                const tabElement = document.getElementById(activeTab + '-tab');
                if (tabElement) {
                    const tab = new bootstrap.Tab(tabElement);
                    tab.show();
                }
            }
        });
    </script>

    <!-- Print Template Selection Modal -->
    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleziona Template di Stampa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select id="templateSelect" class="form-select">
                            <?php if (empty($printTemplates)): ?>
                                <option value="">Nessun template disponibile</option>
                            <?php else: ?>
                                <?php foreach ($printTemplates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>">
                                        <?php echo htmlspecialchars($template['name']); ?>
                                        <?php if ($template['template_format'] === 'xml'): ?>
                                            (XML)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="generateFromModal()" <?php echo empty($printTemplates) ? 'disabled' : ''; ?>>
                        <i class="bi bi-printer"></i> Genera
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
