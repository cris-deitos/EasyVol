<?php
require_once '../src/Autoloader.php';
EasyVol\Autoloader::register();
require_once '../src/App.php';

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\ConventionController;

$app = App::getInstance();

// Check authentication and permissions
if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!$app->checkPermission('conventions', 'view')) {
    die('Accesso negato');
}

if (empty($_GET['id'])) {
    header('Location: conventions.php');
    exit;
}

// Log page access
AutoLogger::logPageAccess();

$controller = new ConventionController($app->getDb(), $app->getConfig());
$item = $controller->get((int)$_GET['id']);

if (!$item) {
    die('Convenzione non trovata');
}

$pageTitle = 'Convenzione: ' . $item['name'];
$months = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
include '../src/Views/includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include '../src/Views/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars($item['name']) ?></h1>
                <div>
                    <?php if ($app->checkPermission('conventions', 'edit')): ?>
                    <a href="convention_edit.php?id=<?= $item['id'] ?>" class="btn btn-warning"><i class="bi bi-pencil"></i> Modifica</a>
                    <?php endif; ?>
                    <a href="conventions.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Torna alla lista</a>
                </div>
            </div>
            
            <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Convenzione salvata con successo.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Basic info -->
            <div class="card mb-3">
                <div class="card-header"><strong>Dati Convenzione</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4"><strong>Nome:</strong><br><?= htmlspecialchars($item['name']) ?></div>
                        <div class="col-md-3"><strong>Data Inizio:</strong><br><?= date('d/m/Y', strtotime($item['start_date'])) ?></div>
                        <div class="col-md-3"><strong>Data Fine:</strong><br><?= $item['end_date'] ? date('d/m/Y', strtotime($item['end_date'])) : 'Non specificata' ?></div>
                        <div class="col-md-2"><strong>Stato:</strong><br>
                            <?php
                            $now = date('Y-m-d');
                            if (!empty($item['end_date']) && $item['end_date'] < $now) {
                                echo '<span class="badge bg-danger">Scaduta</span>';
                            } elseif ($item['start_date'] > $now) {
                                echo '<span class="badge bg-info">Futura</span>';
                            } else {
                                echo '<span class="badge bg-success">Attiva</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($item['description'])): ?>
                    <div class="row mt-3">
                        <div class="col-12"><strong>Descrizione:</strong><br><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Entities -->
            <div class="card mb-3">
                <div class="card-header"><strong>Enti Convenzionati (<?= count($item['entities']) ?>)</strong></div>
                <div class="card-body">
                    <?php if (empty($item['entities'])): ?>
                    <p class="text-muted">Nessun ente registrato</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Denominazione</th>
                                    <th>Tipo</th>
                                    <th>Codice Fiscale</th>
                                    <th>Indirizzo</th>
                                    <th>Contatti</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item['entities'] as $entity): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entity['denomination']) ?></td>
                                    <td><?= htmlspecialchars($entity['entity_type'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($entity['tax_code'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($entity['address'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($entity['phone'])): ?><i class="bi bi-telephone"></i> <?= htmlspecialchars($entity['phone']) ?><br><?php endif; ?>
                                        <?php if (!empty($entity['email'])): ?><i class="bi bi-envelope"></i> <?= htmlspecialchars($entity['email']) ?><br><?php endif; ?>
                                        <?php if (!empty($entity['pec'])): ?><i class="bi bi-envelope-at"></i> <?= htmlspecialchars($entity['pec']) ?><br><?php endif; ?>
                                        <?php if (!empty($entity['contact_person'])): ?><i class="bi bi-person"></i> <?= htmlspecialchars($entity['contact_person']) ?><?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($entity['notes'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Deadlines -->
            <div class="card mb-3">
                <div class="card-header"><strong>Scadenze Annuali (<?= count($item['deadlines']) ?>)</strong></div>
                <div class="card-body">
                    <?php if (empty($item['deadlines'])): ?>
                    <p class="text-muted">Nessuna scadenza annuale registrata</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Data (GG/MM)</th>
                                    <th>Descrizione</th>
                                    <th>Avviso a</th>
                                    <th>Giorni preavviso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item['deadlines'] as $dl): ?>
                                <tr>
                                    <td><?= str_pad($dl['day_of_month'], 2, '0', STR_PAD_LEFT) ?>/<?= str_pad($dl['month'], 2, '0', STR_PAD_LEFT) ?> (<?= $months[(int)$dl['month']] ?>)</td>
                                    <td><?= htmlspecialchars($dl['description']) ?></td>
                                    <td><?= htmlspecialchars($dl['notify_to'] ?? '-') ?></td>
                                    <td><?= $dl['advance_days'] ?> giorni</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
