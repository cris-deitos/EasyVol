<?php
/**
 * Gestione Magazzino - Visualizzazione Dettaglio
 * 
 * Pagina per visualizzare i dettagli completi di un articolo di magazzino
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;
use EasyVol\Controllers\WarehouseController;

$app = new App();

// Verifica autenticazione
if (!$app->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Verifica permessi
if (!$app->hasPermission('warehouse', 'view')) {
    die('Accesso negato');
}

$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemId <= 0) {
    header('Location: warehouse.php');
    exit;
}

$db = $app->getDatabase();
$config = $app->getConfig();
$controller = new WarehouseController($db, $config);

$item = $controller->get($itemId);

if (!$item) {
    header('Location: warehouse.php?error=not_found');
    exit;
}

$pageTitle = 'Dettaglio Articolo: ' . $item['name'];
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
                        <a href="warehouse.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($app->hasPermission('warehouse', 'edit')): ?>
                                <a href="warehouse_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Modifica
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="printQrCode()">
                                <i class="bi bi-qr-code"></i> Stampa QR Code
                            </button>
                            <button type="button" class="btn btn-success" onclick="printBarcode()">
                                <i class="bi bi-upc-scan"></i> Stampa Barcode
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="itemTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                            <i class="bi bi-info-circle"></i> Informazioni Generali
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movements" type="button">
                            <i class="bi bi-arrow-left-right"></i> Movimenti
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="dpi-tab" data-bs-toggle="tab" data-bs-target="#dpi" type="button">
                            <i class="bi bi-person-badge"></i> DPI Assegnati
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="itemTabContent">
                    <!-- Tab Informazioni Generali -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="bi bi-box-seam"></i> Dati Articolo</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Nome:</th>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Codice:</th>
                                                <td><code><?php echo htmlspecialchars($item['code'] ?? '-'); ?></code></td>
                                            </tr>
                                            <tr>
                                                <th>Categoria:</th>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars(ucfirst($item['category'] ?? 'Altro')); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Descrizione:</th>
                                                <td><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Unità di Misura:</th>
                                                <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Ubicazione:</th>
                                                <td><?php echo htmlspecialchars($item['location'] ?? '-'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Giacenza</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Quantità Disponibile:</th>
                                                <td>
                                                    <strong class="<?php echo ($item['quantity'] ?? 0) <= ($item['minimum_quantity'] ?? 0) ? 'text-danger' : 'text-success'; ?>">
                                                        <?php echo htmlspecialchars($item['quantity'] ?? 0); ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Scorta Minima:</th>
                                                <td><?php echo htmlspecialchars($item['minimum_quantity'] ?? 0); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Stato:</th>
                                                <td>
                                                    <?php 
                                                    $status = $item['status'] ?? 'disponibile';
                                                    $statusClass = [
                                                        'disponibile' => 'success',
                                                        'esaurito' => 'danger',
                                                        'in_ordine' => 'warning'
                                                    ];
                                                    $class = $statusClass[$status] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php if (($item['quantity'] ?? 0) <= ($item['minimum_quantity'] ?? 0)): ?>
                                            <tr>
                                                <td colspan="2">
                                                    <div class="alert alert-warning mb-0">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                        <strong>Attenzione!</strong> Scorta sotto il livello minimo
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if (!empty($item['notes'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-chat-left-text"></i> Note</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($item['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Movimenti -->
                    <div class="tab-pane fade" id="movements" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Storico Movimenti</h5>
                                    <?php if ($app->hasPermission('warehouse', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMovementModal">
                                            <i class="bi bi-plus-circle"></i> Nuovo Movimento
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($item['movements'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Tipo</th>
                                                    <th>Quantità</th>
                                                    <th>Causale</th>
                                                    <th>Utente</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($item['movements'] as $movement): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movement['created_at']))); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $movement['movement_type'] == 'carico' ? 'success' : 'danger'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($movement['movement_type'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($movement['quantity']); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['reason'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['user_name'] ?? '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun movimento registrato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab DPI Assegnati -->
                    <div class="tab-pane fade" id="dpi" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person-badge"></i> DPI Assegnati ai Volontari</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($item['dpi_assignments'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Volontario</th>
                                                    <th>Quantità Assegnata</th>
                                                    <th>Data Assegnazione</th>
                                                    <th>Scadenza</th>
                                                    <th>Stato</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($item['dpi_assignments'] as $assignment): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($assignment['member_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($assignment['quantity']); ?></td>
                                                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($assignment['assigned_date']))); ?></td>
                                                        <td>
                                                            <?php 
                                                            if (!empty($assignment['expiry_date'])) {
                                                                $date = new DateTime($assignment['expiry_date']);
                                                                $now = new DateTime();
                                                                $isExpired = $date < $now;
                                                                echo '<span class="badge bg-' . ($isExpired ? 'danger' : 'success') . '">' . 
                                                                     htmlspecialchars($date->format('d/m/Y')) . '</span>';
                                                            } else {
                                                                echo '-';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $assignment['status'] == 'assegnato' ? 'success' : 'secondary'; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($assignment['status'])); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nessun DPI assegnato.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printQrCode() {
            // Implementare generazione e stampa QR code
            alert('Funzionalità in sviluppo');
        }
        
        function printBarcode() {
            // Implementare generazione e stampa Barcode
            alert('Funzionalità in sviluppo');
        }
    </script>
</body>
</html>
