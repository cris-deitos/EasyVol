<?php
/**
 * EasyCO - Dettaglio Volontario (Read-Only)
 * 
 * Pagina di visualizzazione limitata per la Centrale Operativa
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;

$app = App::getInstance();

// Verifica autenticazione
if (!$app->isLoggedIn()) {
    header('Location: login_co.php');
    exit;
}

// Verifica che sia utente CO
$user = $app->getCurrentUser();
if (!isset($user['is_operations_center_user']) || !$user['is_operations_center_user']) {
    die('Accesso negato - Solo per utenti EasyCO');
}

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($memberId <= 0) {
    header('Location: operations_members.php');
    exit;
}

$db = $app->getDb();

// Query per ottenere dati limitati del membro
$sql = "SELECT 
    m.id,
    m.registration_number,
    m.badge_number,
    m.first_name,
    m.last_name,
    m.birth_date,
    m.birth_place,
    m.tax_code,
    m.gender,
    m.mobile,
    m.phone,
    m.email,
    m.address_street,
    m.address_number,
    m.address_cap,
    m.address_city,
    m.address_province,
    m.domicile_street,
    m.domicile_number,
    m.domicile_cap,
    m.domicile_city,
    m.domicile_province,
    m.member_status
FROM members m
WHERE m.id = ? AND m.member_status = 'attivo'";

$member = $db->fetchOne($sql, [$memberId]);

if (!$member) {
    header('Location: operations_members.php?error=not_found');
    exit;
}

// Ottieni ruoli operativi
$roles = $db->fetchAll(
    "SELECT r.name, mr.acquired_date 
    FROM member_roles mr 
    INNER JOIN roles r ON mr.role_id = r.id 
    WHERE mr.member_id = ?
    ORDER BY mr.acquired_date DESC",
    [$memberId]
);

// Ottieni corsi frequentati
$courses = $db->fetchAll(
    "SELECT c.course_name, c.completion_date, c.expiry_date, c.certification_number
    FROM member_courses c
    WHERE c.member_id = ?
    ORDER BY c.completion_date DESC",
    [$memberId]
);

// Ottieni patenti
$licenses = $db->fetchAll(
    "SELECT l.license_type, l.issue_date, l.expiry_date, l.license_number
    FROM member_licenses l
    WHERE l.member_id = ?
    ORDER BY l.issue_date DESC",
    [$memberId]
);

// Log page access
AutoLogger::logPageAccess();

$pageTitle = 'Volontario: ' . $member['first_name'] . ' ' . $member['last_name'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - EasyCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/easyco.css">
</head>
<body>
    <?php include '../src/Views/includes/navbar_operations.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../src/Views/includes/sidebar_operations.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <a href="operations_members.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Visualizzazione limitata per la Centrale Operativa. 
                    Non è possibile modificare i dati da questa interfaccia.
                </div>
                
                <!-- Dati Anagrafici -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-person"></i> Dati Anagrafici</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Matricola:</label>
                                <p><?php echo htmlspecialchars($member['badge_number'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Numero Tesseramento:</label>
                                <p><?php echo htmlspecialchars($member['registration_number'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Nome:</label>
                                <p><?php echo htmlspecialchars($member['first_name']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Cognome:</label>
                                <p><?php echo htmlspecialchars($member['last_name']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Data di Nascita:</label>
                                <p>
                                    <?php 
                                    if (!empty($member['birth_date'])) {
                                        echo date('d/m/Y', strtotime($member['birth_date']));
                                        $age = date_diff(date_create($member['birth_date']), date_create('today'))->y;
                                        echo " ($age anni)";
                                    } else {
                                        echo 'N/D';
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Luogo di Nascita:</label>
                                <p><?php echo htmlspecialchars($member['birth_place'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Codice Fiscale:</label>
                                <p><?php echo htmlspecialchars($member['tax_code'] ?? 'N/D'); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Sesso:</label>
                                <p>
                                    <?php 
                                    $gender = $member['gender'] ?? '';
                                    echo $gender === 'M' ? 'Maschile' : ($gender === 'F' ? 'Femminile' : 'N/D');
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contatti -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-telephone"></i> Contatti</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="fw-bold">Cellulare:</label>
                                <p>
                                    <?php if (!empty($member['mobile'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($member['mobile']); ?>">
                                            <i class="bi bi-phone"></i> <?php echo htmlspecialchars($member['mobile']); ?>
                                        </a>
                                    <?php else: ?>
                                        N/D
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="fw-bold">Telefono:</label>
                                <p>
                                    <?php if (!empty($member['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>">
                                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($member['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        N/D
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="fw-bold">Email:</label>
                                <p>
                                    <?php if (!empty($member['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>">
                                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($member['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        N/D
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Indirizzi -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-house"></i> Indirizzi</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Residenza:</label>
                                <p>
                                    <?php
                                    $address = [];
                                    if (!empty($member['address_street'])) {
                                        $address[] = $member['address_street'] . ' ' . ($member['address_number'] ?? '');
                                    }
                                    if (!empty($member['address_city'])) {
                                        $address[] = $member['address_cap'] . ' ' . $member['address_city'] . ' (' . $member['address_province'] . ')';
                                    }
                                    echo !empty($address) ? implode('<br>', array_map('htmlspecialchars', $address)) : 'N/D';
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Domicilio:</label>
                                <p>
                                    <?php
                                    $domicile = [];
                                    if (!empty($member['domicile_street'])) {
                                        $domicile[] = $member['domicile_street'] . ' ' . ($member['domicile_number'] ?? '');
                                    }
                                    if (!empty($member['domicile_city'])) {
                                        $domicile[] = $member['domicile_cap'] . ' ' . $member['domicile_city'] . ' (' . $member['domicile_province'] . ')';
                                    }
                                    echo !empty($domicile) ? implode('<br>', array_map('htmlspecialchars', $domicile)) : 'Come residenza';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Qualifiche Operative -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-award"></i> Qualifiche Operative</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($roles)): ?>
                            <p class="text-muted">Nessuna qualifica registrata</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Qualifica</th>
                                            <th>Data Acquisizione</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roles as $role): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($role['name']); ?></td>
                                                <td>
                                                    <?php 
                                                    echo !empty($role['acquired_date']) 
                                                        ? date('d/m/Y', strtotime($role['acquired_date'])) 
                                                        : 'N/D'; 
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Corsi -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-mortarboard"></i> Corsi Frequentati</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($courses)): ?>
                            <p class="text-muted">Nessun corso registrato</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Corso</th>
                                            <th>Data Completamento</th>
                                            <th>Scadenza</th>
                                            <th>N. Certificato</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    echo !empty($course['completion_date']) 
                                                        ? date('d/m/Y', strtotime($course['completion_date'])) 
                                                        : 'N/D'; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($course['expiry_date'])) {
                                                        $expiry = strtotime($course['expiry_date']);
                                                        $now = time();
                                                        echo date('d/m/Y', $expiry);
                                                        if ($expiry < $now) {
                                                            echo ' <span class="badge bg-danger">Scaduto</span>';
                                                        }
                                                    } else {
                                                        echo 'N/D';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($course['certification_number'] ?? 'N/D'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Patenti -->
                <div class="card mb-4">
                    <div class="card-header easyco-header">
                        <h5 class="mb-0"><i class="bi bi-credit-card-2-front"></i> Patenti</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($licenses)): ?>
                            <p class="text-muted">Nessuna patente registrata</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Numero</th>
                                            <th>Rilascio</th>
                                            <th>Scadenza</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($licenses as $license): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($license['license_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($license['license_number'] ?? 'N/D'); ?></td>
                                                <td>
                                                    <?php 
                                                    echo !empty($license['issue_date']) 
                                                        ? date('d/m/Y', strtotime($license['issue_date'])) 
                                                        : 'N/D'; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($license['expiry_date'])) {
                                                        $expiry = strtotime($license['expiry_date']);
                                                        $now = time();
                                                        echo date('d/m/Y', $expiry);
                                                        if ($expiry < $now) {
                                                            echo ' <span class="badge bg-danger">Scaduta</span>';
                                                        }
                                                    } else {
                                                        echo 'N/D';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <a href="operations_members.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla Lista
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
