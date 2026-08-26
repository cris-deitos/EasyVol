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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - EasyVol</title>
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
            
            <!-- Amounts -->
            <div class="card mb-3">
                <div class="card-header"><strong>Importo Convenzione</strong></div>
                <div class="card-body">
                    <?php if (empty($item['amounts'])): ?>
                    <p class="text-muted">Nessun importo registrato</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Anno</th>
                                    <th>Importo</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item['amounts'] as $amount): ?>
                                <tr>
                                    <td><?= htmlspecialchars($amount['year']) ?></td>
                                    <td>€ <?= number_format((float)$amount['amount'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($amount['notes'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
            
            <!-- Allegati -->
            <div class="card mb-3" id="allegati">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Allegati (<?= count($item['attachments'] ?? []) ?>)</strong>
                    <?php if ($app->checkPermission('conventions', 'edit')): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#uploadForm">
                        <i class="bi bi-upload"></i> Carica Allegato
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); endif; ?>

                    <?php if ($app->checkPermission('conventions', 'edit')): ?>
                    <div class="collapse mb-3" id="uploadForm">
                        <form action="convention_attachment_upload.php" method="POST" enctype="multipart/form-data" class="border rounded p-3 bg-light">
                            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                            <input type="hidden" name="convention_id" value="<?= $item['id'] ?>">
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">File <small class="text-muted">(PDF, P7M, Word, immagini, max 20MB)</small></label>
                                    <input type="file" name="attachment_file" class="form-control form-control-sm" required
                                           accept=".pdf,.p7m,.doc,.docx,.odt,.rtf,.jpg,.jpeg,.png,.gif,.bmp,.tiff,.tif,.webp,.xls,.xlsx,.csv,.txt">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Titolo</label>
                                    <input type="text" name="title" class="form-control form-control-sm" placeholder="Titolo allegato">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Descrizione</label>
                                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Descrizione opzionale">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-upload"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($item['attachments'])): ?>
                    <p class="text-muted">Nessun allegato caricato</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Titolo</th>
                                    <th>Firma Digitale</th>
                                    <th>Caricato da</th>
                                    <th>Data</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item['attachments'] as $att): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                        $iconClass = 'bi-file-earmark';
                                        if ($ext === 'pdf' || $ext === 'p7m') $iconClass = 'bi-file-earmark-pdf';
                                        elseif (in_array($ext, ['doc','docx','odt','rtf'])) $iconClass = 'bi-file-earmark-word';
                                        elseif (in_array($ext, ['jpg','jpeg','png','gif','bmp','tiff','tif','webp'])) $iconClass = 'bi-file-earmark-image';
                                        elseif (in_array($ext, ['xls','xlsx','csv'])) $iconClass = 'bi-file-earmark-spreadsheet';
                                        ?>
                                        <i class="bi <?= $iconClass ?>"></i>
                                        <a href="convention_attachment_download.php?id=<?= $att['id'] ?>"><?= htmlspecialchars($att['file_name']) ?></a>
                                        <?php if ($att['file_size'] > 0): ?>
                                        <small class="text-muted">(<?= number_format($att['file_size'] / 1024, 0) ?> KB)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($att['title'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($att['has_signature']): ?>
                                            <?php
                                            $validityBadge = 'bg-secondary';
                                            $validityText = 'Sconosciuta';
                                            if ($att['signature_validity'] === 'valid') { $validityBadge = 'bg-success'; $validityText = 'Valida'; }
                                            elseif ($att['signature_validity'] === 'invalid') { $validityBadge = 'bg-danger'; $validityText = 'Non valida'; }
                                            ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($att['signature_format']) ?></span>
                                            <span class="badge <?= $validityBadge ?>"><?= $validityText ?></span>
                                            <br>
                                            <?php
                                            $signatures = json_decode($att['signature_data'] ?? '[]', true);
                                            if (!empty($signatures)):
                                                foreach ($signatures as $sig): ?>
                                                <small class="d-block text-muted">
                                                    <i class="bi bi-pen"></i>
                                                    <?= htmlspecialchars($sig['signer_name'] ?? $sig['common_name'] ?? 'Sconosciuto') ?>
                                                    <?php if (!empty($sig['signing_date'])): ?>
                                                     - <?= htmlspecialchars($sig['signing_date']) ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($sig['organization'])): ?>
                                                     (<?= htmlspecialchars($sig['organization']) ?>)
                                                    <?php endif; ?>
                                                </small>
                                                <?php endforeach;
                                            endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($att['uploaded_by_name'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($att['uploaded_at'])) ?></td>
                                    <td>
                                        <a href="convention_attachment_download.php?id=<?= $att['id'] ?>" class="btn btn-sm btn-outline-primary" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <?php if ($app->checkPermission('conventions', 'edit')): ?>
                                        <form action="convention_attachment_delete.php" method="POST" class="d-inline" onsubmit="return confirm('Eliminare questo allegato?')">
                                            <input type="hidden" name="csrf_token" value="<?= CsrfProtection::getToken() ?>">
                                            <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                            <input type="hidden" name="convention_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Elimina">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
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
