<?php
/**
 * Gestione Soci - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un socio
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\JuniorMemberController;

$app = new App();

// Verifica autenticazione
if (!$app->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->hasPermission('junior_members', 'view')) {
    die('Accesso negato');
}

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($memberId <= 0) {
    header('Location: junior_members.php');
    exit;
}

$db = $app->getDatabase();
$config = $app->getConfig();
$controller = new JuniorMemberController($db, $config);

$member = $controller->get($memberId);

if (!$member) {
    header('Location: junior_members.php?error=not_found');
    exit;
}

$pageTitle = 'Dettaglio Socio Minorenne: ' . $member['first_name'] . ' ' . $member['last_name'];
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
                        <a href="junior_members.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->hasPermission('junior_members', 'edit')): ?>
                                <a href="junior_member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-warning">
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
                                <button class="nav-link" id="qualifications-tab" data-bs-toggle="tab" data-bs-target="#qualifications" type="button" role="tab">
                                    <i class="bi bi-award"></i> Qualifiche
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
                                        <h5 class="card-title">Contatti</h5>
                                        <p class="text-muted">Sezione in sviluppo</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Indirizzi -->
                            <div class="tab-pane fade" id="address" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Indirizzi</h5>
                                        <p class="text-muted">Sezione in sviluppo</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Qualifiche -->
                            <div class="tab-pane fade" id="qualifications" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Qualifiche e Formazione</h5>
                                        <p class="text-muted">Sezione in sviluppo</p>
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
        function printCard() {
            window.open('member_card.php?id=<?php echo $member['id']; ?>', '_blank');
        }
    </script>
</body>
</html>
